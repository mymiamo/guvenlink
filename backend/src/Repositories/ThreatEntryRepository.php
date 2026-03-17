<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class ThreatEntryRepository
{
    private ?PDO $db = null;

    private function db(): PDO
    {
        if (!$this->db instanceof PDO) {
            $this->db = Database::connection();
        }

        return $this->db;
    }

    public function search(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = 'match_value LIKE :q';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        foreach (['status', 'type', 'source', 'is_active'] as $key) {
            if (($filters[$key] ?? null) !== null && ($filters[$key] ?? '') !== '') {
                $where[] = sprintf('%s = :%s', $key, $key);
                $params[$key] = $filters[$key];
            }
        }

        $sql = sprintf(
            'SELECT * FROM threat_entries WHERE %s ORDER BY updated_at DESC LIMIT :limit OFFSET :offset',
            implode(' AND ', $where)
        );
        $stmt = $this->db()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function count(array $filters = []): int
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = 'match_value LIKE :q';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        foreach (['status', 'type', 'source', 'is_active'] as $key) {
            if (($filters[$key] ?? null) !== null && ($filters[$key] ?? '') !== '') {
                $where[] = sprintf('%s = :%s', $key, $key);
                $params[$key] = $filters[$key];
            }
        }

        $stmt = $this->db()->prepare(sprintf('SELECT COUNT(*) FROM threat_entries WHERE %s', implode(' AND ', $where)));
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function create(array $data): int
    {
        $stmt = $this->db()->prepare('
            INSERT INTO threat_entries (
                type, match_value, normalized_value, normalized_hash, status, source, reason, is_active,
                first_seen_at, last_seen_at, created_at, updated_at
            ) VALUES (
                :type, :match_value, :normalized_value, :normalized_hash, :status, :source, :reason, :is_active,
                :first_seen_at, :last_seen_at, NOW(), NOW()
            )
        ');
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sql = '
            UPDATE threat_entries SET
                type = :type,
                match_value = :match_value,
                normalized_value = :normalized_value,
                normalized_hash = :normalized_hash,
                status = :status,
                source = :source,
                reason = :reason,
                is_active = :is_active,
                first_seen_at = :first_seen_at,
                last_seen_at = :last_seen_at,
                updated_at = NOW()
            WHERE id = :id
        ';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($data + ['id' => $id]);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM threat_entries WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findActiveMatch(string $normalizedUrl, string $hostname): array
    {
        $candidates = [
            ['type' => 'url', 'value' => $normalizedUrl],
            ['type' => 'domain', 'value' => $hostname],
        ];

        $matches = [];
        $stmt = $this->db()->prepare('
            SELECT * FROM threat_entries
            WHERE is_active = 1 AND type = :type AND normalized_hash = :hash AND normalized_value = :value
            ORDER BY FIELD(status, "white", "black"), FIELD(source, "manual", "usom"), updated_at DESC
        ');

        foreach ($candidates as $candidate) {
            $stmt->execute([
                'type' => $candidate['type'],
                'hash' => hash('sha256', $candidate['value']),
                'value' => $candidate['value'],
            ]);
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                $matches[] = $row;
            }
        }

        return $matches;
    }

    public function feedPage(int $page, int $perPage): array
    {
        $offset = max(0, $page - 1) * $perPage;
        $stmt = $this->db()->prepare('
            SELECT id, type, normalized_value AS value, status, source, reason, updated_at
            FROM threat_entries
            WHERE is_active = 1
            ORDER BY id ASC
            LIMIT :limit OFFSET :offset
        ');
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function totalActiveCount(): int
    {
        return (int) $this->db()->query('SELECT COUNT(*) FROM threat_entries WHERE is_active = 1')->fetchColumn();
    }

    public function latestUpdatedAt(): ?string
    {
        $value = $this->db()->query('SELECT MAX(updated_at) FROM threat_entries WHERE is_active = 1')->fetchColumn();
        return $value !== false ? (string) $value : null;
    }

    public function upsertImportedEntry(array $data): string
    {
        $existing = $this->findBySourceAndValue('usom', $data['type'], $data['normalized_value'], $data['normalized_hash']);

        if ($existing === null) {
            $this->create($data);
            return 'added';
        }

        $this->update((int) $existing['id'], $data + [
            'first_seen_at' => $existing['first_seen_at'],
        ]);
        return 'updated';
    }

    public function findBySourceAndValue(string $source, string $type, string $normalizedValue, string $normalizedHash): ?array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM threat_entries
            WHERE source = :source AND type = :type AND normalized_hash = :normalized_hash AND normalized_value = :normalized_value
            LIMIT 1
        ');
        $stmt->execute([
            'source' => $source,
            'type' => $type,
            'normalized_hash' => $normalizedHash,
            'normalized_value' => $normalizedValue,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function deactivateMissingUsomEntries(array $normalizedPairs): int
    {
        if ($normalizedPairs === []) {
            return 0;
        }

        $this->db()->exec('DROP TEMPORARY TABLE IF EXISTS temp_usom_entries');
        $this->db()->exec('CREATE TEMPORARY TABLE temp_usom_entries (type VARCHAR(20), normalized_value VARCHAR(1024), normalized_hash CHAR(64), PRIMARY KEY (type, normalized_hash))');

        $stmt = $this->db()->prepare('INSERT INTO temp_usom_entries (type, normalized_value, normalized_hash) VALUES (:type, :normalized_value, :normalized_hash)');
        foreach ($normalizedPairs as $pair) {
            $stmt->execute($pair);
        }

        $sql = '
            UPDATE threat_entries te
            LEFT JOIN temp_usom_entries temp
                ON temp.type = te.type AND temp.normalized_hash = te.normalized_hash
            SET te.is_active = 0, te.updated_at = NOW()
            WHERE te.source = "usom" AND te.is_active = 1 AND temp.type IS NULL
        ';
        $statement = $this->db()->prepare($sql);
        $statement->execute();
        return $statement->rowCount();
    }
}

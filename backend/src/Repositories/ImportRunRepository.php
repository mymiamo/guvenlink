<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class ImportRunRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function create(string $source): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO import_runs (source, status, started_at, created_at, updated_at)
            VALUES (:source, :status, NOW(), NOW(), NOW())
        ');
        $stmt->execute([
            'source' => $source,
            'status' => 'running',
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function finish(int $id, string $status, array $stats = [], ?string $message = null): void
    {
        $stmt = $this->db->prepare('
            UPDATE import_runs
            SET status = :status,
                finished_at = NOW(),
                added_count = :added_count,
                updated_count = :updated_count,
                deactivated_count = :deactivated_count,
                message = :message,
                updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute([
            'id' => $id,
            'status' => $status,
            'added_count' => $stats['added'] ?? 0,
            'updated_count' => $stats['updated'] ?? 0,
            'deactivated_count' => $stats['deactivated'] ?? 0,
            'message' => $message,
        ]);
    }

    public function latest(string $source = 'usom'): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM import_runs WHERE source = :source ORDER BY id DESC LIMIT 1');
        $stmt->execute(['source' => $source]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function recent(int $limit = 10): array
    {
        $stmt = $this->db->prepare('SELECT * FROM import_runs ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}


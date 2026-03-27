<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class SiteReportRepository
{
    private ?PDO $db = null;

    private function db(): PDO
    {
        if (!$this->db instanceof PDO) {
            $this->db = Database::connection();
        }

        return $this->db;
    }

    public function create(array $data): int
    {
        $existing = $this->findPendingByNormalizedValue($data['normalized_value']);
        if ($existing !== null) {
            $stmt = $this->db()->prepare('
                UPDATE site_reports
                SET report_url = :report_url,
                    report_host = :report_host,
                    report_type = :report_type,
                    note = :note,
                    reporter_ip = :reporter_ip,
                    updated_at = NOW()
                WHERE id = :id
            ');
            $stmt->execute([
                'id' => $existing['id'],
                'report_url' => $data['report_url'],
                'report_host' => $data['report_host'],
                'report_type' => $data['report_type'],
                'note' => $data['note'],
                'reporter_ip' => $data['reporter_ip'],
            ]);

            return (int) $existing['id'];
        }

        $stmt = $this->db()->prepare('
            INSERT INTO site_reports (
                report_url, report_host, normalized_value, report_type, note, reporter_ip,
                status, created_at, updated_at
            ) VALUES (
                :report_url, :report_host, :normalized_value, :report_type, :note, :reporter_ip,
                :status, NOW(), NOW()
            )
        ');
        $stmt->execute($data);
        return (int) $this->db()->lastInsertId();
    }

    public function latest(int $limit = 100): array
    {
        $stmt = $this->db()->prepare('
            SELECT * FROM site_reports
            ORDER BY
                CASE status
                    WHEN "pending" THEN 0
                    WHEN "false_positive" THEN 1
                    WHEN "needs_review" THEN 2
                    WHEN "confirmed_malicious" THEN 3
                    ELSE 4
                END,
                id DESC
            LIMIT :limit
        ');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function summary(): array
    {
        $stmt = $this->db()->query('
            SELECT status, COUNT(*) AS total
            FROM site_reports
            GROUP BY status
        ');

        $summary = [
            'pending' => 0,
            'false_positive' => 0,
            'confirmed_malicious' => 0,
            'needs_review' => 0,
            'rejected' => 0,
        ];

        foreach ($stmt->fetchAll() as $row) {
            $summary[(string) $row['status']] = (int) $row['total'];
        }

        return $summary;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM site_reports WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function markReviewed(int $id, string $status): void
    {
        $stmt = $this->db()->prepare('UPDATE site_reports SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'status' => $status,
        ]);
    }

    private function findPendingByNormalizedValue(string $normalizedValue): ?array
    {
        $stmt = $this->db()->prepare('
            SELECT * FROM site_reports
            WHERE normalized_value = :normalized_value AND status = "pending"
            ORDER BY id DESC
            LIMIT 1
        ');
        $stmt->execute(['normalized_value' => $normalizedValue]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}

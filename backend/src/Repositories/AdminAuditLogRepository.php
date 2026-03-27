<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class AdminAuditLogRepository
{
    private ?PDO $db = null;

    private function db(): PDO
    {
        if (!$this->db instanceof PDO) {
            $this->db = Database::connection();
        }

        return $this->db;
    }

    public function create(string $actorEmail, string $action, string $targetType, ?int $targetId, array $details = []): int
    {
        $stmt = $this->db()->prepare('
            INSERT INTO admin_audit_logs (
                actor_email, action, target_type, target_id, details_json, created_at
            ) VALUES (
                :actor_email, :action, :target_type, :target_id, :details_json, NOW()
            )
        ');
        $stmt->execute([
            'actor_email' => trim(strtolower($actorEmail)),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'details_json' => $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function latest(int $limit = 20): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM admin_audit_logs ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $decoded = json_decode((string) ($row['details_json'] ?? ''), true);
            $row['details'] = is_array($decoded) ? $decoded : [];
        }

        return $rows;
    }
}

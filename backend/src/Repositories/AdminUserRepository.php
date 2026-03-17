<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class AdminUserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function ensureDefaultAdmin(): void
    {
        $count = (int) $this->db->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $stmt = $this->db->prepare('
            INSERT INTO admin_users (email, password_hash, created_at, updated_at)
            VALUES (:email, :password_hash, NOW(), NOW())
        ');
        $stmt->execute([
            'email' => defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@example.com',
            'password_hash' => password_hash(defined('ADMIN_PASSWORD') ? ADMIN_PASSWORD : 'ChangeMe123!', PASSWORD_DEFAULT),
        ]);
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM admin_users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}

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

    public function hasAnyUsers(): bool
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() > 0;
    }

    public function create(string $email, string $password): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO admin_users (email, password_hash, created_at, updated_at)
            VALUES (:email, :password_hash, NOW(), NOW())
        ');
        $stmt->execute([
            'email' => trim(strtolower($email)),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM admin_users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => trim(strtolower($email))]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}

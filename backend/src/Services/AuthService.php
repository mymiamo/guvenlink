<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AdminUserRepository;

final class AuthService
{
    public function __construct(
        private readonly AdminUserRepository $users = new AdminUserRepository(),
    ) {
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($email);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['admin_user'] = [
            'id' => (int) $user['id'],
            'email' => $user['email'],
        ];

        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function user(): ?array
    {
        return $_SESSION['admin_user'] ?? null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }
}

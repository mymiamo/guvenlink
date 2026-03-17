<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AdminUserRepository;

final class AuthService
{
    public function __construct(
        private readonly AdminUserRepository $users = new AdminUserRepository(),
    ) {
        $this->users->ensureDefaultAdmin();
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($email);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        $_SESSION['admin_user'] = [
            'id' => (int) $user['id'],
            'email' => $user['email'],
        ];

        return true;
    }

    public function logout(): void
    {
        unset($_SESSION['admin_user']);
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


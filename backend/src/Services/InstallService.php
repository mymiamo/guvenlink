<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AdminUserRepository;

final class InstallService
{
    public function __construct(
        private readonly AdminUserRepository $users = new AdminUserRepository(),
    ) {
    }

    public function canBootstrap(): bool
    {
        return (defined('INSTALL_ALLOW_WEB_BOOTSTRAP') ? INSTALL_ALLOW_WEB_BOOTSTRAP : false) === true
            && !$this->users->hasAnyUsers();
    }

    public function createAdmin(string $email, string $password): int
    {
        if (!$this->canBootstrap()) {
            throw new \RuntimeException('Install kilitli veya yonetici zaten olusturulmus.');
        }

        return $this->users->create($email, $password);
    }
}

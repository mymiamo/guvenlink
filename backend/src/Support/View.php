<?php

declare(strict_types=1);

namespace App\Support;

final class View
{
    public static function render(string $view, array $data = []): string
    {
        $path = BASE_PATH . '/src/Views/' . $view . '.php';
        extract($data, EXTR_SKIP);

        ob_start();
        require $path;
        return (string) ob_get_clean();
    }
}


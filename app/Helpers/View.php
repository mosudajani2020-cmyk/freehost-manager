<?php

declare(strict_types=1);

namespace App\Helpers;

final class View
{
    public static function render(string $view, array $data = []): void
    {
        $viewFile = APP_PATH . '/Views/' . str_replace('.', '/', $view) . '.php';
        if (!file_exists($viewFile)) {
            throw new \RuntimeException('View not found: ' . $view);
        }
        extract($data, EXTR_SKIP);
        // Make flash available
        $flash = $_SESSION['_flash'] ?? [];
        // Clear flash after reading
        unset($_SESSION['_flash']);
        // Also expose old input
        $old = $_SESSION['_old'] ?? [];
        unset($_SESSION['_old']);

        // Provide helper for layout
        ob_start();
        require $viewFile;
        $content = ob_get_clean();
        // If view already includes layout, just echo; else if layout var set, wrap
        // For simplicity, views are full pages including layout include
        echo $content;
    }

    public static function partial(string $partial, array $data = []): void
    {
        $file = APP_PATH . '/Views/' . str_replace('.', '/', $partial) . '.php';
        if (!file_exists($file)) {
            throw new \RuntimeException('Partial not found: ' . $partial);
        }
        extract($data, EXTR_SKIP);
        require $file;
    }
}

<?php

declare(strict_types=1);

if (!function_exists('e')) {
    /**
     * Escape HTML output. Context-appropriate for HTML body/attributes.
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        static $configs = null;
        if ($configs === null) {
            $configs = [
                'app' => file_exists(CONFIG_PATH . '/app.php') ? require CONFIG_PATH . '/app.php' : [],
                'database' => file_exists(CONFIG_PATH . '/database.php') ? require CONFIG_PATH . '/database.php' : [],
                'session' => file_exists(CONFIG_PATH . '/session.php') ? require CONFIG_PATH . '/session.php' : [],
            ];
        }
        $parts = explode('.', $key, 2);
        if (count($parts) === 1) {
            return $configs[$key] ?? $default;
        }
        return $configs[$parts[0]][$parts[1]] ?? $default;
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return rtrim(BASE_PATH, '/\\') . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return STORAGE_PATH . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim(config('app.url', 'http://localhost'), '/');
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path, int $status = 302): never
    {
        header('Location: ' . $path, true, $status);
        exit;
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['_old'][$key] ?? $default;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return \App\Security\Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}

<?php

declare(strict_types=1);

namespace App\Middleware;

final class RbacMiddleware
{
    /**
     * @param string|array $roles  e.g. 'admin' or ['admin']
     * @param string|array $permissions e.g. 'users.view'
     */
    public function __construct(
        private readonly string|array $roles = [],
        private readonly string|array $permissions = []
    ) {}

    public function handle(): void
    {
        // Must be authenticated
        if (empty($_SESSION['user_id'])) {
            header('Location: /login', true, 302);
            exit;
        }

        $userRoles = $_SESSION['user_roles'] ?? [];
        $userPerms = $_SESSION['user_permissions'] ?? [];

        if ($this->roles !== [] ) {
            $required = (array) $this->roles;
            $has = array_intersect($required, $userRoles);
            if ($has === []) {
                http_response_code(403);
                if (file_exists(APP_PATH . '/Views/errors/403.php')) {
                    require APP_PATH . '/Views/errors/403.php';
                } else {
                    echo '<h1>403 Forbidden</h1><p>Insufficient role.</p>';
                }
                exit;
            }
        }

        if ($this->permissions !== [] ) {
            $required = (array) $this->permissions;
            $has = array_intersect($required, $userPerms);
            if ($has === []) {
                http_response_code(403);
                if (file_exists(APP_PATH . '/Views/errors/403.php')) {
                    require APP_PATH . '/Views/errors/403.php';
                } else {
                    echo '<h1>403 Forbidden</h1><p>Insufficient permission.</p>';
                }
                exit;
            }
        }
    }

    /** Helper for route definitions */
    public static function requireRole(string|array $roles): self
    {
        return new self(roles: $roles);
    }

    public static function requirePermission(string|array $perms): self
    {
        return new self(permissions: $perms);
    }
}

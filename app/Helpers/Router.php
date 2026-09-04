<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Middleware\CsrfMiddleware;

/**
 * Simple router — GET/POST with middleware and named params {id}.
 */
final class Router
{
    /** @var array<string, array<int, array{pattern:string,regex:string,handler:mixed,middleware:array}>> */
    private array $routes = [];

    private array $globalMiddleware = [];

    public function get(string $pattern, mixed $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, mixed $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    public function add(string $method, string $pattern, mixed $handler, array $middleware = []): void
    {
        $method = strtoupper($method);
        $regex = $this->patternToRegex($pattern);
        $this->routes[$method][] = [
            'pattern' => $pattern,
            'regex' => $regex,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    private function patternToRegex(string $pattern): string
    {
        // Escape, then convert {param} to named capture
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        return '#^' . $regex . '$#';
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        // Normalize: remove trailing slash except root, and strip base if app is in subfolder
        // Detect base path from APP_URL if needed; simplest: remove /freehost-manager/public prefix if present
        $basePrefix = '/freehost-manager/public';
        if (str_starts_with($path, $basePrefix)) {
            $path = substr($path, strlen($basePrefix));
            if ($path === '') {
                $path = '/';
            }
        }
        // Also handle generic /public prefix removal for flexibility
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
            if ($path === '') {
                $path = '/';
            }
        }

        $candidates = $this->routes[$method] ?? [];
        // Also handle HEAD as GET
        if ($method === 'HEAD' && isset($this->routes['GET'])) {
            $candidates = $this->routes['GET'];
        }

        foreach ($candidates as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Run middleware pipeline
                foreach ($route['middleware'] as $mw) {
                    $this->runMiddleware($mw);
                }

                $handler = $route['handler'];
                if (is_array($handler) && count($handler) === 2) {
                    [$class, $methodName] = $handler;
                    $instance = new $class();
                    $instance->$methodName($params);
                    return;
                }
                if (is_callable($handler)) {
                    $handler($params);
                    return;
                }
                if (is_string($handler) && str_contains($handler, '@')) {
                    [$class, $methodName] = explode('@', $handler, 2);
                    $instance = new $class();
                    $instance->$methodName($params);
                    return;
                }
                throw new \RuntimeException('Invalid route handler');
            }
        }

        http_response_code(404);
        if (file_exists(APP_PATH . '/Views/errors/404.php')) {
            require APP_PATH . '/Views/errors/404.php';
        } else {
            echo '<h1>404 Not Found</h1><p>Page not found: ' . e($path) . '</p>';
        }
    }

    private function runMiddleware(mixed $middleware): void
    {
        if (is_object($middleware)) {
            if (method_exists($middleware, 'handle')) {
                $middleware->handle();
                return;
            }
            if (is_callable($middleware)) {
                $middleware();
                return;
            }
        }
        if (is_callable($middleware)) {
            $middleware();
            return;
        }
        // String: ClassName or ClassName@method
        if (is_string($middleware)) {
            if (str_contains($middleware, '@')) {
                [$class, $method] = explode('@', $middleware, 2);
                (new $class())->$method();
                return;
            }
            // Assume class with handle() method
            if (class_exists($middleware)) {
                $obj = new $middleware();
                if (method_exists($obj, 'handle')) {
                    $obj->handle();
                    return;
                }
            }
        }
        throw new \RuntimeException('Invalid middleware: ' . (is_string($middleware) ? $middleware : (is_object($middleware) ? get_class($middleware) : 'callable')));
    }
}

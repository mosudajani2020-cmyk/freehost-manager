<?php

declare(strict_types=1);

namespace App\Security;

/**
 * PathGuard — prevents directory traversal and ensures customer isolation.
 * Even though Phase 1 has no file manager, this is required for future security tests.
 */
final class PathGuard
{
    private const RESERVED_WINDOWS = ['CON','PRN','AUX','NUL','COM1','COM2','COM3','COM4','COM5','COM6','COM7','COM8','COM9','LPT1','LPT2','LPT3','LPT4','LPT5','LPT6','LPT7','LPT8','LPT9'];

    /**
     * Resolve user-supplied path relative to base. Throws on traversal.
     * @param string $base  Absolute base directory (e.g. storage/hosting/123)
     * @param string $userPath User supplied relative path
     * @return string Absolute resolved path inside base
     * @throws \RuntimeException on traversal attempt
     */
    public static function resolve(string $base, string $userPath): string
    {
        $base = self::normalize($base);
        // Block obvious attacks before processing
        self::assertNoTraversal($userPath);

        // Normalize base: ensure it exists or at least is absolute
        $baseReal = realpath($base) ?: $base;
        $baseReal = self::normalize($baseReal);
        $baseReal = rtrim($baseReal, '/');

        // Join and normalize
        $joined = $baseReal . '/' . ltrim(self::normalize($userPath), '/');
        // Resolve .. and . segments without requiring existence
        $resolved = self::normalizeAndResolve($joined);

        // Prefix check — case-insensitive on Windows
        $isWindows = DIRECTORY_SEPARATOR === '\\' || strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $checkBase = $isWindows ? strtolower($baseReal) : $baseReal;
        $checkResolved = $isWindows ? strtolower($resolved) : $resolved;

        if ($checkResolved !== $checkBase && !str_starts_with($checkResolved . '/', $checkBase . '/')) {
            throw new \RuntimeException('Path traversal detected');
        }

        // If base exists, verify realpath prefix as extra defense
        $realBase = realpath($base);
        if ($realBase !== false) {
            $realBase = self::normalize($realBase);
            // For resolved that exists, check realpath as well
            $realResolvedParent = realpath(dirname($resolved));
            if ($realResolvedParent !== false) {
                $realResolvedParent = self::normalize($realResolvedParent);
                $baseLower = $isWindows ? strtolower($realBase) : $realBase;
                $parentLower = $isWindows ? strtolower($realResolvedParent) : $realResolvedParent;
                if (!str_starts_with($parentLower . '/', $baseLower . '/') && $parentLower !== $baseLower) {
                    throw new \RuntimeException('Path traversal detected (realpath)');
                }
            }
        }

        return $resolved;
    }

    public static function isSafeFilename(string $filename): bool
    {
        if ($filename === '' || strlen($filename) > 255) {
            return false;
        }
        if (str_contains($filename, '/') || str_contains($filename, '\\') || str_contains($filename, "\0")) {
            return false;
        }
        if ($filename === '.' || $filename === '..') {
            return false;
        }
        if (str_starts_with($filename, '.')) {
            return false;
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $filename)) {
            return false;
        }
        if (preg_match('/[<>:\"|?*]/', $filename)) {
            return false;
        }
        // Windows reserved
        $base = strtoupper(explode('.', $filename, 2)[0]);
        if (in_array($base, self::RESERVED_WINDOWS, true)) {
            return false;
        }
        // Allow only safe chars; but permit spaces cautiously -> replace earlier, here allow alnum, dot, dash, underscore
        if (!preg_match('/^[a-zA-Z0-9._\-]+$/', $filename)) {
            return false;
        }
        return true;
    }

    public static function sanitizeFilename(string $filename): string
    {
        // Replace unsafe with underscore, trim
        $filename = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $filename) ?? '_';
        $filename = trim($filename, '._');
        if ($filename === '' || $filename === '.' || $filename === '..') {
            $filename = 'file_' . bin2hex(random_bytes(4));
        }
        // Truncate
        if (strlen($filename) > 200) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $name = substr(pathinfo($filename, PATHINFO_FILENAME), 0, 190);
            $filename = $ext ? $name . '.' . $ext : $name;
        }
        return $filename;
    }

    private static function assertNoTraversal(string $path): void
    {
        if ($path === '') {
            return;
        }
        // Null byte
        if (str_contains($path, "\0") || str_contains($path, '%00') || str_contains($path, '%0d') || str_contains($path, '%0a')) {
            throw new \RuntimeException('Invalid path: null byte');
        }
        // Absolute Windows drive or UNC
        if (preg_match('/^[a-zA-Z]:[\\\\\\/]/', $path) || str_starts_with($path, '\\\\') || str_starts_with($path, '//')) {
            throw new \RuntimeException('Absolute path not allowed');
        }
        // Absolute Unix
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            throw new \RuntimeException('Absolute path not allowed');
        }
        // Encoded traversal — decode once and re-check (double-encode handled iteratively)
        $decoded = $path;
        for ($i = 0; $i < 3; $i++) {
            $prev = $decoded;
            $decoded = rawurldecode($decoded);
            if ($decoded === $prev) {
                break;
            }
            if (str_contains($decoded, '..') || str_contains($decoded, "\0")) {
                throw new \RuntimeException('Encoded traversal detected');
            }
        }
        // Direct .. segments
        $normalized = str_replace('\\', '/', $path);
        $parts = explode('/', $normalized);
        foreach ($parts as $part) {
            if ($part === '..') {
                throw new \RuntimeException('Path traversal segment detected');
            }
        }
        // Suspicious patterns
        if (str_contains($path, '..\\') || str_contains($path, '../')) {
            throw new \RuntimeException('Traversal pattern detected');
        }
    }

    private static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        // Collapse duplicate slashes
        $path = preg_replace('#/+#', '/', $path);
        return $path;
    }

    private static function normalizeAndResolve(string $path): string
    {
        $path = self::normalize($path);
        $drive = '';
        if (preg_match('/^([a-zA-Z]:)(\/.*)?$/', $path, $m)) {
            $drive = $m[1];
            $path = $m[2] ?? '/';
            if ($path === '') {
                $path = '/';
            }
        }
        $isAbsolute = $drive !== '' || str_starts_with($path, '/');
        $parts = explode('/', $path);
        $resolved = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($resolved);
            } else {
                $resolved[] = $part;
            }
        }
        $result = implode('/', $resolved);
        if ($isAbsolute) {
            $result = '/' . $result;
            if ($drive !== '') {
                $result = $drive . $result;
            }
        }
        if ($result === '' || $result === $drive) {
            return $drive . '/';
        }
        // Collapse possible double slashes after drive
        $result = preg_replace('#/+#', '/', $result);
        if ($drive !== '') {
            $result = preg_replace('#^([a-zA-Z]:)/+#', '$1/', $result);
        }
        return $result;
    }
}

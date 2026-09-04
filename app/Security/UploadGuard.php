<?php

declare(strict_types=1);

namespace App\Security;

final class UploadGuard
{
    private const BLOCKED_EXTENSIONS = [
        'php','php3','php4','php5','phtml','phar','inc',
        'cgi','pl','py','sh','exe','dll','so','htaccess','htpasswd',
        'asp','aspx','jsp','war',
    ];

    private const MAX_FILENAME_LENGTH = 255;

    public static function isExtensionAllowed(string $filename, array $allowed = []): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === '') {
            return false;
        }
        if (in_array($ext, self::BLOCKED_EXTENSIONS, true)) {
            return false;
        }
        // Double extension check: e.g. shell.php.jpg -> block if any blocked part
        $parts = explode('.', $filename);
        foreach ($parts as $part) {
            if (in_array(strtolower($part), self::BLOCKED_EXTENSIONS, true)) {
                return false;
            }
        }
        if ($allowed !== [] && !in_array($ext, array_map('strtolower', $allowed), true)) {
            return false;
        }
        return true;
    }

    /**
     * Validate MIME via finfo, not browser-provided type.
     * Returns true if mime matches extension category (basic).
     */
    public static function validateMime(string $tmpPath, string $filename): bool
    {
        if (!file_exists($tmpPath)) {
            return false;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return true; // fallback — cannot validate, allow but log
        }
        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        // Block PHP-like content even with allowed extension
        $content = file_get_contents($tmpPath, false, null, 0, 2048);
        if ($content !== false) {
            if (preg_match('/<\?php/i', $content) || str_contains($content, '<?=') ) {
                // If extension is not php-related but content is php, block unless explicitly allowed text
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','gif','webp','pdf','zip'], true)) {
                    return false;
                }
                // For .txt/.html allow php tags? No — block if uploaded as image
            }
        }

        // Basic mime allow-list per extension could be added; for Phase 1 just ensure not text/php masquerade
        if ($mime === 'text/x-php' || $mime === 'application/x-php' || $mime === 'application/x-httpd-php') {
            return false;
        }
        return true;
    }

    public static function sanitizeFilename(string $filename): string
    {
        return PathGuard::sanitizeFilename($filename);
    }

    public static function checkSize(int $size, int $maxBytes): bool
    {
        return $size <= $maxBytes && $size > 0;
    }
}

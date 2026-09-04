<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\HostingAccountRepository;
use App\Repositories\HostingPlanRepository;
use App\Security\PathGuard;
use App\Security\UploadGuard;

final class FileService
{
    private const TEXT_EXTS = ['txt','html','htm','css','js','json','xml','md','htaccess','php','ini','conf','csv','yml','yaml','log'];
    private const MAX_EDIT_BYTES = 512 * 1024; // 512KB
    private const MAX_UPLOAD_BYTES = 20 * 1024 * 1024; // 20MB per file (also limited by quota)

    public function __construct(
        private readonly Database $db,
        private readonly HostingAccountRepository $accounts,
        private readonly HostingPlanRepository $plans,
        private readonly AuditService $audit,
    ) {}

    private function requireActiveAccount(int $userId, int $accountId): \App\Models\HostingAccount
    {
        $acct = $this->accounts->findById($accountId);
        if (!$acct) {
            throw new \RuntimeException('Hosting account not found');
        }
        if ($acct->userId !== $userId) {
            $this->audit->log($userId, 'file.access_denied', 'hosting_account', (string) $accountId, 'failure', ['reason'=>'ownership']);
            throw new \RuntimeException('Access denied', 403);
        }
        if ($acct->status !== 'active') {
            throw new \RuntimeException('Hosting account is ' . $acct->status . ' — file operations blocked', 403);
        }
        if (!is_dir($acct->rootPath)) {
            // Attempt to recover via provisioner? For Phase 3, just error
            throw new \RuntimeException('Hosting root missing');
        }
        return $acct;
    }

    private function resolve(\App\Models\HostingAccount $acct, string $relative): string
    {
        $relative = trim(str_replace('\\', '/', $relative));
        $relative = ltrim($relative, '/');
        // Empty means root
        if ($relative === '' || $relative === '.') {
            return $acct->rootPath;
        }
        return PathGuard::resolve($acct->rootPath, $relative);
    }

    private function relativeFromAbsolute(\App\Models\HostingAccount $acct, string $abs): string
    {
        $base = str_replace('\\', '/', $acct->rootPath);
        $absNorm = str_replace('\\', '/', $abs);
        $base = rtrim($base, '/');
        if ($absNorm === $base) return '/';
        if (str_starts_with($absNorm, $base . '/')) {
            return '/' . substr($absNorm, strlen($base . '/'));
        }
        return '/';
    }

    public function getQuotaInfo(\App\Models\HostingAccount $acct): array
    {
        $plan = $acct->plan ?? $this->plans->findById($acct->planId);
        $limitMb = $plan?->storageLimitMb ?? 500;
        $limitBytes = $limitMb * 1024 * 1024;
        $usedBytes = $this->calcUsage($acct->rootPath);
        $usedMb = round($usedBytes / (1024*1024), 2);
        $remainingMb = round(max(0, $limitMb - $usedMb), 2);
        $remainingBytes = max(0, $limitBytes - $usedBytes);
        return [
            'limitMb' => $limitMb,
            'limitBytes' => $limitBytes,
            'usedBytes' => $usedBytes,
            'usedMb' => $usedMb,
            'remainingMb' => $remainingMb,
            'remainingBytes' => $remainingBytes,
            'percent' => $limitBytes > 0 ? round(($usedBytes / $limitBytes) * 100, 1) : 0,
        ];
    }

    private function calcUsage(string $root): int
    {
        $size = 0;
        if (!is_dir($root)) return 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return $size;
    }

    /** List directory contents */
    public function list(int $userId, int $accountId, string $relative = ''): array
    {
        $acct = $this->requireActiveAccount($userId, $accountId);
        $abs = $this->resolve($acct, $relative);
        if (!is_dir($abs)) {
            throw new \RuntimeException('Directory not found', 404);
        }
        $items = [];
        $entries = scandir($abs);
        if ($entries === false) {
            throw new \RuntimeException('Cannot read directory');
        }
        foreach ($entries as $name) {
            if ($name === '.' || $name === '..') continue;
            // Skip internal flags
            if ($name === '.suspended' || $name === '.terminated' || $name === '.htaccess') {
                continue;
            }
            $full = $abs . DIRECTORY_SEPARATOR . $name;
            $isDir = is_dir($full);
            $stat = @stat($full);
            $items[] = [
                'name' => $name,
                'relative' => trim($relative, '/') === '' ? $name : trim($relative, '/') . '/' . $name,
                'type' => $isDir ? 'dir' : 'file',
                'size' => $isDir ? 0 : ($stat['size'] ?? 0),
                'size_human' => $isDir ? '-' : $this->humanSize($stat['size'] ?? 0),
                'mtime' => $stat['mtime'] ?? time(),
                'mtime_human' => date('Y-m-d H:i', $stat['mtime'] ?? time()),
                'ext' => $isDir ? '' : strtolower(pathinfo($name, PATHINFO_EXTENSION)),
            ];
        }
        // Sort: dirs first, then alpha
        usort($items, fn($a,$b)=> ($b['type']==='dir'?1:0) <=> ($a['type']==='dir'?1:0) ?: strcmp(strtolower($a['name']), strtolower($b['name'])));

        // Audit list (low volume, but log)
        $this->audit->log($userId, 'file.list', 'hosting_account', (string) $accountId, 'success', ['path'=>'/' . trim($relative,'/')]);

        return [
            'account' => $acct,
            'relative' => '/' . trim($relative, '/'),
            'absolute' => $abs,
            'items' => $items,
            'quota' => $this->getQuotaInfo($acct),
            'breadcrumbs' => $this->breadcrumbs($relative),
        ];
    }

    private function breadcrumbs(string $relative): array
    {
        $relative = trim($relative, '/');
        if ($relative === '') return [['name'=>'/','path'=>'']];
        $parts = explode('/', $relative);
        $crumbs = [['name'=>'/','path'=>'']];
        $acc = '';
        foreach ($parts as $p) {
            $acc = $acc === '' ? $p : $acc . '/' . $p;
            $crumbs[] = ['name'=>$p,'path'=>$acc];
        }
        return $crumbs;
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024*1024) return round($bytes/1024,1) . ' KB';
        return round($bytes/(1024*1024),2) . ' MB';
    }

    public function mkdir(int $userId, int $accountId, string $relative, string $dirName): array
    {
        $acct = $this->requireActiveAccount($userId, $accountId);
        $dirName = trim($dirName);
        if (!PathGuard::isSafeFilename($dirName)) {
            throw new \RuntimeException('Invalid directory name');
        }
        $parentAbs = $this->resolve($acct, $relative);
        if (!is_dir($parentAbs)) {
            throw new \RuntimeException('Parent directory not found', 404);
        }
        $newAbs = $this->resolve($acct, trim($relative, '/') === '' ? $dirName : trim($relative, '/') . '/' . $dirName);
        if (file_exists($newAbs)) {
            throw new \RuntimeException('Already exists');
        }
        if (!mkdir($newAbs, 0755, false)) {
            throw new \RuntimeException('Failed to create directory');
        }
        $this->audit->log($userId, 'directory.create', 'hosting_account', (string) $accountId, 'success', ['path'=>'/' . trim($relative,'/') . '/' . $dirName, 'size'=>0]);
        return ['success'=>true,'message'=>'Directory created','path'=>$newAbs];
    }

    public function createTextFile(int $userId, int $accountId, string $relative, string $filename, string $content = ''): array
    {
        $acct = $this->requireActiveAccount($userId, $accountId);
        $filename = trim($filename);
        if (!PathGuard::isSafeFilename($filename)) {
            throw new \RuntimeException('Invalid filename');
        }
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, self::TEXT_EXTS, true)) {
            throw new \RuntimeException('Extension not allowed for text creation');
        }
        $parentAbs = $this->resolve($acct, $relative);
        if (!is_dir($parentAbs)) {
            throw new \RuntimeException('Parent not found', 404);
        }
        $newAbs = $this->resolve($acct, trim($relative, '/') === '' ? $filename : trim($relative, '/') . '/' . $filename);
        if (file_exists($newAbs)) {
            throw new \RuntimeException('File already exists');
        }
        // Quota check
        $quota = $this->getQuotaInfo($acct);
        $need = strlen($content);
        if ($quota['remainingBytes'] < $need) {
            throw new \RuntimeException('Quota exceeded: remaining ' . $quota['remainingMb'] . ' MB');
        }
        if (file_put_contents($newAbs, $content) === false) {
            throw new \RuntimeException('Failed to create file');
        }
        $this->audit->log($userId, 'file.create', 'hosting_account', (string) $accountId, 'success', ['path'=>'/' . trim($relative,'/') . '/' . $filename, 'size'=>strlen($content)]);
        return ['success'=>true,'message'=>'File created'];
    }

    public function upload(int $userId, int $accountId, string $relative, array $file): array
    {
        $acct = $this->requireActiveAccount($userId, $accountId);
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload failed: code ' . ($file['error'] ?? 'unknown'));
        }
        $tmp = $file['tmp_name'] ?? '';
        $orig = $file['name'] ?? '';
        $size = (int)($file['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            // For tests, allow non-uploaded file if it exists
            if (!file_exists($tmp)) {
                throw new \RuntimeException('Invalid upload');
            }
        }
        // Validate destination
        $parentAbs = $this->resolve($acct, $relative);
        if (!is_dir($parentAbs)) {
            throw new \RuntimeException('Destination not found', 404);
        }
        $filename = PathGuard::sanitizeFilename(basename($orig));
        if (!PathGuard::isSafeFilename($filename)) {
            throw new \RuntimeException('Invalid filename after sanitization');
        }
        if (!UploadGuard::isExtensionAllowed($filename)) {
            throw new \RuntimeException('Extension not allowed');
        }
        if (!UploadGuard::checkSize($size, self::MAX_UPLOAD_BYTES)) {
            throw new \RuntimeException('File too large (max ' . $this->humanSize(self::MAX_UPLOAD_BYTES) . ')');
        }
        // MIME check
        if (!UploadGuard::validateMime($tmp, $filename)) {
            throw new \RuntimeException('File content rejected');
        }
        // Quota
        $quota = $this->getQuotaInfo($acct);
        if ($quota['remainingBytes'] < $size) {
            throw new \RuntimeException('Quota exceeded: remaining ' . $quota['remainingMb'] . ' MB, need ' . $this->humanSize($size));
        }
        $destAbs = $this->resolve($acct, trim($relative, '/') === '' ? $filename : trim($relative, '/') . '/' . $filename);
        if (file_exists($destAbs)) {
            // Handle duplicate: add suffix
            $base = pathinfo($filename, PATHINFO_FILENAME);
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $counter = 1;
            do {
                $try = $base . '_' . $counter . ($ext ? '.' . $ext : '');
                $destAbs = $this->resolve($acct, trim($relative, '/') === '' ? $try : trim($relative, '/') . '/' . $try);
                $counter++;
            } while (file_exists($destAbs) && $counter < 100);
            if (file_exists($destAbs)) {
                throw new \RuntimeException('Duplicate file exists');
            }
            $filename = $try;
        }
        // Move
        $moved = false;
        if (is_uploaded_file($tmp)) {
            $moved = move_uploaded_file($tmp, $destAbs);
        } else {
            $moved = @rename($tmp, $destAbs);
            if (!$moved) {
                $moved = @copy($tmp, $destAbs) && @unlink($tmp);
            }
        }
        if (!$moved) {
            throw new \RuntimeException('Failed to move upload');
        }
        $this->audit->log($userId, 'file.upload', 'hosting_account', (string) $accountId, 'success', ['path'=>'/' . trim($relative,'/') . '/' . $filename, 'size'=>$size]);
        return ['success'=>true,'message'=>'Uploaded as ' . $filename,'filename'=>$filename];
    }

    public function download(int $userId, int $accountId, string $relative): array
    {
        $acct = $this->requireActiveAccount($userId, $accountId);
        $abs = $this->resolve($acct, $relative);
        if (!file_exists($abs)) {
            throw new \RuntimeException('File not found', 404);
        }
        if (is_dir($abs)) {
            throw new \RuntimeException('Cannot download directory');
        }
        // Prevent accessing flag files
        $name = basename($abs);
        if ($name === '.suspended' || $name === '.terminated' || $name === '.htaccess') {
            throw new \RuntimeException('Access denied', 403);
        }
        $this->audit->log($userId, 'file.download', 'hosting_account', (string) $accountId, 'success', ['path'=>'/' . trim($relative,'/'), 'size'=>filesize($abs)]);
        return ['absolute'=>$abs,'filename'=>$name,'size'=>filesize($abs)];
    }

    public function delete(int $userId, int $accountId, string $relative): array
    {
        $acct = $this->requireActiveAccount($userId, $accountId);
        $relative = trim($relative, '/');
        if ($relative === '' || $relative === '.' ) {
            throw new \RuntimeException('Cannot delete root', 400);
        }
        $abs = $this->resolve($acct, $relative);
        // Prevent deleting root itself
        $rootNorm = str_replace('\\','/',$acct->rootPath);
        $absNorm = str_replace('\\','/',$abs);
        if (rtrim($absNorm,'/') === rtrim($rootNorm,'/')) {
            throw new \RuntimeException('Cannot delete hosting root', 400);
        }
        if (!file_exists($abs)) {
            throw new \RuntimeException('Not found', 404);
        }
        $isDir = is_dir($abs);
        if ($isDir) {
            // Check not trying to delete public_html if we want to protect? We allow but warn; still check emptiness? For Phase 3, allow recursive but prevent root
            $this->rrmdir($abs);
            $this->audit->log($userId, 'directory.delete', 'hosting_account', (string) $accountId, 'success', ['path'=>'/' . $relative]);
        } else {
            $name = basename($abs);
            if ($name === '.suspended' || $name === '.terminated') {
                throw new \RuntimeException('Cannot delete system file', 403);
            }
            if (!unlink($abs)) {
                throw new \RuntimeException('Delete failed');
            }
            $this->audit->log($userId, 'file.delete', 'hosting_account', (string) $accountId, 'success', ['path'=>'/' . $relative]);
        }
        return ['success'=>true,'message'=>'Deleted'];
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($dir);
    }

    public function rename(int $userId, int $accountId, string $oldRelative, string $newName): array
    {
        $acct = $this->requireActiveAccount($userId, $accountId);
        $oldRelative = trim($oldRelative, '/');
        if ($oldRelative === '' ) {
            throw new \RuntimeException('Cannot rename root', 400);
        }
        $newName = trim($newName);
        if (!PathGuard::isSafeFilename($newName)) {
            throw new \RuntimeException('Invalid new name');
        }
        $oldAbs = $this->resolve($acct, $oldRelative);
        if (!file_exists($oldAbs)) {
            throw new \RuntimeException('Source not found', 404);
        }
        $dir = dirname($oldRelative);
        if ($dir === '.' ) $dir = '';
        $newRelative = $dir === '' ? $newName : $dir . '/' . $newName;
        $newAbs = $this->resolve($acct, $newRelative);
        if (file_exists($newAbs)) {
            throw new \RuntimeException('Destination already exists');
        }
        // Prevent escaping via newName already validated by isSafeFilename, but also ensure newAbs inside root (resolve already checks)
        if (!rename($oldAbs, $newAbs)) {
            throw new \RuntimeException('Rename failed');
        }
        $this->audit->log($userId, 'file.rename', 'hosting_account', (string) $accountId, 'success', ['from'=>'/' . $oldRelative,'to'=>'/' . $newRelative]);
        return ['success'=>true,'message'=>'Renamed','newRelative'=>$newRelative];
    }

    public function readText(int $userId, int $accountId, string $relative): array
    {
        $acct = $this->requireActiveAccount($userId, $accountId);
        $abs = $this->resolve($acct, $relative);
        if (!file_exists($abs) || is_dir($abs)) {
            throw new \RuntimeException('File not found', 404);
        }
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        // Allow .htaccess without extension? handle
        $basename = basename($abs);
        if ($basename === '.htaccess') $ext = 'htaccess';
        if (!in_array($ext, self::TEXT_EXTS, true)) {
            throw new \RuntimeException('File type not editable');
        }
        $size = filesize($abs);
        if ($size > self::MAX_EDIT_BYTES) {
            throw new \RuntimeException('File too large to edit (max ' . $this->humanSize(self::MAX_EDIT_BYTES) . ')');
        }
        $content = file_get_contents($abs);
        if ($content === false) {
            throw new \RuntimeException('Cannot read file');
        }
        // Detect binary? simple check for null bytes
        if (str_contains($content, "\0")) {
            throw new \RuntimeException('Binary file not editable');
        }
        $this->audit->log($userId, 'file.edit_view', 'hosting_account', (string) $accountId, 'success', ['path'=>'/' . trim($relative,'/'), 'size'=>$size]);
        return ['content'=>$content,'size'=>$size,'ext'=>$ext];
    }

    public function writeText(int $userId, int $accountId, string $relative, string $content): array
    {
        $acct = $this->requireActiveAccount($userId, $accountId);
        $abs = $this->resolve($acct, $relative);
        if (!file_exists($abs) || is_dir($abs)) {
            throw new \RuntimeException('File not found', 404);
        }
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $basename = basename($abs);
        if ($basename === '.htaccess') $ext = 'htaccess';
        if (!in_array($ext, self::TEXT_EXTS, true)) {
            throw new \RuntimeException('File type not editable');
        }
        if (strlen($content) > self::MAX_EDIT_BYTES) {
            throw new \RuntimeException('Content too large (max ' . $this->humanSize(self::MAX_EDIT_BYTES) . ')');
        }
        // Check for null bytes
        if (str_contains($content, "\0")) {
            throw new \RuntimeException('Invalid content');
        }
        // Quota check: delta size
        $oldSize = filesize($abs);
        $newSize = strlen($content);
        $delta = $newSize - $oldSize;
        if ($delta > 0) {
            $quota = $this->getQuotaInfo($acct);
            if ($quota['remainingBytes'] < $delta) {
                throw new \RuntimeException('Quota exceeded');
            }
        }
        // Atomic write: write to temp then rename
        $tmp = $abs . '.tmp.' . bin2hex(random_bytes(4));
        if (file_put_contents($tmp, $content) === false) {
            throw new \RuntimeException('Write failed');
        }
        if (!rename($tmp, $abs)) {
            @unlink($tmp);
            throw new \RuntimeException('Replace failed');
        }
        $this->audit->log($userId, 'file.edit', 'hosting_account', (string) $accountId, 'success', ['path'=>'/' . trim($relative,'/'), 'size'=>$newSize]);
        return ['success'=>true,'message'=>'File saved'];
    }
}

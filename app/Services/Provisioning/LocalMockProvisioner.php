<?php

declare(strict_types=1);

namespace App\Services\Provisioning;

use App\Models\HostingAccount;

final class LocalMockProvisioner implements HostingProvisionerInterface
{
    private function storageBase(): string
    {
        if (defined('STORAGE_PATH')) {
            return STORAGE_PATH;
        }
        return dirname(__DIR__, 3) . '/storage';
    }

    public function createHostingAccount(HostingAccount $account): ProvisionResult
    {
        // Safe local directory structure only — no shell
        $root = $account->rootPath;
        if ($root === '' || str_contains($root, '..') || str_contains($root, "\0")) {
            return ProvisionResult::fail('Invalid root path');
        }
        // Ensure root is under storage/hosting
        $storageHosting = rtrim($this->storageBase() . '/hosting', '/\\');
        $normalizedRoot = str_replace('\\', '/', $root);
        $normalizedBase = str_replace('\\', '/', $storageHosting);
        if (!str_starts_with($normalizedRoot, $normalizedBase . '/') && $normalizedRoot !== $normalizedBase) {
            return ProvisionResult::fail('Root path outside allowed base');
        }

        if (!is_dir($root)) {
            if (!mkdir($root, 0755, true) && !is_dir($root)) {
                return ProvisionResult::fail('Failed to create hosting directory');
            }
        }
        $publicHtml = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'public_html';
        if (!is_dir($publicHtml)) {
            if (!mkdir($publicHtml, 0755, true) && !is_dir($publicHtml)) {
                return ProvisionResult::fail('Failed to create public_html');
            }
        }
        // Create default index if not exists — do not overwrite
        $index = $publicHtml . DIRECTORY_SEPARATOR . 'index.php';
        if (!file_exists($index)) {
            $content = "<?php\n// FreeHost Manager — Customer site placeholder for account {$account->id}\n// This file is isolated from control panel origin.\necho 'Welcome to hosting account {$account->username}';\n";
            @file_put_contents($index, $content);
        }
        // Deny PHP execution in storage/hosting via .htaccess already, but ensure .htaccess exists
        $ht = $root . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($ht)) {
            @file_put_contents($ht, "Require all denied\n");
        }
        // Log for audit (file log, not secrets)
        error_log("[Provision] createHostingAccount id={$account->id} root={$root}");

        return ProvisionResult::ok('Hosting directory created', ['root' => $root]);
    }

    public function suspendHostingAccount(HostingAccount $account): ProvisionResult
    {
        // Local mock: create suspended flag file, do not delete data
        $flag = rtrim($account->rootPath, '/\\') . DIRECTORY_SEPARATOR . '.suspended';
        @file_put_contents($flag, date('c') . " suspended\n");
        error_log("[Provision] suspendHostingAccount id={$account->id}");
        return ProvisionResult::ok('Account suspended (local mock)');
    }

    public function activateHostingAccount(HostingAccount $account): ProvisionResult
    {
        $flag = rtrim($account->rootPath, '/\\') . DIRECTORY_SEPARATOR . '.suspended';
        if (file_exists($flag)) {
            @unlink($flag);
        }
        error_log("[Provision] activateHostingAccount id={$account->id}");
        return ProvisionResult::ok('Account activated (local mock)');
    }

    public function terminateHostingAccount(HostingAccount $account): ProvisionResult
    {
        // Local mock: create terminated flag, keep data for audit until purge (not in Phase 2)
        $flag = rtrim($account->rootPath, '/\\') . DIRECTORY_SEPARATOR . '.terminated';
        @file_put_contents($flag, date('c') . " terminated\n");
        // Also suspend flag
        $suspendFlag = rtrim($account->rootPath, '/\\') . DIRECTORY_SEPARATOR . '.suspended';
        if (file_exists($suspendFlag)) {
            @unlink($suspendFlag);
        }
        error_log("[Provision] terminateHostingAccount id={$account->id}");
        return ProvisionResult::ok('Account terminated (local mock, data retained)');
    }

    public function createSubdomain(HostingAccount $account, string $subdomain, string $fullDomain): ProvisionResult
    {
        // Local mock: create subdomain directory under public_html
        $subRoot = rtrim($account->rootPath, '/\\') . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . $subdomain;
        if (!is_dir($subRoot)) {
            if (!mkdir($subRoot, 0755, true) && !is_dir($subRoot)) {
                return ProvisionResult::fail('Failed to create subdomain directory');
            }
        }
        $index = $subRoot . DIRECTORY_SEPARATOR . 'index.php';
        if (!file_exists($index)) {
            @file_put_contents($index, "<?php echo 'Subdomain {$fullDomain} placeholder';");
        }
        error_log("[Provision] createSubdomain id={$account->id} {$fullDomain}");
        return ProvisionResult::ok('Subdomain created', ['fullDomain' => $fullDomain]);
    }

    public function deleteSubdomain(HostingAccount $account, string $fullDomain): ProvisionResult
    {
        // Local mock: keep directory for safety, just log
        error_log("[Provision] deleteSubdomain id={$account->id} {$fullDomain}");
        return ProvisionResult::ok('Subdomain deleted (mock)');
    }

    public function createDatabase(HostingAccount $account, string $dbName): ProvisionResult
    {
        // Mock: no real MySQL CREATE DATABASE (would require elevated privileges)
        // Validate name already done in service; just log
        error_log("[Provision] createDatabase id={$account->id} db={$dbName} (mock)");
        return ProvisionResult::ok('Database created (mock)', ['dbName'=>$dbName]);
    }

    public function deleteDatabase(HostingAccount $account, string $dbName): ProvisionResult
    {
        error_log("[Provision] deleteDatabase id={$account->id} db={$dbName} (mock)");
        return ProvisionResult::ok('Database deleted (mock)');
    }

    public function createDatabaseUser(HostingAccount $account, string $dbUsername, string $password): ProvisionResult
    {
        // Mock: do not create real MySQL user; password is not logged
        error_log("[Provision] createDatabaseUser id={$account->id} user={$dbUsername} (mock, password not logged)");
        return ProvisionResult::ok('Database user created (mock)', ['username'=>$dbUsername]);
    }

    public function deleteDatabaseUser(HostingAccount $account, string $dbUsername): ProvisionResult
    {
        error_log("[Provision] deleteDatabaseUser id={$account->id} user={$dbUsername} (mock)");
        return ProvisionResult::ok('Database user deleted (mock)');
    }
}

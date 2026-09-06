<?php

declare(strict_types=1);

namespace App\Services\Isolation;

/**
 * PhpFpmPoolConfigGenerator — secure structured PHP-FPM pool configuration generator (P2).
 *
 * Generates valid per-customer PHP-FPM pool definitions using strictly validated
 * and predefined parameters. Prevents arbitrary configuration directive injection
 * and ensures isolated execution identities.
 */
final class PhpFpmPoolConfigGenerator
{
    public static function generate(CustomerSystemIdentity $identity, HostingFilesystemLayout $layout, ResourceIsolationPolicy $policy): string
    {
        $username = $identity->username();
        $group = $identity->groupName();
        $customerRoot = $layout->getCustomerRoot($identity->accountId());
        $socketPath = "/run/php/php8.3-fpm-{$username}.sock";
        $logPath = $layout->getLogsDirectory($identity->accountId()) . '/php-fpm.log';

        // Structured configuration template — no arbitrary user input allowed
        return <<<INI
[$username]
user = $username
group = $group
listen = $socketPath
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 20
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500

php_admin_value[sendmail_path] = /usr/sbin/sendmail -t -i -f webmaster@$username.local
php_admin_value[memory_limit] = {$policy->memoryLimitMb}M
php_admin_flag[display_errors] = off
php_admin_value[error_log] = $logPath
php_admin_value[upload_max_filesize] = {$policy->uploadMaxMb}M
php_admin_value[post_max_size] = {$policy->uploadMaxMb}M
php_admin_value[max_execution_time] = {$policy->maxExecutionTime}
php_admin_value[open_basedir] = $customerRoot/:/tmp/
clear_env = yes
INI;
    }
}
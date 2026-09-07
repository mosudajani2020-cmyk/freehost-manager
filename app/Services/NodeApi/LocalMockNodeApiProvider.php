<?php

declare(strict_types=1);

namespace App\Services\NodeApi;

use App\Services\Isolation\CustomerSystemIdentity;
use App\Services\Isolation\HostingFilesystemLayout;
use App\Services\Isolation\ResourceIsolationPolicy;
use App\Services\Isolation\PhpFpmPoolConfigGenerator;

final class LocalMockNodeApiProvider implements HostingNodeApiInterface
{
    public function createIdentity(array $payload): array
    {
        $accountId = (int)($payload['account_id'] ?? 0);
        $id = new CustomerSystemIdentity($accountId);
        return ['status'=>'ok','operation'=>'hosting.create_identity','identity'=>['username'=>$id->username(),'group'=>$id->groupName(),'uid'=>$id->uid(),'gid'=>$id->gid()]];
    }
    public function createFilesystem(array $payload): array
    {
        $accountId = (int)($payload['account_id'] ?? 0);
        $layout = new HostingFilesystemLayout();
        return ['status'=>'ok','operation'=>'hosting.create_filesystem','paths'=>['root'=>$layout->getCustomerRoot($accountId),'public_html'=>$layout->getPublicHtml($accountId)]];
    }
    public function configurePhpFpm(array $payload): array
    {
        $accountId = (int)($payload['account_id'] ?? 0);
        $id = new CustomerSystemIdentity($accountId);
        $layout = new HostingFilesystemLayout();
        $policy = ResourceIsolationPolicy::defaultPolicy();
        $cfg = PhpFpmPoolConfigGenerator::generate($id, $layout, $policy);
        // normalize line endings
        $cfg = str_replace("\r\n", "\n", $cfg);
        return ['status'=>'ok','operation'=>'hosting.configure_php_fpm','pool'=>$id->username(),'config_preview'=>substr($cfg,0,200)];
    }
    public function applyResourcePolicy(array $payload): array
    {
        $accountId = (int)($payload['account_id'] ?? 0);
        $policy = ResourceIsolationPolicy::defaultPolicy();
        return ['status'=>'ok','operation'=>'hosting.apply_resource_policy','account_id'=>$accountId,'policy'=>$policy->toArray()];
    }
    public function suspend(array $payload): array { return ['status'=>'ok','operation'=>'hosting.suspend','account_id'=>(int)($payload['account_id']??0)]; }
    public function unsuspend(array $payload): array { return ['status'=>'ok','operation'=>'hosting.unsuspend','account_id'=>(int)($payload['account_id']??0)]; }
    public function terminate(array $payload): array { return ['status'=>'ok','operation'=>'hosting.terminate','account_id'=>(int)($payload['account_id']??0)]; }
}

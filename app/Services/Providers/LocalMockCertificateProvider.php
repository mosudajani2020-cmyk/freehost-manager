<?php

declare(strict_types=1);

namespace App\Services\Providers;

final class LocalMockCertificateProvider implements CertificateProviderInterface
{
    public function requestCertificate(string $hostname): array
    {
        error_log("[MockCert] request {$hostname}");
        if (str_contains($hostname, 'fail')) {
            return ['success'=>false,'status'=>'failed','expires_at'=>null,'message'=>'Simulated failure'];
        }
        $expires = date('Y-m-d H:i:s', time() + 90*24*3600);
        return ['success'=>true,'status'=>'active','expires_at'=>$expires,'message'=>'Certificate issued (mock)'];
    }

    public function renewCertificate(string $hostname): array
    {
        error_log("[MockCert] renew {$hostname}");
        $expires = date('Y-m-d H:i:s', time() + 90*24*3600);
        return ['success'=>true,'status'=>'active','expires_at'=>$expires];
    }

    public function revokeCertificate(string $hostname): array
    {
        error_log("[MockCert] revoke {$hostname}");
        return ['success'=>true,'status'=>'revoked'];
    }

    public function getStatus(string $hostname): array
    {
        return ['status'=>'active','expires_at'=>date('Y-m-d H:i:s', time()+ 60*24*3600)];
    }
}

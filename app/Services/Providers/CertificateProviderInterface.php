<?php

declare(strict_types=1);

namespace App\Services\Providers;

interface CertificateProviderInterface
{
    public function requestCertificate(string $hostname): array; // ['success'=>bool,'status'=>string,'expires_at'=>?string]
    public function renewCertificate(string $hostname): array;
    public function revokeCertificate(string $hostname): array;
    public function getStatus(string $hostname): array;
}

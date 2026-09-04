<?php

declare(strict_types=1);

namespace App\Services\Provisioning;

final class ProvisionResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message = '',
        public readonly array $data = []
    ) {}

    public static function ok(string $message = 'OK', array $data = []): self
    {
        return new self(true, $message, $data);
    }

    public static function fail(string $message, array $data = []): self
    {
        return new self(false, $message, $data);
    }
}

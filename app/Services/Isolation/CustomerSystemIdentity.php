<?php

declare(strict_types=1);

namespace App\Services\Isolation;

use App\Models\HostingAccount;

/**
 * CustomerSystemIdentity — deterministic Linux identity mapping (P2).
 *
 * Maps a FreeHost customer / hosting account to a deterministic, collision-resistant
 * system identity without relying on raw user-supplied usernames or arbitrary input.
 *
 * Pattern: fhm_<hosting_account_id>
 * - Username: fhm_1001
 * - Group: fhm_1001
 * - UID/GID: Derived deterministically (e.g., base 20000 + accountId)
 */
final class CustomerSystemIdentity
{
    private const USER_PREFIX = 'fhm_';
    private const UID_BASE = 20000;

    public function __construct(private readonly int $accountId)
    {
        if ($this->accountId < 1) {
            throw new \InvalidArgumentException('Hosting account ID must be a positive integer.');
        }
    }

    public static function fromAccount(HostingAccount $account): self
    {
        return new self($account->id);
    }

    public static function fromId(int $accountId): self
    {
        return new self($accountId);
    }

    public function accountId(): int
    {
        return $this->accountId;
    }

    public function username(): string
    {
        return self::USER_PREFIX . $this->accountId;
    }

    public function groupName(): string
    {
        return self::USER_PREFIX . $this->accountId;
    }

    public function uid(): int
    {
        return self::UID_BASE + $this->accountId;
    }

    public function gid(): int
    {
        return self::UID_BASE + $this->accountId;
    }

    /**
     * Validate that a username string strictly conforms to the allowed identity pattern
     * and contains no shell injection characters or path traversal.
     */
    public static function validateUsername(string $username): bool
    {
        return (bool) preg_match('/^fhm_[1-9][0-9]*$/', $username);
    }
}
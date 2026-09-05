<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Database;
use App\Models\Subscription;

final class SubscriptionRepository
{
    public function __construct(private readonly Database $db) {}

    public function findById(int $id): ?Subscription
    {
        $r = $this->db->fetch("SELECT * FROM subscriptions WHERE id=?", [$id]);
        return $r ? Subscription::fromArray($r) : null;
    }

    public function findByUser(int $userId, int $limit=20): array
    {
        $rows = $this->db->fetchAll("SELECT * FROM subscriptions WHERE user_id=? ORDER BY id DESC LIMIT ?", [$userId,$limit]);
        return array_map([Subscription::class,'fromArray'], $rows);
    }

    public function findByHosting(int $hostingId): ?Subscription
    {
        $r = $this->db->fetch("SELECT * FROM subscriptions WHERE hosting_account_id=? ORDER BY id DESC LIMIT 1", [$hostingId]);
        return $r ? Subscription::fromArray($r) : null;
    }

    public function all(int $limit=50, int $offset=0): array
    {
        $rows = $this->db->fetchAll("SELECT * FROM subscriptions ORDER BY id DESC LIMIT ? OFFSET ?", [$limit,$offset]);
        return array_map([Subscription::class,'fromArray'], $rows);
    }

    public function count(): int
    {
        return (int)$this->db->fetchColumn("SELECT COUNT(*) FROM subscriptions");
    }

    public function create(int $userId, int $planId, ?int $hostingId, string $status='trial', ?string $trialEndsAt=null): Subscription
    {
        $now = date('Y-m-d H:i:s');
        $periodEnd = date('Y-m-d H:i:s', time() + 30*24*3600);
        $trialEnds = $status === 'trial' ? ($trialEndsAt ?? date('Y-m-d H:i:s', time() + 7*24*3600)) : null;
        $this->db->query("INSERT INTO subscriptions (user_id, hosting_account_id, plan_id, status, trial_ends_at, current_period_start, current_period_end) VALUES (?,?,?,?,?,?,?)", [$userId,$hostingId,$planId,$status,$trialEnds,$now,$periodEnd]);
        return $this->findById((int)$this->db->lastInsertId());
    }

    public function updateStatus(int $id, string $status): void
    {
        $valid = ['trial','active','past_due','cancelled','expired'];
        if (!in_array($status, $valid, true)) throw new \InvalidArgumentException('Invalid status');
        $this->db->execute("UPDATE subscriptions SET status=?, updated_at=NOW() WHERE id=?", [$status,$id]);
    }
}

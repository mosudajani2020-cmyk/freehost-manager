<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Database;
use App\Models\Invoice;

final class InvoiceRepository
{
    public function __construct(private readonly Database $db) {}

    public function findById(int $id): ?Invoice
    {
        $r = $this->db->fetch("SELECT * FROM invoices WHERE id=?", [$id]);
        return $r ? Invoice::fromArray($r) : null;
    }

    public function findByUser(int $userId, int $limit=20): array
    {
        $rows = $this->db->fetchAll("SELECT * FROM invoices WHERE user_id=? ORDER BY id DESC LIMIT ?", [$userId,$limit]);
        return array_map([Invoice::class,'fromArray'], $rows);
    }

    public function findBySubscription(int $subId): array
    {
        $rows = $this->db->fetchAll("SELECT * FROM invoices WHERE subscription_id=? ORDER BY id DESC", [$subId]);
        return array_map([Invoice::class,'fromArray'], $rows);
    }

    public function all(int $limit=50, int $offset=0): array
    {
        $rows = $this->db->fetchAll("SELECT * FROM invoices ORDER BY id DESC LIMIT ? OFFSET ?", [$limit,$offset]);
        return array_map([Invoice::class,'fromArray'], $rows);
    }

    public function count(): int
    {
        return (int)$this->db->fetchColumn("SELECT COUNT(*) FROM invoices");
    }

    public function create(int $userId, ?int $subscriptionId, int $amountCents, string $currency='USD', string $status='pending', ?string $providerRef=null): Invoice
    {
        $due = date('Y-m-d H:i:s', time() + 7*24*3600);
        $url = 'https://pay.mock/' . bin2hex(random_bytes(8));
        $this->db->query("INSERT INTO invoices (user_id, subscription_id, amount_cents, currency, status, due_date, hosted_url, provider_ref) VALUES (?,?,?,?,?,?,?,?)", [$userId,$subscriptionId,$amountCents,$currency,$status,$due,$url,$providerRef ?? 'mock_' . bin2hex(random_bytes(6))]);
        return $this->findById((int)$this->db->lastInsertId());
    }

    public function updateStatus(int $id, string $status, ?string $paidAt=null): void
    {
        $valid = ['pending','successful','failed','cancelled','refunded'];
        if (!in_array($status, $valid, true)) throw new \InvalidArgumentException('Invalid status');
        $sql = "UPDATE invoices SET status=? , updated_at=NOW()";
        $params = [$status];
        if ($status === 'successful' && $paidAt === null) {
            $sql .= ", paid_at=NOW()";
        } elseif ($paidAt !== null) {
            $sql .= ", paid_at=?";
            $params[] = $paidAt;
        }
        $sql .= " WHERE id=?";
        $params[] = $id;
        $this->db->execute($sql, $params);
    }

    public function existsProviderRef(string $ref): bool
    {
        return (bool)$this->db->fetchColumn("SELECT 1 FROM invoices WHERE provider_ref=? LIMIT 1", [$ref]);
    }
}

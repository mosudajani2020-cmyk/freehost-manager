<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\SubscriptionRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\HostingPlanRepository;
use App\Services\Providers\PaymentGatewayInterface;

final class SubscriptionService
{
    public function __construct(
        private readonly Database $db,
        private readonly SubscriptionRepository $subs,
        private readonly InvoiceRepository $invoices,
        private readonly HostingPlanRepository $plans,
        private readonly PaymentGatewayInterface $gateway,
        private readonly AuditService $audit,
    ) {}

    public function createSubscription(int $userId, int $planId, ?int $hostingAccountId = null): array
    {
        $plan = $this->plans->findById($planId);
        if (!$plan) throw new \RuntimeException('Plan not found', 404);
        // FREE plans are trial/active without payment
        $status = $plan->priceCents === 0 ? 'active' : 'trial';
        $sub = $this->subs->create($userId, $planId, $hostingAccountId, $status);
        // For paid plans, create pending invoice
        $invoice = null;
        if ($plan->priceCents > 0) {
            $gatewayRes = $this->gateway->createPayment($plan->priceCents, $plan->currency ?? 'USD', 'Subscription ' . $plan->name, ['subscription_id'=>$sub->id,'user_id'=>$userId]);
            $invoice = $this->invoices->create($userId, $sub->id, $plan->priceCents, $plan->currency ?? 'USD', 'pending', $gatewayRes['provider_ref'] ?? null);
            // Update invoice hosted_url if gateway provided
            if (isset($gatewayRes['hosted_url'])) {
                $this->db->execute("UPDATE invoices SET hosted_url=? WHERE id=?", [$gatewayRes['hosted_url'], $invoice->id]);
            }
        }
        $this->audit->log($userId, 'subscription.create', 'subscription', (string)$sub->id, 'success', ['plan'=>$plan->slug,'status'=>$status]);
        return ['subscription'=>$sub,'invoice'=>$invoice];
    }

    public function updateStatus(int $actorId, int $subscriptionId, string $newStatus): array
    {
        $valid = ['trial','active','past_due','cancelled','expired'];
        if (!in_array($newStatus, $valid, true)) throw new \RuntimeException('Invalid status', 400);
        $sub = $this->subs->findById($subscriptionId);
        if (!$sub) throw new \RuntimeException('Subscription not found', 404);
        // Simple state machine: trial->active, active->past_due/cancelled/expired, past_due->active/cancelled, etc.
        $this->subs->updateStatus($subscriptionId, $newStatus);
        $this->audit->log($actorId, 'subscription.status_update', 'subscription', (string)$subscriptionId, 'success', ['from'=>$sub->status,'to'=>$newStatus]);
        return ['success'=>true,'message'=>'Status updated'];
    }

    public function cancelSubscription(int $userId, int $subscriptionId): array
    {
        $sub = $this->subs->findById($subscriptionId);
        if (!$sub) throw new \RuntimeException('Not found', 404);
        if ($sub->userId !== $userId) {
            // Check admin via session
            $isAdmin = in_array('admin', $_SESSION['user_roles'] ?? [], true);
            if (!$isAdmin) throw new \RuntimeException('Access denied', 403);
        }
        $this->subs->updateStatus($subscriptionId, 'cancelled');
        $this->audit->log($userId, 'subscription.cancel', 'subscription', (string)$subscriptionId, 'success', []);
        return ['success'=>true,'message'=>'Subscription cancelled'];
    }

    public function handlePaymentWebhook(string $payload, string $signature, string $secret): bool
    {
        if (!$this->gateway->verifyWebhook($payload, $signature, $secret)) {
            $this->audit->log(null, 'payment.webhook_failed', 'invoice', '-', 'failure', ['reason'=>'invalid signature']);
            throw new \RuntimeException('Invalid webhook signature', 403);
        }
        $data = json_decode($payload, true);
        if (!$data || !isset($data['provider_ref'], $data['status'])) {
            throw new \RuntimeException('Invalid payload', 400);
        }
        $invoice = $this->db->fetch("SELECT * FROM invoices WHERE provider_ref=?", [$data['provider_ref']]);
        if (!$invoice) throw new \RuntimeException('Invoice not found', 404);
        // Prevent duplicate processing via status check
        if ($invoice['status'] === $data['status']) {
            return true; // idempotent
        }
        $this->invoices->updateStatus((int)$invoice['id'], $data['status']);
        // If successful, activate subscription
        if ($data['status'] === 'successful' && $invoice['subscription_id']) {
            $this->subs->updateStatus((int)$invoice['subscription_id'], 'active');
        } elseif ($data['status'] === 'failed' && $invoice['subscription_id']) {
            $this->subs->updateStatus((int)$invoice['subscription_id'], 'past_due');
        }
        $this->audit->log(null, 'payment.webhook', 'invoice', (string)$invoice['id'], 'success', ['status'=>$data['status']]);
        return true;
    }

    public function updateInvoiceStatus(int $actorId, int $invoiceId, string $newStatus): array
    {
        $valid = ['pending','successful','failed','cancelled','refunded'];
        if (!in_array($newStatus, $valid, true)) throw new \RuntimeException('Invalid status', 400);
        $inv = $this->invoices->findById($invoiceId);
        if (!$inv) throw new \RuntimeException('Invoice not found', 404);
        // Prevent duplicate payment (if already successful, don't allow another successful)
        if ($inv->status === 'successful' && $newStatus === 'successful') {
            throw new \RuntimeException('Invoice already successful', 400);
        }
        // Handle refund via gateway
        if ($newStatus === 'refunded') {
            $res = $this->gateway->refundPayment($inv->providerRef ?? '');
            if (!$res['success']) throw new \RuntimeException('Refund failed', 500);
        }
        $this->invoices->updateStatus($invoiceId, $newStatus);
        if ($newStatus === 'successful' && $inv->subscriptionId) {
            $this->subs->updateStatus($inv->subscriptionId, 'active');
        } elseif ($newStatus === 'failed' && $inv->subscriptionId) {
            $this->subs->updateStatus($inv->subscriptionId, 'past_due');
        }
        $this->audit->log($actorId, 'invoice.status_update', 'invoice', (string)$invoiceId, 'success', ['from'=>$inv->status,'to'=>$newStatus]);
        return ['success'=>true,'message'=>'Invoice updated'];
    }

    public function getUserSubscriptions(int $userId): array
    {
        return $this->subs->findByUser($userId);
    }

    public function getUserInvoices(int $userId): array
    {
        return $this->invoices->findByUser($userId);
    }
}

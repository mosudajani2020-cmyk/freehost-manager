<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\View;
use App\Repositories\SubscriptionRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\HostingPlanRepository;
use App\Services\SubscriptionService;
use App\Services\Providers\LocalMockPaymentGateway;
use App\Services\AuditService;

final class BillingController
{
    private function service(): SubscriptionService
    {
        $db = Database::getInstance();
        return new SubscriptionService($db, new SubscriptionRepository($db), new InvoiceRepository($db), new HostingPlanRepository($db), new LocalMockPaymentGateway(), new AuditService($db));
    }

    public function index(array $params = []): void
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $service = $this->service();
        $subs = $service->getUserSubscriptions($userId);
        $invs = $service->getUserInvoices($userId);
        $db = Database::getInstance();
        $plans = $db->fetchAll("SELECT * FROM hosting_plans WHERE status='active' ORDER BY price_cents ASC");
        View::render('billing.index', ['title'=>'Billing','subscriptions'=>$subs,'invoices'=>$invs,'plans'=>$plans]);
    }

    public function subscribe(array $params = []): void
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $planId = (int)($_POST['plan_id'] ?? 0);
        $hostingId = !empty($_POST['hosting_account_id']) ? (int)$_POST['hosting_account_id'] : null;
        try {
            $res = $this->service()->createSubscription($userId, $planId, $hostingId);
            $_SESSION['_flash'] = ['success'=>'Subscription created: ' . $res['subscription']->status . ($res['invoice'] ? ' — Invoice #' . $res['invoice']->id . ' pending' : ' (free)')];
        } catch (\Throwable $e) {
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
        }
        header('Location: /billing', true, 302);
        exit;
    }

    public function cancel(array $params = []): void
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $subId = (int)($_POST['subscription_id'] ?? 0);
        try {
            $res = $this->service()->cancelSubscription($userId, $subId);
            $_SESSION['_flash'] = ['success'=>$res['message']];
        } catch (\Throwable $e) {
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
        }
        header('Location: /billing', true, 302);
        exit;
    }

    public function webhook(array $params = []): void
    {
        // Mock webhook endpoint - verify signature
        $payload = file_get_contents('php://input');
        $sig = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
        $secret = $_ENV['WEBHOOK_SECRET'] ?? 'test_secret';
        try {
            $this->service()->handlePaymentWebhook($payload, $sig, $secret);
            http_response_code(200);
            echo 'OK';
        } catch (\Throwable $e) {
            http_response_code($e->getCode() ?: 400);
            echo $e->getMessage();
        }
        exit;
    }
}

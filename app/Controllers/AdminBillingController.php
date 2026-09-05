<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\View;
use App\Repositories\SubscriptionRepository;
use App\Repositories\InvoiceRepository;
use App\Services\SubscriptionService;
use App\Services\Providers\LocalMockPaymentGateway;
use App\Repositories\HostingPlanRepository;
use App\Services\AuditService;

final class AdminBillingController
{
    public function subscriptions(array $params = []): void
    {
        $db = Database::getInstance();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per = 20;
        $off = ($page - 1) * $per;
        $rows = $db->fetchAll("SELECT s.*, u.email as user_email, hp.name as plan_name FROM subscriptions s JOIN users u ON u.id=s.user_id JOIN hosting_plans hp ON hp.id=s.plan_id ORDER BY s.id DESC LIMIT ? OFFSET ?", [$per,$off]);
        $total = (int)$db->fetchColumn("SELECT COUNT(*) FROM subscriptions");
        View::render('admin.billing.subscriptions', ['title'=>'Subscriptions','rows'=>$rows,'page'=>$page,'pages'=>max(1,(int)ceil($total/$per)),'total'=>$total]);
    }

    public function invoices(array $params = []): void
    {
        $db = Database::getInstance();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per = 20;
        $off = ($page - 1) * $per;
        $rows = $db->fetchAll("SELECT i.*, u.email as user_email FROM invoices i JOIN users u ON u.id=i.user_id ORDER BY i.id DESC LIMIT ? OFFSET ?", [$per,$off]);
        $total = (int)$db->fetchColumn("SELECT COUNT(*) FROM invoices");
        View::render('admin.billing.invoices', ['title'=>'Invoices','rows'=>$rows,'page'=>$page,'pages'=>max(1,(int)ceil($total/$per)),'total'=>$total]);
    }

    public function updateInvoice(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $db = Database::getInstance();
        $service = new SubscriptionService($db, new SubscriptionRepository($db), new InvoiceRepository($db), new HostingPlanRepository($db), new LocalMockPaymentGateway(), new AuditService($db));
        try {
            $service->updateInvoiceStatus((int)($_SESSION['user_id'] ?? 0), $id, $status);
            $_SESSION['_flash'] = ['success'=>'Invoice updated'];
        } catch (\Throwable $e) {
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
        }
        header('Location: /admin/billing/invoices', true, 302);
        exit;
    }

    public function updateSubscription(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $db = Database::getInstance();
        $service = new SubscriptionService($db, new SubscriptionRepository($db), new InvoiceRepository($db), new HostingPlanRepository($db), new LocalMockPaymentGateway(), new AuditService($db));
        try {
            $service->updateStatus((int)($_SESSION['user_id'] ?? 0), $id, $status);
            $_SESSION['_flash'] = ['success'=>'Subscription updated'];
        } catch (\Throwable $e) {
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
        }
        header('Location: /admin/billing/subscriptions', true, 302);
        exit;
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\View;
use App\Repositories\HostingPlanRepository;
use App\Services\AuditService;
use App\Validators\HostingPlanValidator;

final class PlanController
{
    public function index(array $params = []): void
    {
        $db = Database::getInstance();
        $repo = new HostingPlanRepository($db);
        $plans = $repo->all();
        View::render('admin.plans.index', ['title'=>'Hosting Plans','plans'=>$plans]);
    }

    public function create(array $params = []): void
    {
        View::render('admin.plans.form', ['title'=>'Create Plan','plan'=>null,'errors'=>[]]);
    }

    public function store(array $params = []): void
    {
        $db = Database::getInstance();
        $repo = new HostingPlanRepository($db);
        $audit = new AuditService($db);

        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'slug' => strtolower(trim($_POST['slug'] ?? '')),
            'description' => trim($_POST['description'] ?? ''),
            'storage_limit_mb' => $_POST['storage_limit_mb'] ?? '',
            'bandwidth_limit_mb' => $_POST['bandwidth_limit_mb'] ?? '',
            'database_limit' => $_POST['database_limit'] ?? '',
            'domain_limit' => $_POST['domain_limit'] ?? '',
            'subdomain_limit' => $_POST['subdomain_limit'] ?? '',
            'status' => $_POST['status'] ?? 'active',
            'is_default' => isset($_POST['is_default']) ? 1 : 0,
        ];

        $errors = HostingPlanValidator::validate($data);
        if ($errors) {
            View::render('admin.plans.form', ['title'=>'Create Plan','plan'=>(object)$data,'errors'=>$errors]);
            return;
        }

        // Slug unique check
        if ($repo->findBySlug($data['slug'])) {
            View::render('admin.plans.form', ['title'=>'Create Plan','plan'=>(object)$data,'errors'=>['slug'=>'Slug already exists']]);
            return;
        }

        $plan = $repo->create($data);
        $audit->log((int)($_SESSION['user_id'] ?? 0), 'plan.create', 'hosting_plan', (string)$plan->id, 'success', ['name'=>$plan->name]);

        $_SESSION['_flash'] = ['success'=>'Plan created'];
        header('Location: /admin/plans', true, 302);
        exit;
    }

    public function edit(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $db = Database::getInstance();
        $repo = new HostingPlanRepository($db);
        $plan = $repo->findById($id);
        if (!$plan) {
            http_response_code(404);
            View::render('errors.404', ['title'=>'Not found']);
            return;
        }
        View::render('admin.plans.form', ['title'=>'Edit Plan','plan'=>$plan,'errors'=>[]]);
    }

    public function update(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $db = Database::getInstance();
        $repo = new HostingPlanRepository($db);
        $audit = new AuditService($db);
        $existing = $repo->findById($id);
        if (!$existing) {
            http_response_code(404);
            View::render('errors.404', ['title'=>'Not found']);
            return;
        }

        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'slug' => strtolower(trim($_POST['slug'] ?? '')),
            'description' => trim($_POST['description'] ?? ''),
            'storage_limit_mb' => $_POST['storage_limit_mb'] ?? '',
            'bandwidth_limit_mb' => $_POST['bandwidth_limit_mb'] ?? '',
            'database_limit' => $_POST['database_limit'] ?? '',
            'domain_limit' => $_POST['domain_limit'] ?? '',
            'subdomain_limit' => $_POST['subdomain_limit'] ?? '',
            'status' => $_POST['status'] ?? 'active',
            'is_default' => isset($_POST['is_default']) ? 1 : 0,
        ];

        $errors = HostingPlanValidator::validate($data);
        if ($errors) {
            View::render('admin.plans.form', ['title'=>'Edit Plan','plan'=>(object)array_merge((array)$existing, $data),'errors'=>$errors]);
            return;
        }

        $bySlug = $repo->findBySlug($data['slug']);
        if ($bySlug && $bySlug->id !== $id) {
            View::render('admin.plans.form', ['title'=>'Edit Plan','plan'=>(object)$data,'errors'=>['slug'=>'Slug already exists']]);
            return;
        }

        $plan = $repo->update($id, $data);
        $audit->log((int)($_SESSION['user_id'] ?? 0), 'plan.update', 'hosting_plan', (string)$id, 'success', ['name'=>$plan->name]);
        $_SESSION['_flash'] = ['success'=>'Plan updated'];
        header('Location: /admin/plans', true, 302);
        exit;
    }
}

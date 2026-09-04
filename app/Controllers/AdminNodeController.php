<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\View;
use App\Repositories\HostingNodeRepository;
use App\Services\AuditService;
use App\Services\ProvisioningService;

final class AdminNodeController
{
    public function index(array $params = []): void
    {
        $db = Database::getInstance();
        $repo = new HostingNodeRepository($db);
        $nodes = $repo->all();
        View::render('admin.nodes.index', ['title'=>'Hosting Nodes','nodes'=>$nodes]);
    }

    public function create(array $params = []): void
    {
        View::render('admin.nodes.form', ['title'=>'Create Node','node'=>null,'errors'=>[]]);
    }

    public function store(array $params = []): void
    {
        $name = trim($_POST['name'] ?? '');
        $hostname = trim($_POST['hostname'] ?? '');
        $ip = trim($_POST['ip_address'] ?? '');
        $region = trim($_POST['region'] ?? '');
        $max = (int)($_POST['max_accounts'] ?? 100);

        $errors = [];
        if ($name === '' || !preg_match('/^[a-z0-9\-]{2,50}$/', $name)) $errors['name'] = 'Name 2-50 a-z0-9-';
        if ($hostname === '' || !filter_var($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) $errors['hostname'] = 'Invalid hostname';
        if ($ip !== '' && !filter_var($ip, FILTER_VALIDATE_IP)) $errors['ip_address'] = 'Invalid IP';
        if ($max < 1 || $max > 10000) $errors['max_accounts'] = '1-10000';

        if ($errors) {
            View::render('admin.nodes.form', ['title'=>'Create Node','node'=>(object)$_POST,'errors'=>$errors]);
            return;
        }

        $db = Database::getInstance();
        $repo = new HostingNodeRepository($db);
        // Check unique
        if ($db->fetchColumn("SELECT 1 FROM hosting_nodes WHERE name=?", [$name])) {
            View::render('admin.nodes.form', ['title'=>'Create Node','node'=>(object)$_POST,'errors'=>['name'=>'Name exists']]);
            return;
        }
        if ($db->fetchColumn("SELECT 1 FROM hosting_nodes WHERE hostname=?", [$hostname])) {
            View::render('admin.nodes.form', ['title'=>'Create Node','node'=>(object)$_POST,'errors'=>['hostname'=>'Hostname exists']]);
            return;
        }

        // Generate API key (shown once)
        $keyData = ProvisioningService::generateApiKey();
        $node = $repo->create([
            'name'=>$name,
            'hostname'=>$hostname,
            'ip_address'=>$ip ?: null,
            'region'=>$region ?: null,
            'status'=>'active',
            'max_accounts'=>$max,
            'api_url'=> trim($_POST['api_url'] ?? '') ?: null,
            'api_key_hash'=>$keyData['hash'],
            'api_key_preview'=>$keyData['preview'],
        ]);

        $audit = new AuditService($db);
        $audit->log((int)($_SESSION['user_id'] ?? 0), 'node.create', 'hosting_node', (string)$node->id, 'success', ['name'=>$name]);

        $_SESSION['_flash'] = ['success'=>'Node created. API Key (show once): ' . $keyData['plain'] . ' — store securely! Preview: ' . $keyData['preview']];
        header('Location: /admin/nodes', true, 302);
        exit;
    }

    public function show(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $db = Database::getInstance();
        $repo = new HostingNodeRepository($db);
        $node = $repo->findById($id);
        if (!$node) {
            http_response_code(404);
            View::render('errors.404', ['title'=>'Not found']);
            return;
        }
        $jobs = $db->fetchAll("SELECT * FROM provisioning_jobs WHERE node_id=? ORDER BY id DESC LIMIT 20", [$id]);
        View::render('admin.nodes.show', ['title'=>'Node ' . $node->name,'node'=>$node,'jobs'=>$jobs]);
    }
}

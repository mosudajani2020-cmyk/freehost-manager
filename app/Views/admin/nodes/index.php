<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="fa-solid fa-server me-2"></i>Hosting Nodes</h4>
    <a href="/admin/nodes/create" class="btn btn-primary btn-sm">Create Node</a>
  </div>
  <div class="alert alert-info small">
    <strong>Local mock:</strong> Current node <code>local-mock-1</code> handles all development provisioning. Production will use isolated Linux nodes with PHP-FPM pools. No shell exec from web requests.
  </div>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Name</th><th>Hostname</th><th>IP</th><th>Region</th><th>Status</th><th>Load</th><th>API URL</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($nodes as $n): ?>
            <tr>
              <td><?= e($n->name) ?></td>
              <td class="small"><?= e($n->hostname) ?></td>
              <td class="small"><?= e($n->ipAddress ?? '-') ?></td>
              <td><?= e($n->region ?? '-') ?></td>
              <td><span class="badge bg-<?= $n->status==='active'?'success':($n->status==='maintenance'?'warning':'secondary') ?>"><?= e($n->status) ?></span></td>
              <td><?= (int) $n->currentAccounts ?>/<?= (int) $n->maxAccounts ?></td>
              <td class="small"><?= e($n->apiUrl ?? '-') ?></td>
              <td><a href="/admin/nodes/<?= (int) $n->id ?>" class="btn btn-sm btn-outline-secondary">View</a></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($nodes)): ?><tr><td colspan="8" class="text-muted p-3">No nodes.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

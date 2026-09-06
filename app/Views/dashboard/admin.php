<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container-fluid">
  <div class="row">
    <nav class="col-md-3 col-lg-2 sidebar p-3">
      <h6 class="text-muted text-uppercase small">Admin</h6>
      <ul class="nav flex-column">
        <li class="nav-item"><a class="nav-link active" href="/admin/dashboard"><i class="fa-solid fa-shield-halved me-2"></i>Admin Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="/dashboard"><i class="fa-solid fa-gauge me-2"></i>Customer View</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/users"><i class="fa-solid fa-users me-2"></i>Users</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/hosting"><i class="fa-solid fa-server me-2"></i>Hosting Accounts</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/plans"><i class="fa-solid fa-layer-group me-2"></i>Plans</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/databases"><i class="fa-solid fa-database me-2"></i>Databases</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/dns"><i class="fa-solid fa-globe me-2"></i>DNS</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/ssl"><i class="fa-solid fa-lock me-2"></i>SSL</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/nodes"><i class="fa-solid fa-network-wired me-2"></i>Nodes</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/provisioning"><i class="fa-solid fa-gears me-2"></i>Provisioning</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/monitoring"><i class="fa-solid fa-heart-pulse me-2"></i>Monitoring</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/backups"><i class="fa-solid fa-box-archive me-2"></i>Backups</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/billing/subscriptions"><i class="fa-solid fa-credit-card me-2"></i>Subscriptions</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/billing/invoices"><i class="fa-solid fa-file-invoice me-2"></i>Invoices</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/audit"><i class="fa-solid fa-list-check me-2"></i>Audit Logs</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/settings"><i class="fa-solid fa-sliders me-2"></i>Settings</a></li>
      </ul>
    </nav>
    <main class="col-md-9 col-lg-10 p-4">
      <h2>Admin Dashboard</h2>
      <p class="text-muted">System overview — Phases 1–9 complete.</p>

      <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card"><div class="card-body"><h6 class="text-muted">Total users</h6><h3><?= (int) $stats['total_users'] ?></h3></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><h6 class="text-muted">Active</h6><h3><?= (int) $stats['active_users'] ?></h3></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><h6 class="text-muted">Suspended</h6><h3><?= (int) $stats['suspended_users'] ?></h3></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><h6 class="text-muted">Hosting accounts</h6><h3><?= (int) $stats['hosting_accounts'] ?></h3><small class="text-muted">Active: <?= (int) $stats['active_hosting'] ?></small></div></div></div>
      </div>

      <div class="row g-3">
        <div class="col-lg-6">
          <div class="card">
            <div class="card-header bg-white"><strong>Recent users</strong></div>
            <div class="card-body p-0">
              <table class="table table-sm mb-0">
                <thead><tr><th>Email</th><th>Username</th><th>Status</th></tr></thead>
                <tbody>
                  <?php foreach ($recentUsers as $u): ?>
                    <tr><td><?= e($u['email']) ?></td><td><?= e($u['username']) ?></td><td><span class="badge bg-<?= $u['status']==='active'?'success':'warning' ?>"><?= e($u['status']) ?></span></td></tr>
                  <?php endforeach; ?>
                  <?php if (empty($recentUsers)): ?><tr><td colspan="3" class="text-muted p-3">No users yet.</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="card">
            <div class="card-header bg-white"><strong>Recent audit</strong></div>
            <div class="card-body p-0" style="max-height:300px; overflow:auto">
              <table class="table table-sm mb-0">
                <thead><tr><th>Action</th><th>User</th><th>Result</th><th>Time</th></tr></thead>
                <tbody>
                  <?php foreach ($recentLogs as $l): ?>
                    <tr><td><?= e($l['action']) ?></td><td><?= e($l['user_id'] ?? '-') ?></td><td><?= e($l['result']) ?></td><td class="small"><?= e($l['created_at']) ?></td></tr>
                  <?php endforeach; ?>
                  <?php if (empty($recentLogs)): ?><tr><td colspan="4" class="text-muted p-3">No audit entries.</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

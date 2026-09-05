<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container-fluid">
  <div class="row">
    <nav class="col-md-3 col-lg-2 sidebar p-3">
      <h6 class="text-muted text-uppercase small">Customer</h6>
      <?php $firstHostingId = !empty($hostingAccounts[0]['id']) ? (int)$hostingAccounts[0]['id'] : null; ?>
      <ul class="nav flex-column">
        <li class="nav-item"><a class="nav-link active" href="/dashboard"><i class="fa-solid fa-gauge me-2"></i>Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="/hosting"><i class="fa-solid fa-server me-2"></i>Hosting</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $firstHostingId ? '/hosting/' . $firstHostingId . '/files' : '/hosting' ?>"><i class="fa-solid fa-folder me-2"></i>Files</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $firstHostingId ? '/hosting/' . $firstHostingId . '/databases' : '/hosting' ?>"><i class="fa-solid fa-database me-2"></i>Databases</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $firstHostingId ? '/hosting/' . $firstHostingId . '/dns' : '/hosting' ?>"><i class="fa-solid fa-globe me-2"></i>DNS</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $firstHostingId ? '/hosting/' . $firstHostingId . '/ssl' : '/hosting' ?>"><i class="fa-solid fa-lock me-2"></i>SSL</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $firstHostingId ? '/hosting/' . $firstHostingId . '/usage' : '/hosting' ?>"><i class="fa-solid fa-chart-area me-2"></i>Usage</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $firstHostingId ? '/hosting/' . $firstHostingId . '/backups' : '/hosting' ?>"><i class="fa-solid fa-box-archive me-2"></i>Backups</a></li>
      </ul>
      <hr>
      <div class="small text-muted">
        <div>Status: <span class="badge bg-<?= $user->status === 'active' ? 'success' : 'warning' ?>"><?= e($user->status) ?></span></div>
        <div class="mt-1">Accounts: <?= (int) $hostingCount ?></div>
      </div>
    </nav>
    <main class="col-md-9 col-lg-10 p-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Welcome, <?= e($user->fullName) ?></h2>
        <span class="text-muted"><?= e($user->email) ?> • <?= e($user->username) ?></span>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card"><div class="card-body"><h6 class="text-muted">Hosting accounts</h6><h3><?= (int) $hostingCount ?></h3><a href="/hosting" class="btn btn-sm btn-outline-primary mt-2">Manage</a></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><h6 class="text-muted">Databases</h6><h3><?= (int) $dbCount ?></h3><p class="mb-0 small text-muted">Limit per plan</p></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><h6 class="text-muted">Domains</h6><h3><?= (int) $domainCount ?></h3><p class="mb-0 small text-muted">Subdomains via hosting</p></div></div></div>
      </div>

      <?php if (!empty($hostingAccounts)): ?>
      <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <strong>Your Hosting Accounts</strong>
          <a href="/hosting/create" class="btn btn-sm btn-primary">Create Hosting</a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>ID</th><th>Username</th><th>Plan</th><th>Status</th><th>Storage</th><th>Subdomains</th><th>Created</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($hostingAccounts as $ha): ?>
                <tr>
                  <td><?= (int) $ha['id'] ?></td>
                  <td><?= e($ha['username']) ?></td>
                  <td><span class="badge bg-info"><?= e($ha['plan_name']) ?></span></td>
                  <td><span class="badge bg-<?= $ha['status']==='active'?'success':($ha['status']==='suspended'?'warning':'secondary') ?>"><?= e($ha['status']) ?></span></td>
                  <td><?= e($ha['storage_used_mb']) ?>/<?= e($ha['storage_limit_mb']) ?> MB</td>
                  <td><?= (int) $ha['subdomain_count'] ?>/<?= (int) $ha['subdomain_limit'] ?></td>
                  <td class="small"><?= e($ha['created_at']) ?></td>
                  <td><a href="/hosting/<?= (int) $ha['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php else: ?>
        <div class="alert alert-info mb-4">
          No hosting accounts yet. <a href="/hosting/create">Create your first hosting account</a> — choose a plan and username.
        </div>
      <?php endif; ?>

      <div class="row g-3">
        <div class="col-lg-6">
          <div class="card">
            <div class="card-header bg-white"><strong>Recent activity</strong></div>
            <div class="card-body p-0">
              <?php if (empty($auditLogs)): ?>
                <p class="p-3 text-muted mb-0">No activity yet.</p>
              <?php else: ?>
                <table class="table table-sm mb-0">
                  <thead><tr><th>Action</th><th>Result</th><th>When</th></tr></thead>
                  <tbody>
                    <?php foreach ($auditLogs as $log): ?>
                      <tr><td><?= e($log['action']) ?></td><td><span class="badge bg-<?= $log['result']==='success'?'success':'danger' ?>"><?= e($log['result']) ?></span></td><td class="small"><?= e($log['created_at']) ?></td></tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="card">
            <div class="card-header bg-white"><strong>Notifications</strong></div>
            <div class="card-body">
              <?php if (empty($notifications)): ?>
                <p class="text-muted mb-0">No notifications.</p>
              <?php else: ?>
                <?php foreach ($notifications as $n): ?>
                  <div class="alert alert-info py-2"><?= e($n['title']) ?> — <small><?= e($n['body']) ?></small></div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

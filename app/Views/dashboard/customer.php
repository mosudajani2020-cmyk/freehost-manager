<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container-fluid">
  <div class="row">
    <nav class="col-md-3 col-lg-2 sidebar p-3">
      <h6 class="text-muted text-uppercase small">Customer</h6>
      <ul class="nav flex-column">
        <li class="nav-item"><a class="nav-link active" href="/dashboard"><i class="fa-solid fa-gauge me-2"></i>Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="#"><i class="fa-solid fa-server me-2"></i>Hosting (Phase 2)</a></li>
        <li class="nav-item"><a class="nav-link" href="#"><i class="fa-solid fa-folder me-2"></i>Files (Phase 3)</a></li>
        <li class="nav-item"><a class="nav-link" href="#"><i class="fa-solid fa-database me-2"></i>Databases (Phase 4)</a></li>
        <li class="nav-item"><a class="nav-link" href="#"><i class="fa-solid fa-globe me-2"></i>Domains (Phase 5)</a></li>
      </ul>
      <hr>
      <div class="small text-muted">
        <div>Status: <span class="badge bg-<?= $user->status === 'active' ? 'success' : 'warning' ?>"><?= e($user->status) ?></span></div>
        <div class="mt-1">Plan: Default</div>
      </div>
    </nav>
    <main class="col-md-9 col-lg-10 p-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Welcome, <?= e($user->fullName) ?></h2>
        <span class="text-muted"><?= e($user->email) ?> • <?= e($user->username) ?></span>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <div class="card"><div class="card-body">
            <h6 class="text-muted">Hosting accounts</h6>
            <h3><?= (int) $hostingCount ?></h3>
            <p class="mb-0 small text-muted">Phase 2 will enable creation</p>
          </div></div>
        </div>
        <div class="col-md-4">
          <div class="card"><div class="card-body">
            <h6 class="text-muted">Databases</h6>
            <h3><?= (int) $dbCount ?></h3>
            <p class="mb-0 small text-muted">Limit per plan</p>
          </div></div>
        </div>
        <div class="col-md-4">
          <div class="card"><div class="card-body">
            <h6 class="text-muted">Domains</h6>
            <h3><?= (int) $domainCount ?></h3>
            <p class="mb-0 small text-muted">Subdomains: Phase 5</p>
          </div></div>
        </div>
      </div>

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
              <hr>
              <p class="small text-muted mb-0">Storage: <em>Phase 2 quota display</em> — User isolation is enforced server-side. Customer PHP will not execute in control panel origin.</p>
            </div>
          </div>
        </div>
      </div>

      <div class="alert alert-warning mt-4">
        <strong>Phase 1 Foundation:</strong> File manager, hosting provisioning and domain management are intentionally deferred to later phases. Security primitives (PathGuard, UploadGuard, RateLimiter) are active.
      </div>
    </main>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container-fluid py-4">
  <h4><i class="fa-solid fa-heart-pulse me-2"></i>System Monitoring</h4>

  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card border-<?= $health['status']==='healthy'?'success':($health['status']==='warning'?'warning':'danger') ?>">
        <div class="card-body">
          <h6>System Health <span class="badge bg-<?= $health['status']==='healthy'?'success':($health['status']==='warning'?'warning':'danger') ?>"><?= e($health['status']) ?></span></h6>
          <ul class="small mb-0">
            <?php foreach ($health['checks'] as $k=>$v): ?><li><?= e($k) ?>: <?= e((string)$v) ?></li><?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-<?= $provisioning['status']==='healthy'?'success':($provisioning['status']==='warning'?'warning':'danger') ?>">
        <div class="card-body">
          <h6>Provisioning Health <span class="badge bg-<?= $provisioning['status']==='healthy'?'success':($provisioning['status']==='warning'?'warning':'danger') ?>"><?= e($provisioning['status']) ?></span></h6>
          <p class="mb-1 small">Queued: <?= (int) $provisioning['queued'] ?> • Failed: <?= (int) $provisioning['failed'] ?></p>
          <a href="/admin/provisioning?status=failed" class="btn btn-sm btn-outline-danger">View Failed Jobs</a>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <div class="card-body">
          <h6>Operational Stats</h6>
          <ul class="small mb-0">
            <li>Users: <?= (int) $stats['total_users'] ?> (active <?= (int) $stats['hosting_active'] ?>)</li>
            <li>Hosting: <?= (int) $stats['hosting_total'] ?> total, <?= (int) $stats['hosting_suspended'] ?> suspended</li>
            <li>Databases: <?= (int) $stats['databases'] ?></li>
            <li>Backups: <?= (int) $stats['backups'] ?> (failed <?= (int) $stats['backups_failed'] ?>)</li>
            <li>Audit 24h: <?= (int) $stats['audit_last_24h'] ?></li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header bg-white"><strong>Failed Jobs (<?= count($failed) ?>)</strong></div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Job</th><th>Account</th><th>Operation</th><th>Error</th></tr></thead>
            <tbody>
              <?php foreach ($failed as $j): ?>
                <tr><td class="small"><a href="/admin/provisioning/<?= (int) $j['id'] ?>"><?= e(substr($j['job_uuid'],0,8)) ?></a></td><td><?= (int) $j['hosting_account_id'] ?></td><td class="small"><?= e($j['operation']) ?></td><td class="small text-danger"><?= e(substr($j['last_error'] ?? '',0,40)) ?></td></tr>
              <?php endforeach; ?>
              <?php if(empty($failed)): ?><tr><td colspan="4" class="text-muted p-3">No failed jobs.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header bg-white"><strong>Recent Audit Events</strong></div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Action</th><th>User</th><th>Result</th><th>Time</th></tr></thead>
            <tbody>
              <?php foreach ($recentAudit as $a): ?>
                <tr><td class="small"><?= e($a['action']) ?></td><td><?= e($a['user_id'] ?? '-') ?></td><td><span class="badge bg-<?= $a['result']==='success'?'success':'danger' ?>"><?= e($a['result']) ?></span></td><td class="small"><?= e($a['created_at']) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-4 d-flex gap-2">
    <a href="/admin/provisioning" class="btn btn-outline-secondary btn-sm">Provisioning Jobs</a>
    <a href="/admin/backups" class="btn btn-outline-secondary btn-sm">Backups</a>
    <a href="/admin/dns" class="btn btn-outline-secondary btn-sm">DNS</a>
    <a href="/admin/ssl" class="btn btn-outline-secondary btn-sm">SSL</a>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

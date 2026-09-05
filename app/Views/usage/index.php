<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-4">
  <h4><i class="fa-solid fa-chart-area me-2"></i>Usage — <?= e($account->username) ?></h4>
  <p class="text-muted small">Hosting ID <?= (int) $accountId ?> • Plan <?= e($plan?->name ?? '?') ?> • Quota warnings shown below</p>

  <?php if (!empty($warnings)): ?>
    <div class="alert alert-warning">
      <strong>Quota Warnings:</strong>
      <ul class="mb-0"><?php foreach ($warnings as $w): ?><li><?= e($w) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card"><div class="card-body"><h6 class="text-muted">Storage</h6><h5><?= e($storage_used_mb) ?> / <?= e($storage_limit_mb) ?> MB</h5><div class="progress" style="height:6px"><div class="progress-bar <?= ($storage_used_mb/$storage_limit_mb)>0.9?'bg-danger':($storage_used_mb/$storage_limit_mb>0.7?'bg-warning':'bg-success') ?>" style="width: <?= $storage_limit_mb>0? round($storage_used_mb/$storage_limit_mb*100):0 ?>%"></div></div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><h6 class="text-muted">Bandwidth</h6><h5><?= e($bandwidth_used_mb) ?> / <?= e($bandwidth_limit_mb) ?> MB</h5></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><h6 class="text-muted">Databases</h6><h5><?= (int) $database_used ?> / <?= (int) $database_limit ?></h5></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><h6 class="text-muted">Domains / Subs</h6><h5><?= (int) $domain_used ?>/<?= (int) $domain_limit ?> • <?= (int) $subdomain_used ?>/<?= (int) $subdomain_limit ?></h5></div></div></div>
  </div>

  <div class="card mb-3">
    <div class="card-header bg-white d-flex justify-content-between">
      <strong>Recent Usage Records</strong>
      <form method="POST" action="/hosting/<?= (int) $accountId ?>/usage/collect"><?= csrf_field() ?><button class="btn btn-sm btn-outline-primary">Collect Now</button></form>
    </div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th>Type</th><th>Used</th><th>Limit</th><th>Recorded</th></tr></thead>
        <tbody>
          <?php foreach ($history as $h): ?>
            <tr><td><?= e($h['type']) ?></td><td><?= (int) $h['used'] ?></td><td><?= (int) $h['limit_val'] ?></td><td class="small"><?= e($h['recorded_at']) ?></td></tr>
          <?php endforeach; ?>
          <?php if(empty($history)): ?><tr><td colspan="4" class="text-muted p-3">No history yet. Click Collect Now.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-3">
    <a href="/hosting/<?= (int) $accountId ?>" class="btn btn-secondary btn-sm">Back to Hosting</a>
    <a href="/hosting/<?= (int) $accountId ?>/backups" class="btn btn-outline-secondary btn-sm ms-2">Backups</a>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

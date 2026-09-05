<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container-fluid py-4">
  <h4>Usage Overview</h4>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th>Hosting ID</th><th>Username</th><th>Status</th><th>Plan</th><th>Storage Limit</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr><td><?= (int) $r['id'] ?></td><td><?= e($r['username']) ?></td><td><span class="badge bg-<?= $r['status']==='active'?'success':'warning' ?>"><?= e($r['status']) ?></span></td><td><?= e($r['plan_name']) ?></td><td><?= (int) $r['storage_limit_mb'] ?> MB</td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fa-solid fa-server me-2"></i>My Hosting</h2>
    <a href="/hosting/create" class="btn btn-primary">Create Hosting Account</a>
  </div>
  <?php if (empty($accounts)): ?>
    <div class="alert alert-info">No hosting accounts. <a href="/hosting/create">Create one</a>.</div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($accounts as $a): ?>
        <div class="col-md-6 col-lg-4">
          <div class="card h-100">
            <div class="card-body">
              <h5 class="card-title"><?= e($a->username) ?> <span class="badge bg-<?= $a->status==='active'?'success':($a->status==='suspended'?'warning':'secondary') ?> float-end"><?= e($a->status) ?></span></h5>
              <p class="text-muted small mb-2">Plan: <?= e($a->plan?->name ?? '—') ?> | ID <?= (int) $a->id ?></p>
              <ul class="small text-muted mb-3">
                <li>Storage: <?= e($a->storageUsedMb) ?>/<?= e($a->plan?->storageLimitMb ?? '?') ?> MB</li>
                <li>Bandwidth: <?= e($a->bandwidthUsedMb) ?>/<?= e($a->plan?->bandwidthLimitMb ?? '?') ?> MB</li>
                <li>Created: <?= e($a->createdAt) ?></li>
              </ul>
              <a href="/hosting/<?= (int) $a->id ?>" class="btn btn-sm btn-outline-primary w-100">View Details</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

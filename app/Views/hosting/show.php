<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-4">
  <h3>Hosting Account — <?= e($account->username) ?></h3>
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-body">
          <table class="table table-sm">
            <tr><th>ID</th><td><?= (int) $account->id ?></td></tr>
            <tr><th>Username</th><td><?= e($account->username) ?></td></tr>
            <tr><th>Plan</th><td><?= e($account->plan?->name ?? '—') ?> (<?= e($account->plan?->slug ?? '') ?>)</td></tr>
            <tr><th>Status</th><td><span class="badge bg-<?= $account->status==='active'?'success':($account->status==='suspended'?'warning':'secondary') ?>"><?= e($account->status) ?></span></td></tr>
            <tr><th>Root</th><td class="small text-muted"><?= e($account->rootPath) ?> (mock, not web-accessible)</td></tr>
            <tr><th>Storage</th><td><?= e($account->storageUsedMb) ?> / <?= e($account->plan?->storageLimitMb ?? '?') ?> MB</td></tr>
            <tr><th>Bandwidth</th><td><?= e($account->bandwidthUsedMb) ?> / <?= e($account->plan?->bandwidthLimitMb ?? '?') ?> MB</td></tr>
            <tr><th>Database limit</th><td><?= e($account->plan?->databaseLimit ?? '?') ?></td></tr>
            <tr><th>Domain limit</th><td><?= e($account->plan?->domainLimit ?? '?') ?></td></tr>
            <tr><th>Subdomain limit</th><td><?= e($account->plan?->subdomainLimit ?? '?') ?> (used: <?= count($subdomains) ?>)</td></tr>
            <tr><th>Created</th><td><?= e($account->createdAt) ?></td></tr>
          </table>
          <?php if ($account->status==='suspended'): ?>
            <div class="alert alert-warning">This account is suspended — subdomain creation is blocked.</div>
          <?php elseif ($account->status==='terminated'): ?>
            <div class="alert alert-danger">This account is terminated — no operations allowed.</div>
          <?php endif; ?>
          <div class="mt-3 d-grid gap-2">
            <?php if ($account->status==='active'): ?>
              <a href="/hosting/<?= (int) $account->id ?>/files" class="btn btn-primary"><i class="fa-solid fa-folder-open me-1"></i>Open File Manager</a>
            <?php else: ?>
              <a href="/hosting/<?= (int) $account->id ?>/files" class="btn btn-secondary disabled">File Manager (requires active)</a>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header bg-white"><strong>Subdomains</strong> <span class="text-muted small">.<?= e($mainDomain) ?></span></div>
        <div class="card-body">
          <?php if ($account->status !== 'active'): ?>
            <p class="text-muted">Subdomain creation requires active status.</p>
          <?php else: ?>
            <form method="POST" action="/hosting/<?= (int) $account->id ?>/subdomain" class="row g-2 mb-3">
              <?= csrf_field() ?>
              <div class="col-8">
                <div class="input-group">
                  <input type="text" name="subdomain" class="form-control" placeholder="customer" required pattern="[a-z0-9\-]{1,63}">
                  <span class="input-group-text">.<?= e($mainDomain) ?></span>
                </div>
                <div class="form-text">1-63 chars, a-z0-9, hyphens not at ends, not reserved.</div>
              </div>
              <div class="col-4"><button class="btn btn-primary w-100">Create Subdomain</button></div>
            </form>
          <?php endif; ?>

          <?php if (empty($subdomains)): ?>
            <p class="text-muted mb-0">No subdomains yet.</p>
          <?php else: ?>
            <table class="table table-sm">
              <thead><tr><th>Subdomain</th><th>Full Domain</th><th>Status</th><th>Created</th></tr></thead>
              <tbody>
                <?php foreach ($subdomains as $sd): ?>
                  <tr><td><?= e($sd->subdomain) ?></td><td><?= e($sd->fullDomain) ?></td><td><?= e($sd->status) ?></td><td class="small"><?= e($sd->createdAt) ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header bg-white"><strong>Actions</strong></div>
        <div class="card-body">
          <p class="small text-muted">Customer actions limited to subdomain creation. Suspension/termination is admin only.</p>
          <a href="/hosting" class="btn btn-outline-secondary w-100">Back to Hosting</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-4">
  <h3>Hosting #<?= (int) $account->id ?> — <?= e($account->username) ?></h3>
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-body">
          <table class="table table-sm">
            <tr><th>Username</th><td><?= e($account->username) ?></td></tr>
            <tr><th>Owner</th><td><?= $owner ? e($owner['email'] ?? $owner['username']) . ' (ID ' . (int) $account->userId . ')' : e((string)$account->userId) ?></td></tr>
            <tr><th>Plan</th><td><?= e($account->plan?->name ?? '') ?> — <?= e($account->plan?->storageLimitMb ?? '?') ?> MB / <?= e($account->plan?->subdomainLimit ?? '?') ?> subs</td></tr>
            <tr><th>Status</th><td><span class="badge bg-<?= $account->status==='active'?'success':($account->status==='suspended'?'warning':'secondary') ?>"><?= e($account->status) ?></span></td></tr>
            <tr><th>Root</th><td class="small"><?= e($account->rootPath) ?></td></tr>
            <tr><th>Created</th><td><?= e($account->createdAt) ?></td></tr>
            <tr><th>Storage</th><td><?= e($account->storageUsedMb) ?>/<?= e($account->plan?->storageLimitMb ?? '?') ?> MB</td></tr>
            <tr><th>Bandwidth</th><td><?= e($account->bandwidthUsedMb) ?>/<?= e($account->plan?->bandwidthLimitMb ?? '?') ?> MB</td></tr>
          </table>
        </div>
      </div>
      <div class="card">
        <div class="card-header bg-white"><strong>Subdomains (<?= count($subdomains) ?>)</strong></div>
        <div class="card-body p-0">
          <?php if (empty($subdomains)): ?><p class="p-3 text-muted mb-0">None.</p>
          <?php else: ?>
            <table class="table table-sm mb-0"><thead><tr><th>Subdomain</th><th>Full</th><th>Status</th></tr></thead><tbody>
              <?php foreach ($subdomains as $s): ?><tr><td><?= e($s->subdomain) ?></td><td><?= e($s->fullDomain) ?></td><td><?= e($s->status) ?></td></tr><?php endforeach; ?>
            </tbody></table>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header bg-white"><strong>Actions</strong></div>
        <div class="card-body d-grid gap-2">
          <?php if ($account->status !== 'suspended' && !$account->isTerminated()): ?>
            <form method="POST" action="/admin/hosting/<?= (int) $account->id ?>/suspend"><?= csrf_field() ?><button class="btn btn-warning w-100" onclick="return confirm('Suspend this account?')">Suspend</button></form>
          <?php endif; ?>
          <?php if ($account->status === 'suspended'): ?>
            <form method="POST" action="/admin/hosting/<?= (int) $account->id ?>/activate"><?= csrf_field() ?><button class="btn btn-success w-100">Activate</button></form>
          <?php endif; ?>
          <?php if (!$account->isTerminated()): ?>
            <form method="POST" action="/admin/hosting/<?= (int) $account->id ?>/terminate"><?= csrf_field() ?><button class="btn btn-danger w-100" onclick="return confirm('Terminate — cannot be reactivated via same account?')">Terminate</button></form>
          <?php endif; ?>
          <a href="/admin/hosting" class="btn btn-outline-secondary">Back</a>
        </div>
      </div>
      <div class="alert alert-info mt-3 small">Local mock: filesystem under storage/hosting — not real production isolation.</div>
    </div>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

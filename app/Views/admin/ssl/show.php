<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-4">
  <h4>SSL <?= e($row['hostname']) ?></h4>
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-body">
          <table class="table table-sm">
            <tr><th>ID</th><td><?= (int) $row['id'] ?></td></tr>
            <tr><th>Hostname</th><td><code><?= e($row['hostname']) ?></code></td></tr>
            <tr><th>Hosting</th><td><?= (int) $row['hosting_account_id'] ?> <?= $account ? '(' . e($account['username']) . ')' : '' ?></td></tr>
            <tr><th>Status</th><td><span class="badge bg-<?= $row['status']==='active'?'success':'warning' ?>"><?= e($row['status']) ?></span></td></tr>
            <tr><th>Provider</th><td><?= e($row['provider']) ?> (mock, no real Let's Encrypt)</td></tr>
            <tr><th>Expires</th><td><?= e($row['expires_at'] ?? '-') ?></td></tr>
            <tr><th>Auto Renew</th><td><?= $row['auto_renew'] ? 'Yes' : 'No' ?></td></tr>
            <tr><th>Last Error</th><td class="small text-danger"><?= e($row['last_error'] ?? '-') ?></td></tr>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header bg-white"><strong>Provisioning</strong></div>
        <div class="card-body">
          <form method="POST" action="/admin/ssl/<?= (int) $row['id'] ?>/status">
            <?= csrf_field() ?>
            <select name="status" class="form-select mb-2">
              <?php foreach (['pending','issuing','active','renewing','expired','failed','revoked'] as $s): ?>
                <option value="<?= e($s) ?>" <?= $row['status']===$s?'selected':'' ?>><?= e($s) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-primary w-100">Update Status</button>
          </form>
          <a href="/admin/ssl" class="btn btn-outline-secondary w-100 mt-2">Back</a>
        </div>
      </div>
      <p class="small text-muted mt-2">Local mock: certificates auto-issue for subdomains, 90-day expiry. Production will use ACME.</p>
    </div>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-4">
  <h4>Node: <?= e($node->name) ?></h4>
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-body">
          <table class="table table-sm">
            <tr><th>Hostname</th><td><?= e($node->hostname) ?></td></tr>
            <tr><th>IP</th><td><?= e($node->ipAddress ?? '-') ?></td></tr>
            <tr><th>Region</th><td><?= e($node->region ?? '-') ?></td></tr>
            <tr><th>Status</th><td><span class="badge bg-<?= $node->status==='active'?'success':'warning' ?>"><?= e($node->status) ?></span></td></tr>
            <tr><th>Load</th><td><?= (int) $node->currentAccounts ?>/<?= (int) $node->maxAccounts ?></td></tr>
            <tr><th>API URL</th><td class="small"><?= e($node->apiUrl ?? '-') ?></td></tr>
            <tr><th>Key Preview</th><td><code><?= e($node->apiKeyPreview ?? '-') ?></code> (hash stored, plain shown once)</td></tr>
          </table>
        </div>
      </div>
      <div class="card">
        <div class="card-header bg-white"><strong>Recent Jobs on this Node (<?= count($jobs) ?>)</strong></div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Job</th><th>Account</th><th>Operation</th><th>Status</th><th>Attempts</th></tr></thead>
            <tbody>
              <?php foreach ($jobs as $j): ?>
                <tr><td class="small"><a href="/admin/provisioning/<?= (int) $j['id'] ?>"><?= e(substr($j['job_uuid'],0,8)) ?></a></td><td><?= (int) $j['hosting_account_id'] ?></td><td class="small"><?= e($j['operation']) ?></td><td><span class="badge bg-<?= $j['status']==='active'?'success':($j['status']==='failed'?'danger':'warning') ?>"><?= e($j['status']) ?></span></td><td><?= (int) $j['attempts'] ?>/<?= (int) $j['max_attempts'] ?></td></tr>
              <?php endforeach; ?>
              <?php if(empty($jobs)): ?><tr><td colspan="5" class="text-muted p-3">No jobs.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header bg-white"><strong>Security</strong></div>
        <div class="card-body small text-muted">
          <p>API key is hashed (SHA256) with preview only. Future: HMAC-SHA256 request signing, timestamp + nonce replay protection (5-min window), job idempotency via <code>idempotency_key</code>.</p>
          <p>Worker authenticates via <code>X-Api-Key</code> + <code>X-Signature</code>. No SSH passwords stored.</p>
          <a href="/admin/nodes" class="btn btn-outline-secondary w-100">Back</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

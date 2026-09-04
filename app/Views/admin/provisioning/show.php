<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-4">
  <h4>Job <?= e(substr($job->jobUuid,0,8)) ?> — <?= e($job->operation) ?></h4>
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-body">
          <table class="table table-sm">
            <tr><th>UUID</th><td class="font-monospace small"><?= e($job->jobUuid) ?></td></tr>
            <tr><th>Account</th><td><?= (int) $job->hostingAccountId ?> <?= $account ? '(' . e($account['username'] ?? '') . ')' : '' ?></td></tr>
            <tr><th>Node</th><td><?= $job->nodeId ? (int) $job->nodeId . ' (' . e($node['hostname'] ?? '') . ')' : '-' ?></td></tr>
            <tr><th>Operation</th><td><?= e($job->operation) ?></td></tr>
            <tr><th>Payload</th><td><pre class="small bg-light p-2"><?= e($job->payload ?? '{}') ?></pre></td></tr>
            <tr><th>Status</th><td><span class="badge bg-<?= $job->status==='active'?'success':($job->status==='failed'?'danger':'warning') ?>"><?= e($job->status) ?></span></td></tr>
            <tr><th>Attempts</th><td><?= (int) $job->attempts ?>/<?= (int) $job->maxAttempts ?></td></tr>
            <tr><th>Idempotency</th><td class="font-monospace small"><?= e($job->idempotencyKey) ?></td></tr>
            <tr><th>Last Error</th><td class="small text-danger"><?= e($job->lastError ?? '-') ?></td></tr>
            <tr><th>Requested By</th><td><?= $job->requestedBy ? (int) $job->requestedBy : '-' ?></td></tr>
            <tr><th>Created</th><td><?= e($job->createdAt) ?></td></tr>
            <tr><th>Completed</th><td><?= e($job->completedAt ?? '-') ?></td></tr>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header bg-white"><strong>Actions</strong></div>
        <div class="card-body d-grid gap-2">
          <?php if($job->status==='failed' && $job->attempts < $job->maxAttempts): ?>
            <form method="POST" action="/admin/provisioning/<?= (int) $job->id ?>/retry"><?= csrf_field() ?><button class="btn btn-warning w-100">Retry (<?= (int) $job->attempts ?>/<?= (int) $job->maxAttempts ?>)</button></form>
          <?php endif; ?>
          <?php if(!in_array($job->status, ['active','terminated'], true)): ?>
            <form method="POST" action="/admin/provisioning/<?= (int) $job->id ?>/fail"><?= csrf_field() ?><input type="hidden" name="reason" value="Manual fail by admin"><button class="btn btn-danger w-100" onclick="return confirm('Mark failed?')">Mark Failed</button></form>
          <?php endif; ?>
          <a href="/admin/provisioning" class="btn btn-outline-secondary">Back to Jobs</a>
        </div>
      </div>
      <div class="alert alert-info mt-3 small">
        Signing: <code>X-Signature = HMAC-SHA256(method|path|bodyHash|timestamp|nonce, apiKey)</code> with 5-min TTL + nonce replay protection via <code>rate_limits</code>.
      </div>
    </div>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

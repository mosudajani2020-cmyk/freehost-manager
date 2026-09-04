<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container-fluid py-4">
  <h4>Provisioning Jobs <small class="text-muted"><?= (int) $total ?> total</small></h4>
  <form method="GET" class="row g-2 mb-3">
    <div class="col-auto">
      <select name="status" class="form-select">
        <option value="">All statuses</option>
        <?php foreach (['pending','queued','provisioning','active','failed','retrying','suspended','terminated'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $status===$s?'selected':'' ?>><?= e($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div>
  </form>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th>ID</th><th>UUID</th><th>Account</th><th>Node</th><th>Operation</th><th>Status</th><th>Attempts</th><th>Idempotency</th><th>Created</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($jobs as $j): ?>
            <tr>
              <td><?= (int) $j->id ?></td>
              <td class="small font-monospace"><?= e(substr($j->jobUuid,0,8)) ?>…</td>
              <td><?= (int) $j->hostingAccountId ?></td>
              <td><?= $j->nodeId ? (int) $j->nodeId : '-' ?></td>
              <td class="small"><?= e($j->operation) ?></td>
              <td><span class="badge bg-<?= $j->status==='active'?'success':($j->status==='failed'?'danger':($j->status==='retrying'?'warning':'secondary')) ?>"><?= e($j->status) ?></span></td>
              <td><?= (int) $j->attempts ?>/<?= (int) $j->maxAttempts ?></td>
              <td class="small font-monospace"><?= e(substr($j->idempotencyKey,0,12)) ?>…</td>
              <td class="small"><?= e($j->createdAt) ?></td>
              <td><a href="/admin/provisioning/<?= (int) $j->id ?>" class="btn btn-sm btn-outline-secondary">View</a></td>
            </tr>
          <?php endforeach; ?>
          <?php if(empty($jobs)): ?><tr><td colspan="10" class="text-muted p-3">No jobs.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if($pages>1): ?>
      <div class="card-footer d-flex justify-content-between"><span>Page <?= (int) $page ?> of <?= (int) $pages ?></span><div><?php if($page>1): ?><a href="?status=<?= e($status) ?>&page=<?= $page-1 ?>" class="btn btn-sm btn-outline-secondary">Prev</a><?php endif; ?><?php if($page<$pages): ?><a href="?status=<?= e($status) ?>&page=<?= $page+1 ?>" class="btn btn-sm btn-outline-secondary">Next</a><?php endif; ?></div></div>
    <?php endif; ?>
  </div>
  <p class="small text-muted mt-2">Idempotency prevents duplicate jobs; retry up to 3; failed jobs can be retried by admin; all state changes audited.</p>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-4">
  <h4>Database <?= e($row['name']) ?></h4>
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-body">
          <table class="table table-sm">
            <tr><th>ID</th><td><?= (int) $row['id'] ?></td></tr>
            <tr><th>Name</th><td><code><?= e($row['name']) ?></code></td></tr>
            <tr><th>Hosting</th><td><?= e($row['hosting_user']) ?> (<?= (int) $row['hosting_account_id'] ?>)</td></tr>
            <tr><th>Owner</th><td><?= $owner ? e($owner['email'] ?? $owner['username']) . ' (ID ' . (int)$row['user_id'] . ')' : e((string)$row['user_id']) ?></td></tr>
            <tr><th>Hosting Status</th><td><?= e($row['hosting_status']) ?></td></tr>
            <tr><th>Charset</th><td><?= e($row['charset']) ?></td></tr>
            <tr><th>Created</th><td><?= e($row['created_at']) ?></td></tr>
          </table>
        </div>
      </div>
      <div class="card">
        <div class="card-header bg-white"><strong>Users (<?= count($users) ?>)</strong></div>
        <div class="card-body p-0">
          <?php if (empty($users)): ?><p class="p-3 text-muted mb-0">No users.</p>
          <?php else: ?>
            <table class="table table-sm mb-0"><thead><tr><th>Username</th><th>Host</th><th>Created</th></tr></thead><tbody>
              <?php foreach($users as $u): ?><tr><td><code><?= e($u['username']) ?></code></td><td><?= e($u['host']) ?></td><td class="small"><?= e($u['created_at']) ?></td></tr><?php endforeach; ?>
            </tbody></table>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header bg-white"><strong>Admin</strong></div>
        <div class="card-body"><p class="small text-muted">Customer credentials are encrypted with APP_KEY and never logged. Admin can view metadata only, not plaintext passwords.</p><a href="/admin/databases" class="btn btn-outline-secondary w-100">Back</a></div>
      </div>
    </div>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

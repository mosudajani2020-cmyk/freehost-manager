<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-4">
  <h4>User <?= e($user['username']) ?></h4>
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-body">
          <table class="table table-sm">
            <tr><th>ID</th><td><?= (int) $user['id'] ?></td></tr>
            <tr><th>Email</th><td><?= e($user['email']) ?></td></tr>
            <tr><th>Username</th><td><?= e($user['username']) ?></td></tr>
            <tr><th>Full Name</th><td><?= e($user['full_name']) ?></td></tr>
            <tr><th>Status</th><td><span class="badge bg-<?= $user['status']==='active'?'success':'warning' ?>"><?= e($user['status']) ?></span></td></tr>
            <tr><th>Roles</th><td><?php foreach ($roles as $r): ?><span class="badge bg-info me-1"><?= e($r['name']) ?></span><?php endforeach; ?></td></tr>
          </table>
        </div>
      </div>
      <div class="card mb-3">
        <div class="card-header bg-white"><strong>Hosting Accounts (<?= count($hosting) ?>)</strong></div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>ID</th><th>Username</th><th>Plan</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($hosting as $h): ?><tr><td><?= (int) $h['id'] ?></td><td><?= e($h['username']) ?></td><td><?= (int) $h['plan_id'] ?></td><td><?= e($h['status']) ?></td></tr><?php endforeach; ?>
              <?php if(empty($hosting)): ?><tr><td colspan="4" class="text-muted p-3">No hosting.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card">
        <div class="card-header bg-white"><strong>Recent Audit</strong></div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Action</th><th>Result</th><th>Time</th></tr></thead>
            <tbody>
              <?php foreach ($audit as $a): ?><tr><td><?= e($a['action']) ?></td><td><?= e($a['result']) ?></td><td class="small"><?= e($a['created_at']) ?></td></tr><?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header bg-white"><strong>Actions</strong></div>
        <div class="card-body d-grid gap-2">
          <form method="POST" action="/admin/users/<?= (int) $user['id'] ?>/status"><?= csrf_field() ?><select name="status" class="form-select mb-2"><option value="active" <?= $user['status']==='active'?'selected':'' ?>>active</option><option value="suspended" <?= $user['status']==='suspended'?'selected':'' ?>>suspended</option><option value="banned" <?= $user['status']==='banned'?'selected':'' ?>>banned</option></select><button class="btn btn-primary">Update Status</button></form>
          <a href="/admin/users" class="btn btn-outline-secondary">Back</a>
        </div>
      </div>
      <p class="small text-muted mt-2">All status changes are audited. No passwords shown.</p>
    </div>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

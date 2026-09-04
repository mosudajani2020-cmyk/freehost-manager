<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-4">
  <h4>Database: <?= e($db['name']) ?></h4>
  <p class="text-muted small">Hosting <?= (int) $accountId ?> • Charset <?= e($db['charset']) ?> • Created <?= e($db['created_at']) ?></p>

  <?php
    // Flash password once
    $flash = $_SESSION['_flash'] ?? [];
    // Password is also in flash success message; we display separately if set
  ?>
  <?php if (!empty($flash['password_once'])): ?>
    <div class="alert alert-warning">
      <strong>Password (show once):</strong> <code><?= e($flash['password_once']) ?></code> — copy now, it will not be shown again. Use reset if lost.
    </div>
  <?php endif; ?>

  <div class="card mb-4">
    <div class="card-header bg-white d-flex justify-content-between">
      <strong>Database Users (<?= count($db['users']) ?>)</strong>
      <span class="small text-muted">Host: localhost • Privileges: ALL</span>
    </div>
    <div class="card-body">
      <form method="POST" action="/hosting/<?= (int) $accountId ?>/databases/<?= (int) $db['id'] ?>/users" class="row g-2 mb-3">
        <?= csrf_field() ?>
        <input type="hidden" name="db_id" value="<?= (int) $db['id'] ?>">
        <div class="col-md-7">
          <div class="input-group">
            <span class="input-group-text">fh_<?= (int) $accountId ?>_u_</span>
            <input name="username" class="form-control" placeholder="myuser" required pattern="[a-z][a-z0-9_]{2,29}">
          </div>
          <div class="form-text">Password auto-generated (16 chars) and shown once.</div>
        </div>
        <div class="col-md-5"><button class="btn btn-primary w-100">Create User</button></div>
      </form>

      <?php if (empty($db['users'])): ?>
        <p class="text-muted">No users yet.</p>
      <?php else: ?>
        <table class="table table-sm">
          <thead><tr><th>Username</th><th>Host</th><th>Privileges</th><th>Created</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($db['users'] as $u): ?>
              <tr>
                <td><code><?= e($u['username']) ?></code></td>
                <td><?= e($u['host']) ?></td>
                <td><?= e($u['privileges']) ?></td>
                <td class="small"><?= e($u['created_at']) ?></td>
                <td>
                  <form method="POST" action="/hosting/<?= (int) $accountId ?>/databases/users/delete" onsubmit="return confirm('Delete user <?= e($u['username']) ?>?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                    <input type="hidden" name="db_id" value="<?= (int) $db['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h6>Connection Info (mock)</h6>
      <ul class="small">
        <li>Host: <code>localhost</code> (production will be hosting node)</li>
        <li>Database: <code><?= e($db['name']) ?></code></li>
        <li>Charset: <code><?= e($db['charset']) ?></code></li>
        <li>Note: Credentials shown once; use reset if lost. Never commit to Git.</li>
      </ul>
    </div>
  </div>

  <div class="mt-3">
    <a href="/hosting/<?= (int) $accountId ?>/databases" class="btn btn-secondary btn-sm">Back to Databases</a>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-4">
  <h4><i class="fa-solid fa-database me-2"></i>Databases — <?= e($account->username) ?></h4>
  <p class="text-muted small">Hosting ID <?= (int) $accountId ?> • Limit: <?= (int) $count ?> / <?= (int) $limit ?> • Status: <?= e($account->status) ?></p>

  <?php if ($account->status !== 'active'): ?>
    <div class="alert alert-warning">Database operations require active hosting.</div>
  <?php else: ?>
    <div class="card mb-4">
      <div class="card-body">
        <form method="POST" action="/hosting/<?= (int) $accountId ?>/databases" class="row g-2">
          <?= csrf_field() ?>
          <div class="col-md-8">
            <div class="input-group">
              <span class="input-group-text">fh_<?= (int) $accountId ?>_</span>
              <input name="name" class="form-control" placeholder="mydb" required pattern="[a-z][a-z0-9_]{2,29}">
            </div>
            <div class="form-text">3-30 chars, start with letter, a-z0-9_ only. Full name: fh_<?= (int) $accountId ?>_yourname (max 64).</div>
          </div>
          <div class="col-md-4"><button class="btn btn-primary w-100">Create Database</button></div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <?php if (empty($databases)): ?>
    <div class="alert alert-info">No databases yet. Create one above.</div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($databases as $db): ?>
        <div class="col-md-6 col-lg-4">
          <div class="card h-100">
            <div class="card-body">
              <h6 class="card-title"><i class="fa-solid fa-database me-1 text-primary"></i><?= e($db['name']) ?></h6>
              <p class="small text-muted mb-1">Charset: <?= e($db['charset']) ?> • Created: <?= e($db['created_at']) ?></p>
              <p class="small mb-2">Users: <?= count($db['users']) ?> • Status: <span class="badge bg-success"><?= e($db['status']) ?></span></p>
              <a href="/hosting/<?= (int) $accountId ?>/databases/<?= (int) $db['id'] ?>" class="btn btn-sm btn-outline-primary w-100">Manage</a>
              <form method="POST" action="/hosting/<?= (int) $accountId ?>/databases/delete" class="mt-2" onsubmit="return confirm('Delete database <?= e($db['name']) ?>? Users will also be removed.')">
                <?= csrf_field() ?>
                <input type="hidden" name="db_id" value="<?= (int) $db['id'] ?>">
                <button class="btn btn-sm btn-outline-danger w-100">Delete Database</button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="mt-4">
    <a href="/hosting/<?= (int) $accountId ?>" class="btn btn-secondary btn-sm">Back to Hosting</a>
    <a href="/hosting/<?= (int) $accountId ?>/files" class="btn btn-outline-secondary btn-sm ms-2">File Manager</a>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

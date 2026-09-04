<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container-fluid py-4">
  <h3>All Hosting Accounts <small class="text-muted"><?= (int) $total ?> total</small></h3>
  <form method="GET" class="row g-2 mb-3">
    <div class="col-auto"><input name="q" class="form-control" placeholder="Search username/email" value="<?= e($q) ?>"></div>
    <div class="col-auto"><button class="btn btn-outline-primary">Search</button></div>
  </form>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th>ID</th><th>Username</th><th>Owner</th><th>Plan</th><th>Status</th><th>Storage</th><th>Created</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($accounts as $a): ?>
            <?php
              // Fetch owner for display — quick query (N+1 ok for Phase2 small)
              $owner = (new App\Helpers\Database)->fetch ?? null;
            ?>
            <tr>
              <td><?= (int) $a->id ?></td>
              <td><?= e($a->username) ?></td>
              <td class="small"><?= e($a->userId) ?></td>
              <td><?= e($a->plan?->name ?? '?') ?></td>
              <td><span class="badge bg-<?= $a->status==='active'?'success':($a->status==='suspended'?'warning':'secondary') ?>"><?= e($a->status) ?></span></td>
              <td><?= e($a->storageUsedMb) ?>/<?= e($a->plan?->storageLimitMb ?? '?') ?> MB</td>
              <td class="small"><?= e($a->createdAt) ?></td>
              <td><a href="/admin/hosting/<?= (int) $a->id ?>" class="btn btn-sm btn-outline-secondary">View</a></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($accounts)): ?><tr><td colspan="8" class="text-muted p-3">No accounts.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($pages>1): ?>
      <div class="card-footer d-flex justify-content-between">
        <span>Page <?= (int) $page ?> of <?= (int) $pages ?></span>
        <div>
          <?php if ($page>1): ?><a href="?q=<?= e($q) ?>&page=<?= $page-1 ?>" class="btn btn-sm btn-outline-secondary">Prev</a><?php endif; ?>
          <?php if ($page<$pages): ?><a href="?q=<?= e($q) ?>&page=<?= $page+1 ?>" class="btn btn-sm btn-outline-secondary">Next</a><?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

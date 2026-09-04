<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container-fluid py-4">
  <h4>All Databases <small class="text-muted"><?= (int) $total ?> total</small></h4>
  <form method="GET" class="row g-2 mb-3">
    <div class="col-auto"><input name="q" class="form-control" placeholder="Search db/hosting" value="<?= e($q) ?>"></div>
    <div class="col-auto"><button class="btn btn-outline-primary">Search</button></div>
  </form>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th>ID</th><th>Database</th><th>Hosting</th><th>Owner ID</th><th>Users</th><th>Created</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= (int) $r['id'] ?></td>
              <td><code><?= e($r['name']) ?></code></td>
              <td><?= e($r['hosting_user']) ?> (<?= (int) $r['hosting_account_id'] ?>)</td>
              <td><?= (int) $r['user_id'] ?></td>
              <td><?= (int) $r['user_count'] ?></td>
              <td class="small"><?= e($r['created_at']) ?></td>
              <td><a href="/admin/databases/<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($rows)): ?><tr><td colspan="7" class="text-muted p-3">No databases.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($pages>1): ?>
      <div class="card-footer d-flex justify-content-between"><span>Page <?= (int) $page ?> of <?= (int) $pages ?></span><div><?php if($page>1): ?><a href="?q=<?= e($q) ?>&page=<?= $page-1 ?>" class="btn btn-sm btn-outline-secondary">Prev</a><?php endif; ?><?php if($page<$pages): ?><a href="?q=<?= e($q) ?>&page=<?= $page+1 ?>" class="btn btn-sm btn-outline-secondary">Next</a><?php endif; ?></div></div>
    <?php endif; ?>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

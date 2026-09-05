<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container-fluid py-4">
  <h4>Subscriptions <small class="text-muted"><?= (int) $total ?> total</small></h4>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th>ID</th><th>User</th><th>Plan</th><th>Hosting</th><th>Status</th><th>Period End</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= (int) $r['id'] ?></td>
              <td><?= e($r['user_email']) ?></td>
              <td><?= e($r['plan_name']) ?> (<?= (int) $r['plan_id'] ?>)</td>
              <td><?= $r['hosting_account_id'] ? (int) $r['hosting_account_id'] : '-' ?></td>
              <td><span class="badge bg-<?= $r['status']==='active'?'success':($r['status']==='past_due'?'warning':'secondary') ?>"><?= e($r['status']) ?></span></td>
              <td class="small"><?= e($r['current_period_end'] ?? '-') ?></td>
              <td>
                <form method="POST" action="/admin/billing/subscriptions/<?= (int) $r['id'] ?>/status" class="d-inline">
                  <?= csrf_field() ?>
                  <select name="status" class="form-select form-select-sm d-inline w-auto"><option value="active" <?= $r['status']==='active'?'selected':'' ?>>active</option><option value="past_due">past_due</option><option value="cancelled">cancelled</option><option value="expired">expired</option></select>
                  <button class="btn btn-sm btn-outline-primary">Update</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if(empty($rows)): ?><tr><td colspan="7" class="text-muted p-3">No subscriptions.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if($pages>1): ?><div class="card-footer d-flex justify-content-between"><span>Page <?= (int) $page ?> of <?= (int) $pages ?></span><div><?php if($page>1): ?><a href="?page=<?= $page-1 ?>" class="btn btn-sm btn-outline-secondary">Prev</a><?php endif; ?><?php if($page<$pages): ?><a href="?page=<?= $page+1 ?>" class="btn btn-sm btn-outline-secondary">Next</a><?php endif; ?></div></div><?php endif; ?>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container-fluid py-4">
  <h4>Invoices <small class="text-muted"><?= (int) $total ?> total</small></h4>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th>ID</th><th>User</th><th>Amount</th><th>Status</th><th>Provider Ref</th><th>Due</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= (int) $r['id'] ?></td>
              <td><?= e($r['user_email']) ?></td>
              <td><?= e(number_format($r['amount_cents']/100,2)) ?> <?= e($r['currency']) ?></td>
              <td><span class="badge bg-<?= $r['status']==='successful'?'success':($r['status']==='pending'?'warning':'secondary') ?>"><?= e($r['status']) ?></span></td>
              <td class="small font-monospace"><?= e($r['provider_ref'] ?? '-') ?></td>
              <td class="small"><?= e($r['due_date'] ?? '-') ?></td>
              <td>
                <form method="POST" action="/admin/billing/invoices/<?= (int) $r['id'] ?>/status" class="d-inline">
                  <?= csrf_field() ?>
                  <select name="status" class="form-select form-select-sm d-inline w-auto"><option value="pending">pending</option><option value="successful">successful</option><option value="failed">failed</option><option value="cancelled">cancelled</option><option value="refunded">refunded</option></select>
                  <button class="btn btn-sm btn-outline-primary">Update</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if(empty($rows)): ?><tr><td colspan="7" class="text-muted p-3">No invoices.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if($pages>1): ?><div class="card-footer d-flex justify-content-between"><span>Page <?= (int) $page ?> of <?= (int) $pages ?></span><div><?php if($page>1): ?><a href="?page=<?= $page-1 ?>" class="btn btn-sm btn-outline-secondary">Prev</a><?php endif; ?><?php if($page<$pages): ?><a href="?page=<?= $page+1 ?>" class="btn btn-sm btn-outline-secondary">Next</a><?php endif; ?></div></div><?php endif; ?>
  </div>
  <p class="small text-muted mt-2">Mock gateway — no card/CVV stored. Webhook verification via HMAC.</p>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

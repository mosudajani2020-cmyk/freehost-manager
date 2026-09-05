<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-4">
  <h4><i class="fa-solid fa-credit-card me-2"></i>Billing</h4>

  <div class="row g-3 mb-4">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header bg-white"><strong>Your Subscriptions (<?= count($subscriptions) ?>)</strong></div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>ID</th><th>Plan</th><th>Hosting</th><th>Status</th><th>Period End</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($subscriptions as $s): ?>
                <tr>
                  <td><?= (int) $s->id ?></td>
                  <td><?= (int) $s->planId ?></td>
                  <td><?= $s->hostingAccountId ? (int) $s->hostingAccountId : '-' ?></td>
                  <td><span class="badge bg-<?= $s->status==='active'?'success':($s->status==='past_due'?'warning':'secondary') ?>"><?= e($s->status) ?></span></td>
                  <td class="small"><?= e($s->currentPeriodEnd ?? '-') ?></td>
                  <td>
                    <?php if($s->status !== 'cancelled'): ?>
                      <form method="POST" action="/billing/cancel" onsubmit="return confirm('Cancel subscription?')"><?= csrf_field() ?><input type="hidden" name="subscription_id" value="<?= (int) $s->id ?>"><button class="btn btn-sm btn-outline-danger">Cancel</button></form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if(empty($subscriptions)): ?><tr><td colspan="6" class="text-muted p-3">No subscriptions.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header bg-white"><strong>Subscribe</strong></div>
        <div class="card-body">
          <form method="POST" action="/billing/subscribe">
            <?= csrf_field() ?>
            <div class="mb-2">
              <label class="form-label">Plan</label>
              <select name="plan_id" class="form-select" required>
                <?php foreach ($plans as $p): ?>
                  <option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?> — <?= e(number_format($p['priceCents']/100,2)) ?> <?= e($p['currency']) ?> (<?= e($p['status']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label">Hosting Account ID (optional)</label>
              <input name="hosting_account_id" class="form-control" placeholder="e.g. 1">
            </div>
            <button class="btn btn-primary w-100">Create Subscription (mock, no card)</button>
            <div class="form-text">Mock gateway — no card/CVV stored.</div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header bg-white"><strong>Your Invoices (<?= count($invoices) ?>)</strong></div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th>ID</th><th>Amount</th><th>Status</th><th>Due</th><th>Paid</th><th>Provider Ref</th></tr></thead>
        <tbody>
          <?php foreach ($invoices as $inv): ?>
            <tr>
              <td><?= (int) $inv->id ?></td>
              <td><?= e($inv->formattedAmount()) ?></td>
              <td><span class="badge bg-<?= $inv->status==='successful'?'success':($inv->status==='pending'?'warning':'secondary') ?>"><?= e($inv->status) ?></span></td>
              <td class="small"><?= e($inv->dueDate ?? '-') ?></td>
              <td class="small"><?= e($inv->paidAt ?? '-') ?></td>
              <td class="small font-monospace"><?= e($inv->providerRef ?? '-') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if(empty($invoices)): ?><tr><td colspan="6" class="text-muted p-3">No invoices.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <p class="small text-muted mt-2">Mock payment — webhook verification via HMAC `payload|signature|secret`.</p>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

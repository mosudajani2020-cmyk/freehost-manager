<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-4">
  <h4><i class="fa-solid fa-lock me-2"></i>SSL — <?= e($account->username) ?></h4>
  <p class="text-muted small">Hosting ID <?= (int) $accountId ?> • Mock Let's Encrypt (no real certs)</p>

  <div class="card mb-4">
    <div class="card-header bg-white"><strong>Request Certificate</strong></div>
    <div class="card-body">
      <form method="POST" action="/hosting/<?= (int) $accountId ?>/ssl/request" class="row g-2">
        <?= csrf_field() ?>
        <div class="col-md-8"><input name="hostname" class="form-control" placeholder="sub.freehost.example" required pattern="[a-z0-9\.\-]{3,253}"></div>
        <div class="col-md-4"><button class="btn btn-primary w-100">Request SSL (mock)</button></div>
      </form>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header bg-white"><strong>Your Certificates (<?= count($certificates) ?>)</strong></div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th>Hostname</th><th>Status</th><th>Provider</th><th>Expires</th><th>Auto Renew</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($certificates as $c): ?>
            <tr>
              <td><code><?= e($c->hostname) ?></code></td>
              <td><span class="badge bg-<?= $c->status==='active'?'success':($c->status==='failed'?'danger':'warning') ?>"><?= e($c->status) ?></span></td>
              <td class="small"><?= e($c->provider) ?></td>
              <td class="small"><?= e($c->expiresAt ?? '-') ?></td>
              <td><?= $c->autoRenew ? 'Yes' : 'No' ?></td>
              <td class="d-flex gap-1">
                <form method="POST" action="/hosting/<?= (int) $accountId ?>/ssl/renew"><?= csrf_field() ?><input type="hidden" name="cert_id" value="<?= (int) $c->id ?>"><button class="btn btn-sm btn-outline-primary">Renew</button></form>
                <form method="POST" action="/hosting/<?= (int) $accountId ?>/ssl/revoke" onsubmit="return confirm('Revoke <?= e($c->hostname) ?>?')"><?= csrf_field() ?><input type="hidden" name="cert_id" value="<?= (int) $c->id ?>"><button class="btn btn-sm btn-outline-danger">Revoke</button></form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if(empty($certificates)): ?><tr><td colspan="6" class="text-muted p-3">No certificates. Subdomains auto-create SSL (mock).</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header bg-white"><strong>Subdomains SSL Status</strong></div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th>Subdomain</th><th>SSL Status</th><th>Expires</th></tr></thead>
        <tbody>
          <?php foreach ($subdomains as $s): ?>
            <tr><td><code><?= e($s['full_domain']) ?></code></td><td><span class="badge bg-<?= $s['ssl_status']==='active'?'success':'warning' ?>"><?= e($s['ssl_status']) ?></span></td><td class="small"><?= e($s['ssl_expires_at'] ?? '-') ?></td></tr>
          <?php endforeach; ?>
          <?php if(empty($subdomains)): ?><tr><td colspan="3" class="text-muted p-3">No subdomains.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-3"><a href="/hosting/<?= (int) $accountId ?>" class="btn btn-secondary btn-sm">Back</a><a href="/hosting/<?= (int) $accountId ?>/dns" class="btn btn-outline-secondary btn-sm ms-2">DNS</a></div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

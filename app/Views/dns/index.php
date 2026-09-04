<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-4">
  <h4><i class="fa-solid fa-network-wired me-2"></i>DNS — <?= e($account->username) ?></h4>
  <p class="text-muted small">Hosting ID <?= (int) $accountId ?> • Domain: <?= e($account->domain ?? 'not assigned') ?> • Main: <?= e($_ENV['APP_DOMAIN'] ?? 'freehost.example') ?></p>

  <div class="card mb-4">
    <div class="card-header bg-white"><strong>Create DNS Record</strong> <span class="small text-muted">(mock, no real DNS)</span></div>
    <div class="card-body">
      <form method="POST" action="/hosting/<?= (int) $accountId ?>/dns" class="row g-2">
        <?= csrf_field() ?>
        <div class="col-md-4"><input name="hostname" class="form-control" placeholder="sub.freehost.example" required pattern="[a-z0-9\.\-]{3,253}"></div>
        <div class="col-md-2">
          <select name="type" class="form-select"><option>A</option><option>CNAME</option><option>TXT</option><option>MX</option><option>AAAA</option></select>
        </div>
        <div class="col-md-3"><input name="value" class="form-control" placeholder="127.0.0.1 or cname" required></div>
        <div class="col-md-1"><input type="number" name="ttl" class="form-control" value="3600" min="60" max="86400"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Create</button></div>
      </form>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header bg-white"><strong>Your DNS Records (<?= count($records) ?>)</strong></div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th>Hostname</th><th>Type</th><th>Value</th><th>TTL</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($records as $r): ?>
            <tr>
              <td><code><?= e($r->hostname) ?></code></td>
              <td><?= e($r->type) ?></td>
              <td class="small"><?= e($r->value) ?></td>
              <td><?= (int) $r->ttl ?></td>
              <td><span class="badge bg-<?= $r->status==='active'?'success':($r->status==='failed'?'danger':'secondary') ?>"><?= e($r->status) ?></span></td>
              <td>
                <form method="POST" action="/hosting/<?= (int) $accountId ?>/dns/delete" onsubmit="return confirm('Delete DNS <?= e($r->hostname) ?>?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="record_id" value="<?= (int) $r->id ?>">
                  <button class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if(empty($records)): ?><tr><td colspan="6" class="text-muted p-3">No DNS records yet. Subdomains automatically create A record.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header bg-white"><strong>Your Subdomains & DNS Status</strong></div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th>Subdomain</th><th>DNS Status</th><th>SSL Status</th></tr></thead>
        <tbody>
          <?php foreach ($subdomains as $s): ?>
            <tr>
              <td><code><?= e($s['full_domain']) ?></code></td>
              <td><span class="badge bg-<?= $s['dns_status']==='active'?'success':'warning' ?>"><?= e($s['dns_status']) ?></span></td>
              <td><span class="badge bg-<?= $s['ssl_status']==='active'?'success':'warning' ?>"><?= e($s['ssl_status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
          <?php if(empty($subdomains)): ?><tr><td colspan="3" class="text-muted p-3">No subdomains yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-3">
    <a href="/hosting/<?= (int) $accountId ?>" class="btn btn-secondary btn-sm">Back to Hosting</a>
    <a href="/hosting/<?= (int) $accountId ?>/ssl" class="btn btn-outline-secondary btn-sm ms-2">SSL</a>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

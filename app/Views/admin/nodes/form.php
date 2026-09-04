<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-4">
  <h4>Create Hosting Node</h4>
  <p class="text-muted small">API key is generated and shown once (hashed stored, preview saved). Use secure secret management in production.</p>
  <div class="card">
    <div class="card-body">
      <form method="POST" action="/admin/nodes">
        <?= csrf_field() ?>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Name</label>
            <input name="name" class="form-control <?= isset($errors['name'])?'is-invalid':'' ?>" value="<?= e($node->name ?? '') ?>" required pattern="[a-z0-9\-]{2,50}">
            <?php if(isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
          </div>
          <div class="col-md-6">
            <label class="form-label">Hostname</label>
            <input name="hostname" class="form-control <?= isset($errors['hostname'])?'is-invalid':'' ?>" value="<?= e($node->hostname ?? '') ?>" required>
            <?php if(isset($errors['hostname'])): ?><div class="invalid-feedback"><?= e($errors['hostname']) ?></div><?php endif; ?>
          </div>
          <div class="col-md-4">
            <label class="form-label">IP</label>
            <input name="ip_address" class="form-control" value="<?= e($node->ip_address ?? '') ?>" placeholder="127.0.0.1">
            <?php if(isset($errors['ip_address'])): ?><div class="text-danger small"><?= e($errors['ip_address']) ?></div><?php endif; ?>
          </div>
          <div class="col-md-4">
            <label class="form-label">Region</label>
            <input name="region" class="form-control" value="<?= e($node->region ?? '') ?>" placeholder="local">
          </div>
          <div class="col-md-4">
            <label class="form-label">Max Accounts</label>
            <input type="number" name="max_accounts" class="form-control" value="<?= e($node->max_accounts ?? '100') ?>" min="1" max="10000" required>
          </div>
          <div class="col-md-12">
            <label class="form-label">API URL (provisioning service)</label>
            <input name="api_url" class="form-control" value="<?= e($node->api_url ?? '') ?>" placeholder="https://node.example/api/provision">
            <div class="form-text">For local mock, use http://localhost/mock-provisioning. Production: restricted worker API with HMAC.</div>
          </div>
        </div>
        <button class="btn btn-primary mt-3">Create Node</button>
        <a href="/admin/nodes" class="btn btn-secondary mt-3 ms-2">Cancel</a>
      </form>
    </div>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

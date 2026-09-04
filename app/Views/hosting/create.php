<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-4">
  <h3>Create Hosting Account</h3>
  <p class="text-muted">Choose a plan and username. Provisioning is local mock only.</p>
  <div class="card">
    <div class="card-body p-4">
      <form method="POST" action="/hosting">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label">Username (system user)</label>
          <input type="text" name="username" class="form-control <?= isset($errors['username'])?'is-invalid':'' ?>" value="<?= e($old['username'] ?? '') ?>" required pattern="[a-z0-9]{3,32}">
          <div class="form-text">3-32 chars, lowercase a-z0-9 only. Must be unique.</div>
          <?php if (isset($errors['username'])): ?><div class="invalid-feedback"><?= e($errors['username']) ?></div><?php endif; ?>
          <?php if (isset($errors['plan_id'])): ?><div class="text-danger small"><?= e($errors['plan_id']) ?></div><?php endif; ?>
        </div>
        <div class="mb-3">
          <label class="form-label">Hosting Plan</label>
          <select name="plan_id" class="form-select" required>
            <option value="">— Select plan —</option>
            <?php foreach ($plans as $p): ?>
              <option value="<?= (int) $p->id ?>" <?= (isset($old['plan_id']) && (int)$old['plan_id']===$p->id)?'selected':'' ?>>
                <?= e($p->name) ?> — <?= (int) $p->storageLimitMb ?> MB / <?= (int) $p->bandwidthLimitMb ?> MB / DB <?= (int) $p->databaseLimit ?> / Sub <?= (int) $p->subdomainLimit ?>  — <?= e($p->status) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary">Create Account</button>
        <a href="/hosting" class="btn btn-secondary ms-2">Cancel</a>
      </form>
    </div>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

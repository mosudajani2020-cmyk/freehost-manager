<?php require APP_PATH . '/Views/partials/header.php'; ?>
<?php
$isEdit = $plan && isset($plan->id);
$action = $isEdit ? '/admin/plans/' . (int) $plan->id : '/admin/plans';
?>
<div class="container py-4">
  <h3><?= $isEdit ? 'Edit Plan' : 'Create Plan' ?></h3>
  <div class="card">
    <div class="card-body p-4">
      <form method="POST" action="<?= e($action) ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Name</label>
            <input name="name" class="form-control <?= isset($errors['name'])?'is-invalid':'' ?>" value="<?= e($plan->name ?? '') ?>" required>
            <?php if(isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
          </div>
          <div class="col-md-6">
            <label class="form-label">Slug</label>
            <input name="slug" class="form-control <?= isset($errors['slug'])?'is-invalid':'' ?>" value="<?= e($plan->slug ?? '') ?>" required pattern="[a-z0-9\-]{2,50}">
            <div class="form-text">Lowercase, hyphens.</div>
            <?php if(isset($errors['slug'])): ?><div class="invalid-feedback"><?= e($errors['slug']) ?></div><?php endif; ?>
          </div>
          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control"><?= e($plan->description ?? '') ?></textarea>
          </div>
          <div class="col-md-4">
            <label class="form-label">Storage MB</label>
            <input type="number" name="storage_limit_mb" class="form-control <?= isset($errors['storage_limit_mb'])?'is-invalid':'' ?>" value="<?= e($plan->storage_limit_mb ?? $plan->storageLimitMb ?? '') ?>" required min="0" max="1000000">
            <?php if(isset($errors['storage_limit_mb'])): ?><div class="invalid-feedback"><?= e($errors['storage_limit_mb']) ?></div><?php endif; ?>
          </div>
          <div class="col-md-4">
            <label class="form-label">Bandwidth MB</label>
            <input type="number" name="bandwidth_limit_mb" class="form-control <?= isset($errors['bandwidth_limit_mb'])?'is-invalid':'' ?>" value="<?= e($plan->bandwidth_limit_mb ?? $plan->bandwidthLimitMb ?? '') ?>" required min="0">
            <?php if(isset($errors['bandwidth_limit_mb'])): ?><div class="invalid-feedback"><?= e($errors['bandwidth_limit_mb']) ?></div><?php endif; ?>
          </div>
          <div class="col-md-4">
            <label class="form-label">Database limit</label>
            <input type="number" name="database_limit" class="form-control <?= isset($errors['database_limit'])?'is-invalid':'' ?>" value="<?= e($plan->database_limit ?? $plan->databaseLimit ?? '') ?>" required min="0" max="1000">
          </div>
          <div class="col-md-4">
            <label class="form-label">Domain limit</label>
            <input type="number" name="domain_limit" class="form-control" value="<?= e($plan->domain_limit ?? $plan->domainLimit ?? '') ?>" required min="0">
          </div>
          <div class="col-md-4">
            <label class="form-label">Subdomain limit</label>
            <input type="number" name="subdomain_limit" class="form-control" value="<?= e($plan->subdomain_limit ?? $plan->subdomainLimit ?? '') ?>" required min="0">
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="active" <?= (($plan->status ?? 'active')==='active'?'selected':'') ?>>active</option>
              <option value="inactive" <?= (($plan->status ?? '')==='inactive'?'selected':'') ?>>inactive</option>
            </select>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input type="checkbox" name="is_default" value="1" class="form-check-input" id="is_default" <?= !empty($plan->is_default ?? $plan->isDefault ?? false)?'checked':'' ?>>
              <label class="form-check-label" for="is_default">Default plan</label>
            </div>
          </div>
        </div>
        <button class="btn btn-primary mt-3"><?= $isEdit?'Update':'Create' ?></button>
        <a href="/admin/plans" class="btn btn-secondary mt-3 ms-2">Cancel</a>
      </form>
    </div>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

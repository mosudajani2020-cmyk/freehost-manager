<?php
$errors = $flash['errors'] ?? [];
?>
<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
      <div class="card">
        <div class="card-body p-4 p-md-5">
          <h4 class="mb-3">Reset password</h4>
          <form method="POST" action="/reset-password">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
            <div class="mb-3">
              <label class="form-label">New password</label>
              <input type="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" required>
              <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= e($errors['password']) ?></div><?php endif; ?>
            </div>
            <div class="mb-3">
              <label class="form-label">Confirm password</label>
              <input type="password" name="password_confirm" class="form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>" required>
              <?php if (isset($errors['password_confirm'])): ?><div class="invalid-feedback"><?= e($errors['password_confirm']) ?></div><?php endif; ?>
            </div>
            <button class="btn btn-primary w-100">Reset password</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

<?php
$errors = $flash['errors'] ?? [];
$old = $old ?? [];
?>
<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
      <div class="card">
        <div class="card-body p-4 p-md-5">
          <h3 class="mb-1">Create account</h3>
          <p class="text-muted mb-4">Register for FreeHost Manager</p>
          <form method="POST" action="/register" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
              <label class="form-label" for="full_name">Full name</label>
              <input type="text" id="full_name" name="full_name" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" value="<?= e($old['full_name'] ?? '') ?>" required autofocus>
              <?php if (isset($errors['full_name'])): ?><div class="invalid-feedback"><?= e($errors['full_name']) ?></div><?php endif; ?>
            </div>
            <div class="mb-3">
              <label class="form-label" for="username">Username</label>
              <input type="text" id="username" name="username" class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>" value="<?= e($old['username'] ?? '') ?>" required pattern="[a-z0-9_\.]{3,50}">
              <div class="form-text">Lowercase letters, numbers, underscore, dot (3–50 chars)</div>
              <?php if (isset($errors['username'])): ?><div class="invalid-feedback"><?= e($errors['username']) ?></div><?php endif; ?>
            </div>
            <div class="mb-3">
              <label class="form-label" for="email">Email</label>
              <input type="email" id="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" value="<?= e($old['email'] ?? '') ?>" required>
              <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= e($errors['email']) ?></div><?php endif; ?>
            </div>
            <div class="mb-3">
              <label class="form-label" for="password">Password</label>
              <input type="password" id="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" required>
              <div class="form-text">Min 8 chars, uppercase, lowercase, digit</div>
              <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= e($errors['password']) ?></div><?php endif; ?>
            </div>
            <div class="mb-3">
              <label class="form-label" for="password_confirm">Confirm password</label>
              <input type="password" id="password_confirm" name="password_confirm" class="form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>" required>
              <?php if (isset($errors['password_confirm'])): ?><div class="invalid-feedback"><?= e($errors['password_confirm']) ?></div><?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary w-100">Create account</button>
          </form>
          <hr class="my-4">
          <p class="text-center mb-0">Already have an account? <a href="/login">Login</a></p>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

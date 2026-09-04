<?php
$errors = $flash['errors'] ?? [];
$old = $old ?? [];
?>
<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
      <div class="card">
        <div class="card-body p-4 p-md-5">
          <h3 class="mb-1">Welcome back</h3>
          <p class="text-muted mb-4">Login to your hosting dashboard</p>
          <form method="POST" action="/login" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
              <label class="form-label" for="login">Username or email</label>
              <input type="text" id="login" name="login" class="form-control <?= isset($errors['login']) ? 'is-invalid' : '' ?>" value="<?= e($old['login'] ?? '') ?>" required autofocus>
              <?php if (isset($errors['login'])): ?><div class="invalid-feedback"><?= e($errors['login']) ?></div><?php endif; ?>
            </div>
            <div class="mb-3">
              <label class="form-label" for="password">Password</label>
              <input type="password" id="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" required>
              <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= e($errors['password']) ?></div><?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
          </form>
          <div class="d-flex justify-content-between mt-3">
            <a href="/forgot-password">Forgot password?</a>
            <a href="/register">Create account</a>
          </div>
        </div>
      </div>
      <div class="alert alert-info mt-3">
        <strong>Admin creation:</strong> Run <code>php scripts/create-admin.php</code> from CLI.
      </div>
    </div>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

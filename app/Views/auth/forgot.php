<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
      <div class="card">
        <div class="card-body p-4 p-md-5">
          <h4 class="mb-3">Forgot password</h4>
          <p class="text-muted">Enter your email. If an account exists, a reset link will be sent.</p>
          <form method="POST" action="/forgot-password">
            <?= csrf_field() ?>
            <div class="mb-3">
              <label class="form-label" for="email">Email</label>
              <input type="email" id="email" name="email" class="form-control" required>
            </div>
            <button class="btn btn-primary w-100">Send reset link</button>
          </form>
          <p class="text-center mt-3 mb-0"><a href="/login">Back to login</a></p>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

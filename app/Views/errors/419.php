<?php http_response_code(419); ?>
<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-5 text-center">
  <h1>419 — Page Expired</h1>
  <p class="text-muted">CSRF token mismatch. Please refresh and try again.</p>
  <a href="javascript:history.back()" class="btn btn-primary">Go back</a>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

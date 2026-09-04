<?php http_response_code(500); ?>
<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-5 text-center">
  <h1>500 — Internal Server Error</h1>
  <p class="text-muted">Something went wrong. Check storage/logs/app.log for details.</p>
  <a href="/" class="btn btn-primary">Go home</a>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

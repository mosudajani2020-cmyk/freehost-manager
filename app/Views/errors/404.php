<?php http_response_code(404); ?>
<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-5 text-center">
  <h1>404 — Not Found</h1>
  <p class="text-muted">The page you requested could not be found.</p>
  <a href="/" class="btn btn-primary">Go home</a>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

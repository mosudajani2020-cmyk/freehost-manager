<?php http_response_code(403); ?>
<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-5 text-center">
  <h1>403 — Forbidden</h1>
  <p class="text-muted">You do not have permission to access this resource.</p>
  <a href="/dashboard" class="btn btn-primary">Dashboard</a>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

<footer class="mt-5 py-4 text-center text-muted small">
  <div class="container">
    <p class="mb-0">&copy; <?= date('Y') ?> <?= e($_ENV['APP_NAME'] ?? 'FreeHost Manager') ?> — Secure hosting control panel. Phases 1–7 complete.</p>
    <p class="mb-0">Env: <?= e($_ENV['APP_ENV'] ?? 'production') ?> | PHP <?= e(PHP_VERSION) ?></p>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

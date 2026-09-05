<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-4">
  <h4>System Settings</h4>
  <p class="text-muted small">Only non-secret settings are editable here. Secrets remain in <code>.env</code>.</p>
  <div class="card">
    <div class="card-body">
      <form method="POST" action="/admin/settings">
        <?= csrf_field() ?>
        <table class="table table-sm">
          <thead><tr><th>Key</th><th>Value</th><th>Type</th></tr></thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><code><?= e($r['key']) ?></code></td>
                <td><input name="settings[<?= e($r['key']) ?>]" class="form-control form-control-sm" value="<?= e($r['value'] ?? '') ?>"></td>
                <td class="small"><?= e($r['type']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <button class="btn btn-primary">Save Settings</button>
        <a href="/admin/dashboard" class="btn btn-secondary ms-2">Cancel</a>
      </form>
    </div>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

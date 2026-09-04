<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-4">
  <h4>Create Text File in <?= e($currentPath ?: '/') ?></h4>
  <nav aria-label="breadcrumb"><ol class="breadcrumb small"><li class="breadcrumb-item"><a href="/hosting/<?= (int) $accountId ?>/files">/</a></li><li class="breadcrumb-item active">Create</li></ol></nav>
  <?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <div class="card">
    <div class="card-body">
      <form method="POST" action="/hosting/<?= (int) $accountId ?>/files/create">
        <?= csrf_field() ?>
        <input type="hidden" name="path" value="<?= e($currentPath) ?>">
        <div class="mb-3">
          <label class="form-label">Filename</label>
          <input name="filename" class="form-control" value="<?= e($old['filename'] ?? '') ?>" placeholder="example.txt" required pattern="[a-zA-Z0-9._\-]{1,64}">
          <div class="form-text">Allowed: txt, html, css, js, json, xml, md, htaccess, php, ini, etc. — sanitized server-side.</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Content (optional)</label>
          <textarea name="content" class="form-control font-monospace" rows="12"><?= e($old['content'] ?? '') ?></textarea>
        </div>
        <button class="btn btn-primary">Create</button>
        <a href="/hosting/<?= (int) $accountId ?>/files<?= $currentPath ? '?path=' . urlencode($currentPath) : '' ?>" class="btn btn-secondary ms-2">Cancel</a>
      </form>
    </div>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

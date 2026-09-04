<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container-fluid py-3">
  <h4>Edit: <?= e(basename($path)) ?> <small class="text-muted"><?= e(dirname($path) === '.' ? '/' : dirname($path)) ?></small></h4>
  <nav aria-label="breadcrumb"><ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="/hosting/<?= (int) $accountId ?>/files">/</a></li>
    <?php foreach ($breadcrumbs as $i=>$crumb): if($i===0) continue; ?>
      <li class="breadcrumb-item <?= $i===count($breadcrumbs)-1?'active':'' ?>"><?= e($crumb['name']) ?></li>
    <?php endforeach; ?>
  </ol></nav>
  <?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <div class="card">
    <div class="card-body">
      <form method="POST" action="/hosting/<?= (int) $accountId ?>/files/edit">
        <?= csrf_field() ?>
        <input type="hidden" name="path" value="<?= e($path) ?>">
        <div class="mb-3">
          <textarea name="content" class="form-control font-monospace" rows="20" style="font-family:monospace; font-size:13px"><?= e($content) ?></textarea>
          <div class="form-text">Max 512KB, text only, escaped display. Saving is atomic.</div>
        </div>
        <button class="btn btn-primary">Save</button>
        <a href="/hosting/<?= (int) $accountId ?>/files<?= dirname($path) && dirname($path)!='.' ? '?path=' . urlencode(dirname($path)) : '' ?>" class="btn btn-secondary ms-2">Back</a>
      </form>
    </div>
  </div>
  <p class="small text-muted mt-2">Content is escaped for display; raw file saved on disk. No execution via control panel.</p>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

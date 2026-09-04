<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h4 class="mb-0"><i class="fa-solid fa-folder-open me-2"></i>File Manager — <?= e($account->username) ?> <span class="badge bg-<?= $account->status==='active'?'success':'warning' ?>"><?= e($account->status) ?></span></h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <?php foreach ($breadcrumbs as $i=>$crumb): ?>
            <li class="breadcrumb-item <?= $i===count($breadcrumbs)-1?'active':'' ?>">
              <?php if ($i===count($breadcrumbs)-1): ?><?= e($crumb['name']) ?>
              <?php else: ?><a href="/hosting/<?= (int) $accountId ?>/files<?= $crumb['path'] ? '?path=' . urlencode($crumb['path']) : '' ?>"><?= e($crumb['name']) ?></a><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      </nav>
      <small class="text-muted">Showing: <?= e($relative) ?> &nbsp;|&nbsp; Physical path hidden (isolated)</small>
    </div>
    <div>
      <a href="/hosting/<?= (int) $accountId ?>" class="btn btn-sm btn-outline-secondary">Back to Hosting</a>
    </div>
  </div>

  <!-- Quota -->
  <div class="card mb-3">
    <div class="card-body py-2">
      <div class="row align-items-center">
        <div class="col-md-8">
          <div class="d-flex justify-content-between small mb-1">
            <span>Storage: <?= e($quota['usedMb']) ?> / <?= e($quota['limitMb']) ?> MB used (<?= e($quota['percent']) ?>%)</span>
            <span>Remaining: <?= e($quota['remainingMb']) ?> MB</span>
          </div>
          <div class="progress" style="height:8px">
            <div class="progress-bar <?= $quota['percent']>90?'bg-danger':($quota['percent']>70?'bg-warning':'bg-success') ?>" style="width: <?= (float) $quota['percent'] ?>%"></div>
          </div>
        </div>
        <div class="col-md-4 text-end small text-muted">
          Plan: <?= e($account->plan?->name ?? '?') ?> • <?= e($account->plan?->storageLimitMb ?? '?') ?> MB limit
        </div>
      </div>
    </div>
  </div>

  <!-- Actions -->
  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3">
          <form method="POST" action="/hosting/<?= (int) $accountId ?>/files/mkdir">
            <?= csrf_field() ?>
            <input type="hidden" name="path" value="<?= e(trim($relative,'/')) ?>">
            <div class="input-group input-group-sm">
              <input name="dirname" class="form-control" placeholder="New folder" required pattern="[a-zA-Z0-9._\-]{1,64}">
              <button class="btn btn-outline-primary">Create Folder</button>
            </div>
          </form>
        </div>
        <div class="col-md-4">
          <form method="POST" action="/hosting/<?= (int) $accountId ?>/files/upload" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="path" value="<?= e(trim($relative,'/')) ?>">
            <div class="input-group input-group-sm">
              <input type="file" name="file" class="form-control" required>
              <button class="btn btn-primary">Upload</button>
            </div>
          </form>
        </div>
        <div class="col-md-3">
          <a href="/hosting/<?= (int) $accountId ?>/files/create<?= $relative && $relative!=='/' ? '?path=' . urlencode(trim($relative,'/')) : '' ?>" class="btn btn-sm btn-outline-secondary w-100">+ Create Text File</a>
        </div>
        <div class="col-md-2 text-end">
          <small class="text-muted"><?= count($items) ?> items</small>
        </div>
      </div>
    </div>
  </div>

  <!-- File list -->
  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover table-sm mb-0">
        <thead><tr><th>Name</th><th>Type</th><th>Size</th><th>Modified</th><th>Actions</th></tr></thead>
        <tbody>
          <?php if ($relative !== '/' && $relative !== ''): ?>
            <?php
              $parent = dirname(trim($relative,'/'));
              if ($parent === '.' ) $parent = '';
              $parentQ = $parent ? '?path=' . urlencode($parent) : '';
            ?>
            <tr><td colspan="5"><a href="/hosting/<?= (int) $accountId ?>/files<?= $parentQ ?>"><i class="fa-solid fa-turn-up me-1"></i>.. (parent)</a></td></tr>
          <?php endif; ?>
          <?php foreach ($items as $it): ?>
            <tr>
              <td>
                <?php if ($it['type']==='dir'): ?>
                  <a href="/hosting/<?= (int) $accountId ?>/files?path=<?= urlencode($it['relative']) ?>"><i class="fa-solid fa-folder text-warning me-1"></i><?= e($it['name']) ?></a>
                <?php else: ?>
                  <i class="fa-solid fa-file me-1 text-muted"></i><?= e($it['name']) ?>
                  <?php if (in_array($it['ext'], ['txt','html','css','js','json','md','php','htaccess'], true)): ?>
                    <a href="/hosting/<?= (int) $accountId ?>/files/edit?path=<?= urlencode($it['relative']) ?>" class="badge bg-info text-decoration-none ms-1">Edit</a>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
              <td class="small"><?= e($it['type']) ?><?= $it['ext'] ? ' ('.e($it['ext']).')' : '' ?></td>
              <td class="small"><?= e($it['size_human']) ?></td>
              <td class="small"><?= e($it['mtime_human']) ?></td>
              <td class="small">
                <?php if ($it['type']==='file'): ?>
                  <a href="/hosting/<?= (int) $accountId ?>/files/download?path=<?= urlencode($it['relative']) ?>" class="btn btn-sm btn-outline-primary py-0">Download</a>
                <?php endif; ?>
                <button class="btn btn-sm btn-outline-secondary py-0" data-bs-toggle="modal" data-bs-target="#rename-<?= md5($it['name']) ?>">Rename</button>
                <button class="btn btn-sm btn-outline-danger py-0" data-bs-toggle="modal" data-bs-target="#delete-<?= md5($it['name']) ?>">Delete</button>

                <!-- Rename modal -->
                <div class="modal fade" id="rename-<?= md5($it['name']) ?>" tabindex="-1">
                  <div class="modal-dialog modal-sm">
                    <form method="POST" action="/hosting/<?= (int) $accountId ?>/files/rename">
                      <?= csrf_field() ?>
                      <input type="hidden" name="path" value="<?= e($it['relative']) ?>">
                      <input type="hidden" name="current_path" value="<?= e(trim($relative,'/')) ?>">
                      <div class="modal-content">
                        <div class="modal-header"><h6 class="modal-title">Rename <?= e($it['name']) ?></h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body"><input name="new_name" class="form-control" value="<?= e($it['name']) ?>" required pattern="[a-zA-Z0-9._\-]{1,64}"></div>
                        <div class="modal-footer"><button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary btn-sm">Rename</button></div>
                      </div>
                    </form>
                  </div>
                </div>
                <!-- Delete modal -->
                <div class="modal fade" id="delete-<?= md5($it['name']) ?>" tabindex="-1">
                  <div class="modal-dialog modal-sm">
                    <form method="POST" action="/hosting/<?= (int) $accountId ?>/files/delete">
                      <?= csrf_field() ?>
                      <input type="hidden" name="path" value="<?= e($it['relative']) ?>">
                      <input type="hidden" name="current_path" value="<?= e(trim($relative,'/')) ?>">
                      <div class="modal-content">
                        <div class="modal-header"><h6 class="modal-title">Delete <?= e($it['name']) ?></h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body small">Are you sure? <?= $it['type']==='dir' ? 'Directory and contents will be removed.' : '' ?></div>
                        <div class="modal-footer"><button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger btn-sm">Delete</button></div>
                      </div>
                    </form>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($items)): ?><tr><td colspan="5" class="text-muted p-3">Empty directory.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <p class="small text-muted mt-2">Isolation: all operations are jailed to your hosting root via PathGuard. No shell execution. Text files limited to 512KB edit.</p>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

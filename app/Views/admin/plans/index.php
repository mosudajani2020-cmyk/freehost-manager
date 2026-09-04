<?php require APP_PATH . '/Views/partials/header.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Hosting Plans</h3>
    <a href="/admin/plans/create" class="btn btn-primary">Create Plan</a>
  </div>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Name</th><th>Slug</th><th>Storage</th><th>BW</th><th>DB</th><th>Domains</th><th>Subs</th><th>Status</th><th>Default</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($plans as $p): ?>
            <tr>
              <td><?= e($p->name) ?></td>
              <td class="small"><?= e($p->slug) ?></td>
              <td><?= (int) $p->storageLimitMb ?> MB</td>
              <td><?= (int) $p->bandwidthLimitMb ?> MB</td>
              <td><?= (int) $p->databaseLimit ?></td>
              <td><?= (int) $p->domainLimit ?></td>
              <td><?= (int) $p->subdomainLimit ?></td>
              <td><span class="badge bg-<?= $p->status==='active'?'success':'secondary' ?>"><?= e($p->status) ?></span></td>
              <td><?= $p->isDefault?'Yes':'' ?></td>
              <td><a href="/admin/plans/<?= (int) $p->id ?>/edit" class="btn btn-sm btn-outline-secondary">Edit</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'FreeHost Manager') ?> — <?= e($_ENV['APP_NAME'] ?? 'FreeHost Manager') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<style>
body { background:#f8f9fa; }
.navbar-brand { font-weight:700; }
.card { border:none; box-shadow:0 2px 8px rgba(0,0,0,.06); }
.sidebar { min-height:calc(100vh - 56px); background:#fff; border-right:1px solid #e9ecef; }
.sidebar .nav-link { color:#495057; }
.sidebar .nav-link.active { background:#0d6efd; color:#fff; border-radius:.5rem; }
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="/"><i class="fa-solid fa-server me-2"></i><?= e($_ENV['APP_NAME'] ?? 'FreeHost Manager') ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav ms-auto">
        <?php if (!empty($_SESSION['user_id'])): ?>
          <?php $firstHostingId = null; ?>
          <li class="nav-item"><a class="nav-link" href="/dashboard"><i class="fa-solid fa-gauge me-1"></i>Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="/hosting"><i class="fa-solid fa-server me-1"></i>Hosting</a></li>
          <?php if (in_array('admin', $_SESSION['user_roles'] ?? [], true)): ?>
            <li class="nav-item"><a class="nav-link" href="/admin/dashboard"><i class="fa-solid fa-shield-halved me-1"></i>Admin</a></li>
          <?php endif; ?>
          <li class="nav-item">
            <form method="POST" action="/logout" class="d-inline">
              <?= csrf_field() ?>
              <button class="btn btn-outline-light btn-sm ms-2">Logout (<?= e($_SESSION['user_roles'][0] ?? 'user') ?>)</button>
            </form>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="/login">Login</a></li>
          <li class="nav-item"><a class="nav-link" href="/register">Register</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<?php
// Flash messages
$flash = $flash ?? ($_SESSION['_flash'] ?? []);
if (isset($flash['success'])): ?>
  <div class="container mt-3"><div class="alert alert-success alert-dismissible fade show" role="alert"><?= e($flash['success']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div></div>
<?php endif; ?>
<?php if (isset($flash['error'])): ?>
  <div class="container mt-3"><div class="alert alert-danger alert-dismissible fade show" role="alert"><?= e($flash['error']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div></div>
<?php endif; ?>

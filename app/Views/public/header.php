<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'FreeHost Manager') ?> &mdash; <?= e($_ENV['APP_NAME'] ?? 'FreeHost Manager') ?></title>
<meta name="description" content="FreeHost Manager is a secure web hosting control panel for managing hosting accounts, files, databases, domains, DNS, SSL, usage, backups, monitoring, provisioning and billing.">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='6' fill='%2310b981'/><path fill='%23052e22' d='M8 10h16v3H8zM8 15h16v3H8zM8 20h10v3H8z'/></svg>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="/assets/css/landing.css" rel="stylesheet">
</head>
<body class="fhm-public">
<a href="#fhm-main" class="skip-link">Skip to main content</a>
<nav class="navbar navbar-expand-lg fhm-nav" aria-label="Primary">
  <div class="container">
    <a class="navbar-brand" href="#">
      <span class="fhm-brand-mark" aria-hidden="true"><i class="fa-solid fa-server"></i></span>
      FreeHost Manager
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#fhmNav" aria-controls="fhmNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="fhmNav">
      <ul class="navbar-nav ms-auto align-items-lg-center">
        <li class="nav-item"><a class="nav-link active" href="#">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="#fhm-features">Features</a></li>
        <li class="nav-item"><a class="nav-link" href="#fhm-hosting">Hosting</a></li>
        <li class="nav-item"><a class="nav-link" href="#fhm-security">Security</a></li>
        <li class="nav-item"><a class="nav-link" href="#fhm-how">How It Works</a></li>
        <li class="nav-item ms-lg-2"><a class="btn btn-fhm-ghost" href="/login">Sign In</a></li>
        <li class="nav-item"><a class="btn btn-fhm-primary" href="/register">Create Free Account</a></li>
      </ul>
    </div>
  </div>
</nav>
<main id="fhm-main">
<?php
// Public flash messages (only on landing — empty by default)
$flash = $flash ?? ($_SESSION['_flash_public'] ?? []);
unset($_SESSION['_flash_public']);
if (isset($flash['success'])): ?>
  <div class="container mt-3"><div class="alert alert-success alert-dismissible fade show" role="alert"><?= e($flash['success']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div></div>
<?php endif; ?>
<?php if (isset($flash['error'])): ?>
  <div class="container mt-3"><div class="alert alert-danger alert-dismissible fade show" role="alert"><?= e($flash['error']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div></div>
<?php endif; ?>

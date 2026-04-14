<?php /** @var string $title */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow,noarchive,nosnippet">
<title><?= htmlspecialchars($title ?? 'Foot Traffic Analytics') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root {
  --ft-bg:#f4efe6; --ft-card:#faf6ee; --ft-ink:#2b2519;
  --ft-brown:#6b4f2b; --ft-olive:#5a6b3a; --ft-rust:#a0522d; --ft-sand:#d9c9a3;
}
body { background: var(--ft-bg); color: var(--ft-ink); font-family: Arial, sans-serif; }
.navbar { background: var(--ft-brown) !important; }
.navbar .navbar-brand, .navbar .nav-link { color: var(--ft-card) !important; }
.navbar .nav-link:hover { color: var(--ft-sand) !important; }
.card { background: var(--ft-card); border: 1px solid var(--ft-sand); }
.btn-primary { background: var(--ft-olive); border-color: var(--ft-olive); }
.btn-primary:hover { background: var(--ft-brown); border-color: var(--ft-brown); }
.btn-outline-primary { color: var(--ft-olive); border-color: var(--ft-olive); }
.btn-outline-primary:hover { background: var(--ft-olive); border-color: var(--ft-olive); }
a { color: var(--ft-rust); }
.score-pill { display:inline-block; padding:2px 8px; border-radius:10px; background: var(--ft-sand); font-weight:bold; }
.table thead th { background: var(--ft-sand); }
</style>
</head>
<body>
<?php if (Auth::check()): ?>
<nav class="navbar navbar-expand-lg navbar-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?= BASE_URL ?>/dashboard">Foot Traffic Analytics</a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/dashboard">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/listings">Listings</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/tracks">Tracks</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/blacklist">Blacklist</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/applications">Applications</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/submit">+ Submit</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/runs">Scraper Runs</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/settings">Settings</a></li>
      </ul>
      <span class="navbar-text text-white-50 me-3"><?= htmlspecialchars(Auth::name()) ?></span>
      <?php if (empty($_SESSION['ip_bypass'])): ?>
      <form method="post" action="<?= BASE_URL ?>/logout" class="d-inline">
        <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
        <button class="btn btn-sm btn-outline-light">Logout</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</nav>
<?php endif; ?>
<main class="container-fluid py-4">
<?php $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); ?>
<?php if ($flash): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
<?php endif; ?>
<?= $content ?>
</main>
</body>
</html>

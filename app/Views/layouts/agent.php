<?php use App\Core\Auth; ?><!doctype html>
<html lang="fr"><head>
<meta charset="utf-8">
<meta name="base-path" content="<?= e(base_path()) ?>"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="robots" content="noindex,nofollow">
<title><?= e($title ?? 'Missions') ?> — <?= e(setting('company_name')) ?></title>
<meta name="theme-color" content="#0B2545">
<link rel="manifest" href="/agent.webmanifest">
<link rel="icon" type="image/png" href="/assets/icons/icon-192.png"><link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap.min.css') ?>"><link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="agent">
<header class="agent-top">
  <a class="brand" href="/agent"><span class="brand-drop" aria-hidden="true"></span>Missions</a>
  <form method="post" action="/deconnexion" class="ms-auto"><?= csrf_field() ?><button class="btn btn-sm btn-outline-light">Quitter</button></form>
</header>
<main class="agent-main">
  <?php if (!empty($flash_ok)): ?><div class="alert alert-success" role="status"><?= e($flash_ok) ?></div><?php endif ?>
  <?php if (!empty($flash_err)): ?><div class="alert alert-danger" role="alert"><?= e($flash_err) ?></div><?php endif ?>
  <?= $content ?>
</main>
<script src="<?= asset('vendor/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body></html>

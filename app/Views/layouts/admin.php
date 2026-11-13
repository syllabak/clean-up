<?php
use App\Core\Auth;
$path = \App\Core\Request::path();
// Neuf entrées pour le travail de tous les jours ; tout le reste vit dans « Configuration ».
// [libellé, adresse, droit(s) (un seul suffit), préfixes d'adresses qui gardent l'entrée active]
$cfg = \App\Controllers\Admin\ConfigController::PERMS;
$nav = [
  ['Tableau de bord', '/admin', ['dashboard.view'], []],
  ['Réservations', '/admin/reservations', ['bookings.view'], []],
  ['Planning', '/admin/planning', ['planning.view'], []],
  ['Clients', '/admin/crud/customers', ['customers.manage'], ['/admin/clients']],
  ['Prospects', '/admin/crm', ['crm.manage'], ['/admin/crud/leads']],
  ['Recrutement', '/admin/recrutement', ['recruitment.manage'], []],
  ['Marketing', '/admin/marketing', ['marketing.manage'], ['/admin/crud/acquisition_channels']],
  ['Gestion', '/admin/gestion', ['finance.manage'], ['/admin/crud/expenses']],
  ['Configuration', '/admin/configuration', $cfg, ['/admin/parametres', '/admin/audit', '/admin/notifications', '/admin/services/', '/admin/crud/']],
];
$elsewhere = ['/admin/crud/customers', '/admin/crud/leads', '/admin/crud/acquisition_channels', '/admin/crud/expenses'];   // écrans de /admin/crud/ qui ont leur propre entrée
$isActive = function (string $href, array $also) use ($path, $elsewhere): bool {
    if ($href === '/admin') return $path === '/admin';
    if (str_starts_with($path, $href)) return true;
    foreach ($also as $p) {
        if (!str_starts_with($path, $p)) continue;
        if ($p === '/admin/crud/' && array_filter($elsewhere, fn($e) => str_starts_with($path, $e))) continue;
        return true;
    }
    return false;
};
$u = Auth::user();
?><!doctype html>
<html lang="fr"><head>
<meta charset="utf-8">
<meta name="base-path" content="<?= e(base_path()) ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?= e($title ?? 'Administration') ?> — <?= e(setting('company_name')) ?></title>
<link rel="icon" type="image/png" href="/assets/icons/icon-192.png">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap.min.css') ?>"><link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<?php foreach ((array) ($page_css ?? []) as $css): ?><link rel="stylesheet" href="<?= asset($css) ?>"><?php endforeach ?>
</head>
<body class="admin">
<div class="admin-shell">
  <aside class="admin-side" id="side">
    <a class="brand brand-admin" href="/admin"><span class="brand-drop" aria-hidden="true"></span><?= e(setting('company_name')) ?></a>
    <nav aria-label="Navigation principale">
      <?php foreach ($nav as [$label, $href, $perms, $also]): if (!array_filter($perms, fn($pm) => Auth::can($pm))) continue; $active = $isActive($href, $also); ?>
        <a class="side-link <?= $active ? 'active' : '' ?>" href="<?= e($href) ?>" <?= $active ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach ?>
    </nav>
  </aside>
  <div class="admin-main">
    <header class="admin-top">
      <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" data-toggle-side aria-controls="side">Menu</button>
      <?php if (Auth::can('dashboard.view')): ?><form method="get" action="/admin/recherche" class="admin-search" role="search"><input class="form-control form-control-sm" type="search" name="q" placeholder="Rechercher : référence, nom, téléphone…" aria-label="Rechercher" value="<?= e(\App\Core\Request::str('q')) ?>"></form><?php endif ?>
      <span class="ms-auto small text-muted"><?= e($u['name'] ?? '') ?> · <?= e($u['role_name'] ?? '') ?></span>
      <a class="btn btn-sm btn-outline-secondary" href="/" target="_blank" rel="noopener">Voir le site</a>
      <form method="post" action="/deconnexion" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Déconnexion</button></form>
    </header>
    <div class="admin-content">
      <?php if (!empty($flash_ok)): ?><div class="alert alert-success" role="status"><?= e($flash_ok) ?></div><?php endif ?>
      <?php if (!empty($flash_err)): ?><div class="alert alert-danger" role="alert"><?= e($flash_err) ?></div><?php endif ?>
      <?= $content ?>
    </div>
  </div>
</div>
<script src="<?= asset('vendor/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<?php foreach ((array) ($page_js ?? []) as $js): ?><script src="<?= asset(str_contains($js, '/') ? $js : 'js/' . $js) ?>"></script><?php endforeach ?>
</body></html>

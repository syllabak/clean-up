<?php
use App\Core\Auth;
$path = \App\Core\Request::path();
$nav = [
  ['Tableau de bord', '/admin', 'dashboard.view'], ['Réservations', '/admin/reservations', 'bookings.view'], ['Planning', '/admin/planning', 'planning.view'],
  ['Services', '/admin/crud/services', 'catalog.manage'], ['Catégories', '/admin/crud/service_categories', 'catalog.manage'],
  ['Zones : quartiers', '/admin/crud/neighborhoods', 'geo.manage'], ['Zones : régions', '/admin/crud/regions', 'geo.manage'],
  ['Équipes', '/admin/crud/teams', 'staff.manage'], ['Agents', '/admin/crud/staff', 'staff.manage'], ['Compétences', '/admin/crud/skills', 'staff.manage'], ['Congés et fermetures', '/admin/crud/time_off', 'staff.manage'],
  ['Clients', '/admin/crud/customers', 'customers.manage'],
  ['Journal des envois', '/admin/notifications', 'notifications.manage'], ['Modèles de messages', '/admin/crud/notifications', 'notifications.manage'],
  ['Messages reçus', '/admin/crud/contact_messages', 'cms.manage'], ['Pages', '/admin/crud/pages', 'cms.manage'], ['FAQ', '/admin/crud/faq', 'cms.manage'], ['Témoignages', '/admin/crud/testimonials', 'cms.manage'],
  ['Utilisateurs', '/admin/crud/users', 'users.manage'], ["Journal d'audit", '/admin/audit', 'users.manage'], ['Paramètres', '/admin/parametres', 'settings.manage'],
];
$u = Auth::user();
?><!doctype html>
<html lang="fr"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?= e($title ?? 'Administration') ?> — <?= e(setting('company_name')) ?></title>
<link rel="icon" type="image/png" href="/assets/icons/icon-192.png">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap.min.css') ?>"><link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="admin">
<div class="admin-shell">
  <aside class="admin-side" id="side">
    <a class="brand brand-admin" href="/admin"><span class="brand-drop" aria-hidden="true"></span><?= e(setting('company_name')) ?></a>
    <nav aria-label="Navigation principale">
      <?php foreach ($nav as [$label, $href, $perm]): if (!Auth::can($perm)) continue;
        $active = $href === '/admin' ? $path === '/admin' : str_starts_with($path, $href); ?>
        <a class="side-link <?= $active ? 'active' : '' ?>" href="<?= e($href) ?>" <?= $active ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach ?>
    </nav>
  </aside>
  <div class="admin-main">
    <header class="admin-top">
      <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" data-toggle-side aria-controls="side">Menu</button>
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
<?php if (!empty($page_js)): ?><script src="<?= asset('js/' . $page_js) ?>"></script><?php endif ?>
</body></html>

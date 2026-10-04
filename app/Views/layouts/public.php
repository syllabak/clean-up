<?php
$company = setting('company_name', "Net'Express");
$wa = setting('company_whatsapp', '');
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? $company) ?></title>
<meta name="description" content="<?= e($meta ?? 'Nettoyage à domicile et au bureau : lavage auto, canapés, matelas, tapis, maison, bureaux. Réservation en ligne, équipes mobiles.') ?>">
<?php if (!empty($noindex)): ?><meta name="robots" content="noindex"><?php endif ?>
<meta name="theme-color" content="#0B2545">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" type="image/png" href="/assets/icons/icon-192.png">
<link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="site">
<a class="skip" href="#main">Aller au contenu</a>
<header class="site-header">
  <nav class="navbar navbar-expand-lg container">
    <a class="brand" href="/"><span class="brand-drop" aria-hidden="true"></span><?= e($company) ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Menu"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
        <li class="nav-item"><a class="nav-link" href="/services">Services</a></li>
        <li class="nav-item"><a class="nav-link" href="/faq">Questions</a></li>
        <li class="nav-item"><a class="nav-link" href="/contact">Contact</a></li>
        <li class="nav-item"><a class="nav-link" href="/suivi">Suivre ma réservation</a></li>
        <li class="nav-item ms-lg-2"><a class="btn btn-lemon" href="/reserver">Réserver</a></li>
      </ul>
    </div>
  </nav>
</header>
<main id="main">
  <?= View_flash($flash_ok ?? null, $flash_err ?? null) ?>
  <?= $content ?>
</main>
<footer class="site-footer">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-5">
        <p class="brand mb-2"><span class="brand-drop" aria-hidden="true"></span><?= e($company) ?></p>
        <p class="mb-1">Nettoyage mobile à Dakar et environs.</p>
        <p class="mb-0 small opacity-75"><?= e(setting('company_address')) ?></p>
      </div>
      <div class="col-6 col-md-3">
        <p class="fw-semibold mb-2">Nous joindre</p>
        <p class="mb-1"><a href="tel:<?= e(preg_replace('/[^\d+]/', '', (string) setting('company_phone'))) ?>"><?= e(setting('company_phone')) ?></a></p>
        <p class="mb-1"><a href="mailto:<?= e(setting('company_email')) ?>"><?= e(setting('company_email')) ?></a></p>
        <p class="small opacity-75 mb-0"><?= e(setting('opening_text')) ?></p>
      </div>
      <div class="col-6 col-md-4">
        <p class="fw-semibold mb-2">Informations</p>
        <p class="mb-1"><a href="/a-propos">À propos</a></p>
        <p class="mb-1"><a href="/conditions-generales">Conditions générales</a></p>
        <p class="mb-1"><a href="/confidentialite">Confidentialité</a></p>
        <p class="mb-0"><a href="/connexion">Espace équipe</a></p>
      </div>
    </div>
  </div>
</footer>
<?php if ($wa !== ''): ?>
<a class="wa-float" href="https://wa.me/<?= e(preg_replace('/\D/', '', $wa)) ?>" target="_blank" rel="noopener" aria-label="Écrire sur WhatsApp">WhatsApp</a>
<?php endif ?>
<script src="<?= asset('vendor/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<?php if (!empty($page_js)): ?><script src="<?= asset('js/' . $page_js) ?>"></script><?php endif ?>
</body>
</html>

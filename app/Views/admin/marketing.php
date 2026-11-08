<div class="d-flex flex-wrap align-items-center gap-2 mb-3"><h1 class="mb-0 me-auto">Marketing</h1>
  <a class="btn btn-primary" href="/admin/marketing/campagnes/nouveau">Nouvelle campagne</a>
  <a class="btn btn-outline-primary" href="/admin/marketing/liens">Liens suivis</a>
  <?php if ($can_settings): ?><a class="btn btn-outline-secondary" href="/admin/marketing/canaux">Configurer les canaux</a><?php endif ?></div>

<div class="row g-3 mb-3">
  <?php foreach ($status as [$name, $state, $on]): ?>
  <div class="col-6 col-lg"><div class="panel h-100 mb-0"><div class="small text-muted"><?= e($name) ?></div>
    <div class="fw-semibold"><span class="badge text-bg-<?= $on ? 'success' : 'secondary' ?>"><?= $on ? 'Actif' : 'À configurer' ?></span></div>
    <div class="small mt-1"><?= e($state) ?></div></div></div>
  <?php endforeach ?>
</div>

<div class="row g-3">
  <div class="col-lg-7"><div class="panel"><h2>Dernières campagnes</h2>
    <?php if (!$campaigns): ?><p class="text-muted mb-0">Aucune campagne pour l'instant.</p><?php endif ?>
    <?php foreach ($campaigns as $c): [$l, $col] = \App\Services\MarketingService::STATUSES[$c['status']] ?? [$c['status'], 'light']; ?>
      <div class="d-flex align-items-center gap-2 py-1 border-bottom"><a class="me-auto" href="/admin/marketing/campagnes/<?= (int) $c['id'] ?>"><?= e($c['name']) ?></a>
        <span class="small text-muted"><?= e(\App\Services\MarketingService::CHANNELS[$c['channel']] ?? $c['channel']) ?></span><span class="badge text-bg-<?= e($col) ?>"><?= e($l) ?></span></div>
    <?php endforeach ?>
    <a class="small" href="/admin/marketing/campagnes">Toutes les campagnes</a></div></div>
  <div class="col-lg-5"><div class="panel"><h2>Public joignable</h2>
    <p class="display-6 mb-0"><?= (int) $consenting ?></p><p class="small text-muted">clients sur <?= (int) $customers ?> ont accepté de recevoir des offres. Le consentement se coche sur la fiche du client.</p>
    <h2 class="mt-3">Publicité : derniers événements</h2>
    <?php foreach ($events as $ev): ?><div class="small border-bottom py-1"><span class="badge text-bg-<?= $ev['status'] === 'ok' ? 'success' : 'danger' ?>"><?= e($ev['status']) ?></span> <?= e($ev['provider']) ?> · <?= e($ev['event']) ?> · <?= e($ev['ref']) ?><?= $ev['error'] ? '<br><span class="text-danger">' . e($ev['error']) . '</span>' : '' ?></div><?php endforeach ?>
    <?php if (!$events): ?><p class="small text-muted mb-0">Aucun événement envoyé à Meta pour l'instant.</p><?php endif ?></div></div>
</div>

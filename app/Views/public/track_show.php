<div class="page-head"><div class="container"><p class="mb-1"><a href="/suivi">← Autre réservation</a></p><h1>Réservation <?= e($b['reference']) ?></h1></div></div>
<div class="container section" style="max-width:820px">
  <div class="panel">
    <?php $flow = ['pending' => 'Reçue', 'confirmed' => 'Confirmée', 'assigned' => 'Équipe affectée', 'en_route' => 'En route', 'in_progress' => 'En intervention', 'completed' => 'Terminée'];
          $idx = array_search($b['status'], array_keys($flow), true); ?>
    <?php if ($b['status'] === 'cancelled'): ?><p class="mb-2"><?= status_badge('cancelled') ?></p>
    <?php else: ?><ol class="wiz-progress mb-3" aria-label="Avancement de la réservation"><?php $i = 0; foreach ($flow as $k => $lab): ?><li class="<?= $i < $idx ? 'done' : ($i === $idx ? 'cur' : '') ?>" <?= $i === $idx ? 'aria-current="step"' : '' ?>><?= e($lab) ?></li><?php $i++; endforeach ?></ol><?php endif ?>
    <p class="mb-1"><strong><?= e($b['service_name']) ?></strong> — <?= e(fmt_date($b['scheduled_date'])) ?> à <?= e($b['start_time']) ?></p>
    <p class="mb-1">Adresse : <?= e($b['address_text']) ?> (<?= e($b['neighborhood_name']) ?>)</p>
    <?php if ($b['team_name'] && in_array($b['status'], ['assigned', 'en_route', 'in_progress', 'completed'], true)): ?><p class="mb-1">Équipe : <?= e($b['team_name']) ?></p><?php endif ?>
    <?php foreach ($items as $it): foreach (json_decode((string) $it['details'], true) ?: [] as [$k, $v]): ?><p class="mb-0 small text-muted"><?= e($k) ?> : <?= e($v) ?></p><?php endforeach; endforeach ?>
    <hr><p class="mb-0">Total estimé : <strong><?= money((int) $b['total']) ?></strong></p>
  </div>
  <?php $wa = preg_replace('/\D/', '', (string) setting('company_whatsapp', '')); ?>
  <p class="d-flex flex-wrap gap-2"><?php if (!in_array($b['status'], ['cancelled', 'completed'], true)): ?><a class="btn btn-sm btn-outline-primary" href="/suivi/<?= e($b['reference']) ?>/calendrier">Ajouter à mon agenda</a><?php endif ?>
    <?php if (!empty($rebook)): ?><a class="btn btn-sm btn-primary" href="<?= e($rebook) ?>">Réserver à nouveau</a><?php endif ?>
    <?php if ($wa): ?><a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Bonjour, ma réservation ' . $b['reference']) ?>">Écrire sur WhatsApp</a><?php endif ?></p>
  <h2 class="h5">Historique</h2>
  <ul class="timeline mb-4"><?php foreach ($history as $h): ?><li><?= status_badge($h['to_status']) ?> <span class="small text-muted"><?= e(date('d/m/Y H:i', strtotime($h['created_at'] . ' UTC'))) ?></span></li><?php endforeach ?></ul>
  <?php if ($can_cancel): ?>
  <form method="post" action="/suivi/<?= e($b['reference']) ?>/annuler" data-confirm="Annuler définitivement cette réservation ?"><?= csrf_field() ?><button class="btn btn-outline-danger">Annuler ma réservation</button></form>
  <p class="small text-muted mt-2">Pour changer la date ou l'heure, appelez-nous au <?= e(setting('company_phone')) ?>.</p>
  <?php elseif (in_array($b['status'], ['pending', 'confirmed'], true)): ?>
  <p class="text-muted">L'annulation en ligne n'est plus possible à moins de <?= (int) setting('cancel_hours_before', '12') ?> h de l'intervention. Appelez-nous au <?= e(setting('company_phone')) ?>.</p>
  <?php endif ?>
</div>

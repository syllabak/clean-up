<div class="page-head"><div class="container"><p class="mb-1"><a href="/suivi">← Autre réservation</a></p><h1>Réservation <?= e($b['reference']) ?></h1></div></div>
<div class="container section" style="max-width:820px">
  <div class="panel">
    <p class="mb-2"><?= status_badge($b['status']) ?></p>
    <p class="mb-1"><strong><?= e($b['service_name']) ?></strong> — <?= e(fmt_date($b['scheduled_date'])) ?> à <?= e($b['start_time']) ?></p>
    <p class="mb-1">Adresse : <?= e($b['address_text']) ?> (<?= e($b['neighborhood_name']) ?>)</p>
    <?php if ($b['team_name'] && in_array($b['status'], ['assigned', 'en_route', 'in_progress', 'completed'], true)): ?><p class="mb-1">Équipe : <?= e($b['team_name']) ?></p><?php endif ?>
    <?php foreach ($items as $it): foreach (json_decode((string) $it['details'], true) ?: [] as [$k, $v]): ?><p class="mb-0 small text-muted"><?= e($k) ?> : <?= e($v) ?></p><?php endforeach; endforeach ?>
    <hr><p class="mb-0">Total estimé : <strong><?= money((int) $b['total']) ?></strong></p>
  </div>
  <h2 class="h5">Historique</h2>
  <ul class="timeline mb-4"><?php foreach ($history as $h): ?><li><?= status_badge($h['to_status']) ?> <span class="small text-muted"><?= e(date('d/m/Y H:i', strtotime($h['created_at'] . ' UTC'))) ?></span></li><?php endforeach ?></ul>
  <?php if ($can_cancel): ?>
  <form method="post" action="/suivi/<?= e($b['reference']) ?>/annuler" data-confirm="Annuler définitivement cette réservation ?"><?= csrf_field() ?><button class="btn btn-outline-danger">Annuler ma réservation</button></form>
  <p class="small text-muted mt-2">Pour changer la date ou l'heure, appelez-nous au <?= e(setting('company_phone')) ?>.</p>
  <?php elseif (in_array($b['status'], ['pending', 'confirmed'], true)): ?>
  <p class="text-muted">L'annulation en ligne n'est plus possible à moins de <?= (int) setting('cancel_hours_before', '12') ?> h de l'intervention. Appelez-nous au <?= e(setting('company_phone')) ?>.</p>
  <?php endif ?>
</div>

<h1>Tableau de bord</h1>
<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3"><div class="kpi"><div class="n"><?= $stats['today'] ?></div><div class="l">interventions aujourd'hui</div></div></div>
  <div class="col-6 col-lg-3"><a class="text-decoration-none" href="/admin/reservations?status=pending"><div class="kpi <?= $stats['pending'] ? 'warn' : '' ?>"><div class="n"><?= $stats['pending'] ?></div><div class="l">à confirmer</div></div></a></div>
  <div class="col-6 col-lg-3"><div class="kpi"><div class="n"><?= $stats['week'] ?></div><div class="l">sur les 7 prochains jours</div></div></div>
  <div class="col-6 col-lg-3"><a class="text-decoration-none" href="/admin/notifications?status=failed"><div class="kpi <?= $stats['failed_notifs'] ? 'bad' : '' ?>"><div class="n"><?= $stats['failed_notifs'] ?></div><div class="l">envois en échec</div></div></a></div>
</div>
<?php if ($revenue_on): ?>
<div class="row g-3 mb-3">
  <div class="col-md-6"><div class="kpi"><div class="n"><?= money($stats['declared_month']) ?></div><div class="l">encaissé ce mois (saisi sur les réservations terminées)</div></div></div>
  <div class="col-md-6"><div class="kpi"><div class="n"><?= money($stats['estimated_month']) ?></div><div class="l">estimé ce mois (réservations non annulées)</div></div></div>
</div>
<?php endif ?>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="panel"><h2>À affecter dans les 3 prochains jours</h2>
      <?php if (!$unassigned): ?><p class="text-muted mb-0">Rien à affecter. 🎉</p><?php else: ?>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Quand</th><th>Client</th><th>Service</th><th>Quartier</th><th></th></tr></thead><tbody>
        <?php foreach ($unassigned as $b): ?><tr><td class="text-nowrap"><?= e(date('d/m', strtotime($b['scheduled_date']))) ?> <?= e($b['start_time']) ?></td><td><?= e($b['customer_first_name'] . ' ' . $b['customer_last_name']) ?></td><td><?= e($b['service_name']) ?></td><td><?= e($b['neighborhood_name']) ?></td><td><a class="btn btn-sm btn-outline-primary" href="/admin/reservations/<?= (int) $b['id'] ?>">Ouvrir</a></td></tr><?php endforeach ?>
      </tbody></table></div><?php endif ?>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="panel"><h2>Charge des équipes aujourd'hui</h2>
      <?php foreach ($load as $l): $pct = $l['capacity'] ? min(100, (int) round($l['count'] / $l['capacity'] * 100)) : 0; ?>
        <div class="mb-3"><div class="d-flex justify-content-between"><strong><?= e($l['team']['name']) ?></strong><span class="small text-muted"><?= $l['off'] || !$l['hours'] ? 'Ne travaille pas' : $l['count'] . ' / ' . $l['capacity'] . ' · libre ' . e(fmt_duration($l['free'])) ?></span></div>
        <div class="loadbar"><i class="<?= $pct >= 100 ? 'full' : '' ?>" style="width:<?= $pct ?>%"></i></div></div>
      <?php endforeach ?>
      <a href="/admin/planning">Ouvrir le planning</a>
    </div>
    <div class="panel"><h2>Réservations du mois par statut</h2>
      <?php foreach (BOOKING_STATUSES as $k => [$l]): ?><div class="d-flex justify-content-between py-1 border-bottom"><span><?= status_badge($k) ?></span><strong><?= (int) ($by_status[$k] ?? 0) ?></strong></div><?php endforeach ?>
    </div>
    <?php if ($stats['messages']): ?><p><a href="/admin/crud/contact_messages"><?= $stats['messages'] ?> message(s) du site à traiter</a></p><?php endif ?>
  </div>
</div>

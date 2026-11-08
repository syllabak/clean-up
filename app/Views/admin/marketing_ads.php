<?php $pct = fn($v) => $v === null ? '—' : $v . ' %'; $lab = fn(string $s) => $s === '' ? '—' : $s; ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
  <h1 class="mb-0 me-auto">Résultats publicitaires</h1>
  <form method="get" class="d-flex flex-wrap gap-1 align-items-center"><input class="form-control form-control-sm w-auto" type="date" name="from" value="<?= e($from) ?>" aria-label="Du"><span>→</span><input class="form-control form-control-sm w-auto" type="date" name="to" value="<?= e($to) ?>" aria-label="Au"><button class="btn btn-sm btn-outline-primary">Afficher</button></form>
</div>
<p class="text-muted small">Réservations venues d'une annonce, d'un lien suivi ou d'une campagne, du <?= e(date('d/m/Y', strtotime($from))) ?> au <?= e(date('d/m/Y', strtotime($to))) ?>. Chiffre d'affaires = réservations terminées. Les dépenses viennent de « Dépenses », rattachées à un canal.</p>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3"><div class="kpi"><div class="n"><?= (int) $total['bookings'] ?></div><div class="l">réservations publicitaires</div></div></div>
  <div class="col-6 col-lg-3"><div class="kpi"><div class="n"><?= money($total['revenue']) ?></div><div class="l">chiffre d'affaires</div></div></div>
  <div class="col-6 col-lg-3"><div class="kpi"><div class="n"><?= money($total['spend']) ?></div><div class="l">dépensé en publicité</div></div></div>
  <div class="col-6 col-lg-3"><div class="kpi <?= $total['spend'] > $total['revenue'] && $total['spend'] > 0 ? 'bad' : '' ?>"><div class="n"><?= $total['spend'] > 0 ? $pct((int) round(($total['revenue'] - $total['spend']) / $total['spend'] * 100)) : '—' ?></div><div class="l">retour sur investissement</div></div></div>
</div>

<div class="panel"><h2>Par plateforme</h2>
  <?php if (!$platforms): ?><p class="text-muted small mb-0">Aucune réservation ni dépense sur la période. Mettez un <a href="/admin/marketing/liens">lien suivi</a> dans vos annonces.</p><?php else: ?>
  <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Plateforme</th><th class="text-end">Réservations</th><th class="text-end">Clients</th><th class="text-end">CA</th><th class="text-end">Dépensé</th><th class="text-end">Coût / réservation</th><th class="text-end">ROI</th></tr></thead><tbody>
    <?php foreach ($platforms as $p): ?><tr><td><?= e($p['name']) ?></td><td class="text-end"><?= (int) $p['bookings'] ?></td><td class="text-end"><?= (int) $p['customers'] ?></td><td class="text-end text-nowrap"><?= money($p['revenue']) ?></td>
      <td class="text-end text-nowrap"><?= $p['spend'] ? money($p['spend']) : '—' ?></td><td class="text-end text-nowrap"><?= $p['cost_per_booking'] === null ? '—' : money($p['cost_per_booking']) ?></td>
      <td class="text-end"><?= $p['roi'] === null ? '—' : '<span class="' . ($p['roi'] < 0 ? 'text-danger' : 'text-success') . '">' . $pct($p['roi']) . '</span>' ?></td></tr><?php endforeach ?>
  </tbody></table></div>
  <p class="small text-muted mt-2 mb-0">Une plateforme avec des dépenses mais aucune réservation s'affiche quand même : c'est l'argent à surveiller.</p><?php endif ?></div>

<div class="panel"><h2>Par annonce ou campagne</h2>
  <?php if (!$ads): ?><p class="text-muted small mb-0">Aucune origine enregistrée sur la période.</p><?php else: ?>
  <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Source</th><th>Type</th><th>Campagne</th><th class="text-end">Prospects</th><th class="text-end">Réservations</th><th class="text-end">CA</th><th class="text-end">Estimé (tout)</th></tr></thead><tbody>
    <?php foreach ($ads as $a): ?><tr><td><?= e($lab($a['source'])) ?></td><td><?= e($lab($a['medium'])) ?><?= $a['internal'] ? ' <span class="badge text-bg-info">envoi interne</span>' : '' ?></td><td><?= e($lab($a['campaign'])) ?></td>
      <td class="text-end"><?= (int) $a['leads'] ?></td><td class="text-end"><?= (int) $a['bookings'] ?></td><td class="text-end text-nowrap"><?= money($a['revenue']) ?></td><td class="text-end text-nowrap"><?= money($a['estimated']) ?></td></tr><?php endforeach ?>
  </tbody></table></div>
  <p class="small text-muted mt-2 mb-0">Le nom de la campagne vient du lien suivi (<code>utm_campaign</code>). Les dépenses se saisissent par plateforme, pas par annonce : le coût par annonce n'est donc pas calculé.</p><?php endif ?></div>

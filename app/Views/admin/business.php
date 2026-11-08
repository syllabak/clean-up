<?php use App\Services\BusinessReportService as B; $pct = fn($v) => $v === null ? '—' : $v . ' %'; $maxM = max(1, ...array_map(fn($m) => max($m['revenue'], $m['expenses']), $months)); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <h1 class="mb-0 me-auto">Gestion et résultats</h1>
  <form method="get" class="d-flex flex-wrap gap-1 align-items-center"><input class="form-control form-control-sm w-auto" type="date" name="from" value="<?= e($from) ?>" aria-label="Du"><span>→</span><input class="form-control form-control-sm w-auto" type="date" name="to" value="<?= e($to) ?>" aria-label="Au"><button class="btn btn-sm btn-outline-secondary">Afficher</button></form>
  <a class="btn btn-sm btn-outline-primary" href="/admin/crud/expenses">Toutes les dépenses</a>
  <a class="btn btn-sm btn-primary" href="/admin/crud/expenses/nouveau">Saisir une dépense</a>
</div>
<p class="text-muted small">Chiffre d'affaires = réservations terminées (montant saisi, à défaut montant estimé). Période : <?= e(date('d/m/Y', strtotime($from))) ?> au <?= e(date('d/m/Y', strtotime($to))) ?>.</p>
<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3"><div class="kpi"><div class="n"><?= money($profit['revenue']) ?></div><div class="l">chiffre d'affaires</div></div></div>
  <div class="col-6 col-lg-3"><div class="kpi"><div class="n"><?= money($profit['expenses']) ?></div><div class="l">dépenses</div></div></div>
  <div class="col-6 col-lg-3"><div class="kpi <?= $profit['profit'] < 0 ? 'bad' : '' ?>"><div class="n"><?= money($profit['profit']) ?></div><div class="l">résultat (marge <?= $pct($profit['margin']) ?>)</div></div></div>
  <div class="col-6 col-lg-3"><div class="kpi"><div class="n"><?= money($profit['to_come']) ?></div><div class="l">à venir (réservations en cours)</div></div></div>
</div>
<div class="row g-3">
  <div class="col-12"><div class="panel"><h2>Acquisition par canal</h2>
    <?php if (!$acq): ?><p class="text-muted small mb-0">Aucune donnée sur la période.</p><?php else: ?>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Canal</th><th class="text-end">Prospects</th><th class="text-end">Conv.</th><th class="text-end">Nouv. clients</th><th class="text-end">Résa</th><th class="text-end">CA</th><th class="text-end">Dépenses</th><th class="text-end">Coût / client</th><th class="text-end">ROI</th></tr></thead><tbody>
      <?php foreach ($acq as $r): ?><tr><td><?= e($r['name']) ?></td><td class="text-end text-nowrap"><?= $r['leads'] ?></td><td class="text-end text-nowrap"><?= $pct($r['lead_rate']) ?></td><td class="text-end text-nowrap"><?= $r['customers'] ?></td><td class="text-end text-nowrap"><?= $r['bookings'] ?></td>
        <td class="text-end text-nowrap"><?= money($r['revenue']) ?></td><td class="text-end text-nowrap"><?= $r['spend'] ? money($r['spend']) : '—' ?></td><td class="text-end text-nowrap"><?= $r['cac'] === null ? '—' : money($r['cac']) ?></td><td class="text-end text-nowrap"><?= $pct($r['roi']) ?></td></tr><?php endforeach ?>
    </tbody></table></div>
    <p class="small text-muted mt-2 mb-0">Le coût par client et le ROI se calculent à partir des dépenses rattachées à un canal (menu Dépenses).</p><?php endif ?>
  </div></div>
</div>
<div class="row g-3">
  <div class="col-lg-7"><div class="panel"><h2>6 derniers mois</h2>
    <?php foreach ($months as $m): ?><div class="mb-2"><div class="d-flex justify-content-between small"><strong><?= e($m['month']) ?></strong><span>CA <?= money($m['revenue']) ?> · dépenses <?= money($m['expenses']) ?> · <strong class="<?= $m['profit'] < 0 ? 'text-danger' : '' ?>"><?= money($m['profit']) ?></strong></span></div>
      <div class="loadbar"><i style="width:<?= (int) round($m['revenue'] / $maxM * 100) ?>%"></i></div><div class="loadbar mt-1"><i class="full" style="width:<?= (int) round($m['expenses'] / $maxM * 100) ?>%"></i></div></div><?php endforeach ?>
    <p class="small text-muted mb-0">Barre du haut : chiffre d'affaires. Barre du bas : dépenses.</p>
  </div></div>
  <div class="col-lg-5"><div class="panel"><h2>Dépenses par catégorie</h2>
    <?php foreach ($profit['by_category'] as $k => $v): ?><div class="d-flex justify-content-between py-1 border-bottom"><span><?= e(B::EXPENSE_CATEGORIES[$k] ?? $k) ?></span><strong><?= money($v) ?></strong></div><?php endforeach;
    if (!$profit['by_category']): ?><p class="text-muted small mb-0">Aucune dépense saisie sur la période.</p><?php endif ?>
  </div>
<div class="panel"><h2>Clients</h2>
    <div class="d-flex justify-content-between py-1 border-bottom"><span>Clients au total</span><strong><?= $cust['total'] ?></strong></div>
    <div class="d-flex justify-content-between py-1 border-bottom"><span>Nouveaux sur la période</span><strong><?= $cust['new'] ?></strong></div>
    <div class="d-flex justify-content-between py-1 border-bottom"><span>Clients fidèles (2 prestations ou plus)</span><strong><?= $cust['repeat'] ?> (<?= $pct($cust['repeat_rate']) ?>)</strong></div>
    <div class="d-flex justify-content-between py-1"><span>Panier moyen</span><strong><?= money($cust['avg_basket']) ?></strong></div>
  </div></div>
</div>
<div class="row g-3">
  <div class="col-lg-6"><div class="panel"><h2>Meilleurs clients</h2>
    <?php foreach ($cust['top'] as $t): ?><div class="d-flex justify-content-between py-1 border-bottom"><a href="/admin/clients/<?= (int) $t['id'] ?>"><?= e($t['first_name'] . ' ' . $t['last_name']) ?></a><span><?= (int) $t['n'] ?> prest. · <strong><?= money((int) $t['revenue']) ?></strong></span></div><?php endforeach;
    if (!$cust['top']): ?><p class="text-muted small mb-0">Aucune prestation terminée.</p><?php endif ?></div></div>
  <div class="col-lg-6"><div class="panel"><h2>Clients à relancer (inactifs depuis 90 jours)</h2>
    <?php foreach ($cust['dormant'] as $t): ?><div class="d-flex justify-content-between py-1 border-bottom"><a href="/admin/clients/<?= (int) $t['id'] ?>"><?= e($t['first_name'] . ' ' . $t['last_name']) ?></a><span class="small text-muted">dernière prestation le <?= e(date('d/m/Y', strtotime($t['last_date']))) ?></span></div><?php endforeach;
    if (!$cust['dormant']): ?><p class="text-muted small mb-0">Aucun client inactif.</p><?php endif ?></div></div>
</div>

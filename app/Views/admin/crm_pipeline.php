<?php use App\Services\CrmService; ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <h1 class="mb-0 me-auto">Prospects</h1>
  <form method="get" class="d-flex gap-1"><select class="form-select form-select-sm" name="channel_id" aria-label="Canal" onchange="this.form.submit()"><option value="">Tous les canaux</option>
    <?php foreach ($channels as $ch): ?><option value="<?= (int) $ch['id'] ?>" <?= $channel === (int) $ch['id'] ? 'selected' : '' ?>><?= e($ch['name']) ?></option><?php endforeach ?></select></form>
  <a class="btn btn-primary btn-sm" href="/admin/crud/leads/nouveau">Nouveau prospect</a>
</div>
<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3"><div class="kpi"><div class="n"><?= $kpi['open'] ?></div><div class="l">prospects en cours</div></div></div>
  <div class="col-6 col-lg-3"><div class="kpi"><div class="n"><?= money($kpi['value']) ?></div><div class="l">valeur estimée du pipeline</div></div></div>
  <div class="col-6 col-lg-3"><div class="kpi <?= $kpi['due'] ? 'warn' : '' ?>"><div class="n"><?= $kpi['due'] ?></div><div class="l">à relancer aujourd'hui ou en retard</div></div></div>
  <div class="col-6 col-lg-3"><div class="kpi"><div class="n"><?= $kpi['rate'] === null ? '—' : $kpi['rate'] . ' %' ?></div><div class="l">conversion (30 derniers jours)</div></div></div>
</div>
<div class="row g-3">
<?php foreach (CrmService::LEAD_STATUSES as $st => [$label]): ?>
  <div class="col-md-6 col-xl"><div class="panel h-100"><h2 class="d-flex justify-content-between"><span><?= e($label) ?></span><span class="text-muted"><?= count($cols[$st]) ?></span></h2>
    <?php foreach ($cols[$st] as $l): $late = in_array($st, CrmService::OPEN_STATUSES, true) && $l['next_follow_up'] && $l['next_follow_up'] <= $today; ?>
      <a class="d-block text-decoration-none border rounded p-2 mb-2 <?= $late ? 'border-warning bg-warning-subtle' : '' ?>" href="/admin/crm/prospects/<?= (int) $l['id'] ?>">
        <strong class="text-body"><?= e($l['name']) ?></strong>
        <div class="small text-muted"><?= e($l['service_name'] ?? 'Service non précisé') ?> · <?= e($l['channel_name'] ?? 'Canal inconnu') ?></div>
        <?php if ((int) $l['estimated_value']): ?><div class="small"><?= money((int) $l['estimated_value']) ?></div><?php endif ?>
        <?php if ($l['next_follow_up'] && in_array($st, CrmService::OPEN_STATUSES, true)): ?><div class="small <?= $late ? 'text-danger fw-semibold' : 'text-muted' ?>">Relance : <?= e(date('d/m', strtotime($l['next_follow_up']))) ?></div><?php endif ?>
      </a>
    <?php endforeach; if (!$cols[$st]): ?><p class="text-muted small mb-0">Aucun.</p><?php endif ?>
  </div></div>
<?php endforeach ?>
</div>

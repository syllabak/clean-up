<?php
$qs = fn(array $o) => '?' . http_build_query(array_filter(array_merge(['view' => $view, 'date' => $date, 'group' => $group] + $f, $o), fn($v) => $v !== '' && $v !== null && $v !== 0));
$step = $view === 'week' ? '7 day' : '1 day';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <h1 class="mb-0 me-auto">Planning <span class="fs-6 text-muted"><?= $view === 'week' ? e(date('d/m', strtotime($from))) . ' → ' . e(date('d/m/Y', strtotime($to))) : e(fmt_date($date)) ?></span></h1>
  <a class="btn btn-sm btn-outline-secondary" href="<?= e($qs(['date' => date('Y-m-d', strtotime("$date -$step"))])) ?>">‹</a>
  <a class="btn btn-sm btn-outline-secondary" href="<?= e($qs(['date' => date('Y-m-d')])) ?>">Aujourd'hui</a>
  <a class="btn btn-sm btn-outline-secondary" href="<?= e($qs(['date' => date('Y-m-d', strtotime("$date +$step"))])) ?>">›</a>
  <div class="btn-group btn-group-sm"><a class="btn btn-outline-primary <?= $view === 'day' ? 'active' : '' ?>" href="<?= e($qs(['view' => 'day'])) ?>">Jour</a><a class="btn btn-outline-primary <?= $view === 'week' ? 'active' : '' ?>" href="<?= e($qs(['view' => 'week'])) ?>">Semaine</a></div>
</div>
<form class="panel row g-2 align-items-end" method="get">
  <input type="hidden" name="view" value="<?= e($view) ?>"><input type="hidden" name="date" value="<?= e($date) ?>">
  <div class="col-6 col-md-2"><label class="form-label small" for="group">Grouper par</label><select class="form-select form-select-sm" id="group" name="group"><?php foreach (['team' => 'Équipe', 'staff' => 'Agent', 'neighborhood' => 'Quartier', 'status' => 'Statut'] as $k => $l): ?><option value="<?= $k ?>" <?= $group === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label small" for="team_id">Équipe</label><select class="form-select form-select-sm" id="team_id" name="team_id"><option value="">Toutes</option><?php foreach ($teams as $t): ?><option value="<?= $t['id'] ?>" <?= (int) ($f['team_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label small" for="staff_id">Agent</label><select class="form-select form-select-sm" id="staff_id" name="staff_id"><option value="">Tous</option><?php foreach ($staff as $t): ?><option value="<?= $t['id'] ?>" <?= (int) ($f['staff_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label small" for="neighborhood_id">Quartier</label><select class="form-select form-select-sm" id="neighborhood_id" name="neighborhood_id"><option value="">Tous</option><?php foreach ($hoods as $t): ?><option value="<?= $t['id'] ?>" <?= (int) ($f['neighborhood_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label small" for="status">Statut</label><select class="form-select form-select-sm" id="status" name="status"><option value="">Actifs</option><?php foreach (BOOKING_STATUSES as $k => [$l]): ?><option value="<?= $k ?>" <?= ($f['status'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select></div>
  <div class="col-6 col-md-2"><button class="btn btn-sm btn-primary">Appliquer</button></div>
</form>

<?php if ($view === 'day' && $load): ?>
<div class="panel"><h2>Disponibilité des équipes</h2><div class="row g-3">
  <?php foreach ($load as $l): $pct = $l['capacity'] ? min(100, (int) round($l['count'] / $l['capacity'] * 100)) : 0; ?>
  <div class="col-md-4"><strong><?= e($l['team']['name']) ?></strong>
    <div class="small text-muted"><?php if ($l['off']): ?>En congé ou entreprise fermée<?php elseif (!$l['hours']): ?>Ne travaille pas ce jour<?php else: ?><?= e($l['hours']['start_time']) ?>–<?= e($l['hours']['end_time']) ?> · <?= $l['count'] ?>/<?= $l['capacity'] ?> intervention(s) · <strong>libre : <?= e(fmt_duration($l['free'])) ?></strong><?php endif ?></div>
    <div class="loadbar mt-1"><i class="<?= $pct >= 100 ? 'full' : '' ?>" style="width:<?= $pct ?>%"></i></div></div>
  <?php endforeach ?></div></div>
<?php endif ?>

<?php if (!$groups): ?><div class="panel text-muted">Aucune intervention sur cette période.</div>
<?php elseif ($view === 'day'): ?>
<div class="row g-3"><?php foreach ($groups as $g): ?>
  <div class="col-lg-6 col-xl-4"><div class="panel"><h2><?= e($g['label']) ?> <span class="text-muted fs-6">(<?= count($g['items']) ?>)</span></h2><div class="cardlist">
    <?php foreach ($g['items'] as $b): $cf = $conflicts[$b['id']] ?? []; ?>
    <a class="item d-block text-decoration-none text-body <?= $cf ? 'conflict' : '' ?>" href="/admin/reservations/<?= (int) $b['id'] ?>">
      <span class="tm"><?= e($b['start_time']) ?>–<?= e($b['end_time']) ?></span> <?= status_badge($b['status']) ?><br>
      <strong><?= e($b['service_name']) ?></strong> · <?= e($b['customer_first_name'] . ' ' . $b['customer_last_name']) ?><br>
      <span class="small text-muted"><?= e($b['neighborhood_name']) ?><?= $group !== 'team' && $b['team_name'] ? ' · ' . e($b['team_name']) : '' ?><?= $group !== 'staff' && $b['staff_name'] ? ' · ' . e($b['staff_name']) : '' ?></span>
      <?php foreach ($cf as $m): ?><br><span class="small text-danger fw-semibold">⚠ <?= e($m) ?></span><?php endforeach ?>
    </a><?php endforeach ?></div></div></div>
<?php endforeach ?></div>
<?php else:
  $days = []; for ($d = strtotime($from); $d <= strtotime($to); $d = strtotime('+1 day', $d)) $days[] = date('Y-m-d', $d); ?>
<div class="table-responsive"><div class="week-grid">
  <div class="hd">Groupe</div><?php foreach ($days as $d): ?><div class="hd"><?= e(DAYS_FR[(int) date('N', strtotime($d))]) ?> <?= e(date('d/m', strtotime($d))) ?></div><?php endforeach ?>
  <?php foreach ($groups as $g): ?>
    <div class="hd"><?= e($g['label']) ?></div>
    <?php foreach ($days as $d): ?><div><?php foreach ($g['items'] as $b): if ($b['scheduled_date'] !== $d) continue; ?>
      <a class="chip <?= isset($conflicts[$b['id']]) ? 'conflict' : '' ?>" href="/admin/reservations/<?= (int) $b['id'] ?>" title="<?= e($b['service_name'] . ' — ' . $b['customer_last_name']) ?>"><strong><?= e($b['start_time']) ?></strong> <?= e($b['service_name']) ?><br><span class="small"><?= e($b['neighborhood_name']) ?></span></a>
    <?php endforeach ?></div><?php endforeach ?>
  <?php endforeach ?>
</div></div>
<?php endif ?>
<p class="small text-muted mt-3">Le déplacement d'une intervention se fait depuis sa fiche (« Déplacer ») : les créneaux proposés tiennent compte des équipes, des trajets et des congés. Une bordure rouge signale un chevauchement ou un trajet trop court.</p>

<h1>Réservations <span class="text-muted fs-6">(<?= $total ?>)</span></h1>
<form class="panel row g-2 align-items-end" method="get">
  <div class="col-md-3"><label class="form-label small" for="q">Recherche</label><input class="form-control form-control-sm" id="q" name="q" value="<?= e($f['q'] ?? '') ?>" placeholder="Référence, nom, téléphone"></div>
  <div class="col-6 col-md-2"><label class="form-label small" for="status">Statut</label><select class="form-select form-select-sm" id="status" name="status"><option value="">Tous</option><?php foreach (BOOKING_STATUSES as $k => [$l]): ?><option value="<?= $k ?>" <?= ($f['status'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label small" for="team_id">Équipe</label><select class="form-select form-select-sm" id="team_id" name="team_id"><option value="">Toutes</option><?php foreach ($teams as $t): ?><option value="<?= $t['id'] ?>" <?= (int) ($f['team_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label small" for="service_id">Service</label><select class="form-select form-select-sm" id="service_id" name="service_id"><option value="">Tous</option><?php foreach ($services as $t): ?><option value="<?= $t['id'] ?>" <?= (int) ($f['service_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label small" for="neighborhood_id">Quartier</label><select class="form-select form-select-sm" id="neighborhood_id" name="neighborhood_id"><option value="">Tous</option><?php foreach ($hoods as $t): ?><option value="<?= $t['id'] ?>" <?= (int) ($f['neighborhood_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label small" for="from">Du</label><input class="form-control form-control-sm" type="date" id="from" name="from" value="<?= e($f['from'] ?? '') ?>"></div>
  <div class="col-6 col-md-2"><label class="form-label small" for="to">Au</label><input class="form-control form-control-sm" type="date" id="to" name="to" value="<?= e($f['to'] ?? '') ?>"></div>
  <div class="col-12 col-md-auto"><button class="btn btn-sm btn-primary">Filtrer</button> <a class="btn btn-sm btn-outline-secondary" href="/admin/reservations">Réinitialiser</a></div>
</form>
<div class="panel p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Référence</th><th>Quand</th><th>Client</th><th>Service</th><th>Quartier</th><th>Équipe / agent</th><th>Total</th><th>Statut</th></tr></thead><tbody>
  <?php foreach ($rows as $b): ?><tr>
    <td><a href="/admin/reservations/<?= (int) $b['id'] ?>"><?= e($b['reference']) ?></a></td>
    <td class="text-nowrap"><?= e(date('d/m/Y', strtotime($b['scheduled_date']))) ?> <?= e($b['start_time']) ?></td>
    <td><?= e($b['customer_first_name'] . ' ' . $b['customer_last_name']) ?><br><span class="small text-muted"><?= e(phone_fmt($b['customer_phone'])) ?></span></td>
    <td><?= e($b['service_name']) ?></td><td><?= e($b['neighborhood_name']) ?></td>
    <td><?= e($b['team_name'] ?? '—') ?><?= $b['staff_name'] ? '<br><span class="small text-muted">' . e($b['staff_name']) . '</span>' : '' ?></td>
    <td class="text-nowrap"><?= money((int) $b['total']) ?></td><td><?= status_badge($b['status']) ?></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">Aucune réservation.</td></tr><?php endif ?>
</tbody></table></div></div>
<?= \App\Core\View::partial('partials/pagination', ['page' => $page, 'pages' => $pages]) ?>

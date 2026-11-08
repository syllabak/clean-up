<?php
use App\Services\BookingService;
$hasPos = $b['lat'] !== null && $b['lng'] !== null;
$addr = trim($b['address_text'] . ', ' . $b['neighborhood_name'] . ', ' . $b['city_name'] . ', Sénégal');
$mapsOpen = $hasPos ? 'https://www.google.com/maps/search/?api=1&query=' . $b['lat'] . ',' . $b['lng'] : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($addr);
$mapsRoute = 'https://www.google.com/maps/dir/?api=1&destination=' . ($hasPos ? $b['lat'] . ',' . $b['lng'] : rawurlencode($addr));
$photoIds = implode(',', array_map(fn($p) => (int) $p['id'], $photos));
$final = in_array($b['status'], ['completed', 'cancelled'], true);
?>
<p class="mb-1"><a href="/admin/reservations">← Réservations</a></p>
<h1 class="mb-2"><?= e($b['reference']) ?> <?= status_badge($b['status']) ?></h1>

<div class="seg-status mb-3" role="group" aria-label="Statut de la commande">
  <?php foreach (BOOKING_STATUSES as $k => [$label]):
    $isNow = $k === $b['status']; $can = in_array($k, $next, true) && can('bookings.manage'); ?>
    <?php if ($isNow): ?><span class="seg on s-<?= e($k) ?>" aria-current="true"><?= e($label) ?></span>
    <?php elseif ($can): ?><form method="post" action="/admin/reservations/<?= (int) $b['id'] ?>/statut" <?= $k === 'cancelled' ? 'data-confirm="Annuler cette commande ? Cette action est définitive."' : '' ?>><?= csrf_field() ?><input type="hidden" name="to" value="<?= e($k) ?>"><button class="seg s-<?= e($k) ?>"><?= e($label) ?></button></form>
    <?php else: ?><span class="seg off"><?= e($label) ?></span><?php endif ?>
  <?php endforeach ?>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="panel"><h2>Client</h2>
      <p class="mb-1 fs-5"><strong><?= e($b['customer_first_name'] . ' ' . $b['customer_last_name']) ?></strong></p>
      <p class="mb-2"><a href="tel:+<?= e($b['customer_phone']) ?>"><?= e(phone_fmt($b['customer_phone'])) ?></a><?= $b['customer_email'] ? ' · <a href="mailto:' . e($b['customer_email']) . '">' . e($b['customer_email']) . '</a>' : '' ?></p>
      <p class="mb-0"><?= contact_buttons($b['customer_phone'], booking_message($b)) ?></p>
    </div>

    <div class="panel"><h2>Intervention</h2>
      <p class="mb-1"><strong><?= e($b['service_name']) ?></strong></p>
      <p class="mb-1"><?= e(fmt_date($b['scheduled_date'])) ?> de <?= e($b['start_time']) ?> à <?= e($b['end_time']) ?> (<?= e(fmt_duration((int) $b['duration_minutes'])) ?>)</p>
      <?php foreach ($items as $it): foreach (json_decode((string) $it['details'], true) ?: [] as [$k, $v]): ?><p class="mb-0 small text-muted"><?= e($k) ?> : <?= e($v) ?></p><?php endforeach; endforeach ?>
      <?php if ($b['instructions']): ?><p class="mt-2 mb-0"><strong>Instructions :</strong> <?= e($b['instructions']) ?></p><?php endif ?>
      <hr><p class="mb-0">Total <strong><?= money((int) $b['total']) ?></strong> <span class="small text-muted">(dont déplacement <?= money((int) $b['travel_fee']) ?>)</span></p>
    </div>

    <div class="panel" id="carte"><h2>Localisation</h2>
      <p class="mb-2"><?= e($b['address_text']) ?><br><span class="text-muted small"><?= e($b['neighborhood_name']) ?>, <?= e($b['commune_name']) ?>, <?= e($b['city_name']) ?></span></p>
      <?php if ($hasPos): ?>
        <div id="order-map" class="geo-map mb-2" data-lat="<?= e($b['lat']) ?>" data-lng="<?= e($b['lng']) ?>" role="img" aria-label="Position du client sur la carte"></div>
        <p class="small text-muted mb-2"><?= ['gps' => 'Position détectée par le téléphone du client.', 'map' => 'Position placée sur la carte par le client.'][$b['location_source']] ?? 'Position indiquée par le client.' ?></p>
      <?php else: ?><p class="small text-muted mb-2">Le client n'a pas partagé sa position : seule l'adresse écrite est connue.</p><?php endif ?>
      <a class="btn btn-sm btn-primary" href="<?= e($mapsRoute) ?>" target="_blank" rel="noopener">Itinéraire</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= e($mapsOpen) ?>" target="_blank" rel="noopener">Ouvrir dans Google Maps</a>
    </div>

    <div class="panel"><h2>Photos <span class="text-muted fs-6 fw-normal">(<?= count($photos) ?>)</span></h2>
      <?php if (!$photos): ?><p class="text-muted mb-0">Aucune photo pour cette commande.</p><?php else: ?>
      <div class="ph-grid">
        <?php foreach ($photos as $i => $ph): ?><button type="button" class="ph-thumb ph-lg" data-ids="<?= e($photoIds) ?>" data-i="<?= $i ?>" aria-label="Agrandir la photo <?= $i + 1 ?>"><img src="/photo/<?= (int) $ph['id'] ?>?w=480" alt="" loading="lazy"><span class="ph-tag"><?= $ph['user_id'] === null ? 'Client · avant' : ($ph['kind'] === 'after' ? 'Équipe · après' : 'Équipe · avant') ?></span></button><?php endforeach ?>
      </div><p class="small text-muted mt-2 mb-0">Touchez une photo pour l'agrandir et passer de l'une à l'autre.</p><?php endif ?>
    </div>

    <div class="panel"><h2>Notes internes</h2>
      <form method="post" action="/admin/reservations/<?= (int) $b['id'] ?>/note" class="d-flex gap-2 mb-3"><?= csrf_field() ?><input class="form-control" name="note" maxlength="2000" placeholder="Ajouter une note…" required><button class="btn btn-outline-primary">Ajouter</button></form>
      <?php foreach ($notes as $n): ?><p class="mb-2 small"><strong><?= e($n['user_name'] ?? 'Système') ?></strong> · <span class="text-muted"><?= e(date('d/m H:i', strtotime($n['created_at'] . ' UTC'))) ?></span><br><?= nl2br(e($n['note'])) ?></p><?php endforeach ?>
    </div>
  </div>

  <div class="col-lg-5">
    <?php if (!$final): ?>
    <div class="panel"><h2>Équipe</h2>
      <p class="small text-muted mb-2">Actuellement : <strong><?= e($b['team_name'] ?? 'aucune') ?></strong><?= $b['staff_name'] ? ' · ' . e($b['staff_name']) : '' ?></p>
      <form method="post" action="/admin/reservations/<?= (int) $b['id'] ?>/affecter" class="row g-2"><?= csrf_field() ?>
        <div class="col-12"><select class="form-select" id="team_id" name="team_id" aria-label="Équipe"><option value="">Choisir une équipe…</option><?php foreach ($teams as $t): ?><option value="<?= $t['id'] ?>" <?= (int) $b['team_id'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach ?></select></div>
        <div class="col-12"><details><summary class="small text-primary">Choisir un agent précis ou forcer</summary><div class="mt-2">
          <select class="form-select mb-2" id="staff_id" name="staff_id" aria-label="Agent"><option value="">Toute l'équipe</option><?php foreach ($staff as $s): ?><option value="<?= $s['id'] ?>" <?= (int) ($b['staff_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?> (<?= e($s['team_name'] ?? '—') ?>)</option><?php endforeach ?></select>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="force" value="1" id="force"><label class="form-check-label small" for="force">Forcer malgré un conflit (sera tracé)</label></div></div></details></div>
        <div class="col-12"><button class="btn btn-primary">Affecter</button></div>
      </form>
    </div>
    <?php if ($b['status'] !== 'in_progress'): ?>
    <div class="panel"><details><summary class="fw-bold">Déplacer la commande</summary>
      <form method="post" action="/admin/reservations/<?= (int) $b['id'] ?>/deplacer" class="row g-2 mt-1" id="resched" data-booking="<?= (int) $b['id'] ?>" data-csrf="<?= e(\App\Core\Csrf::token()) ?>"><?= csrf_field() ?>
        <div class="col-12"><label class="form-label small" for="r_date">Nouvelle date</label><input class="form-control" type="date" id="r_date" name="date" min="<?= date('Y-m-d') ?>" required></div>
        <div class="col-12"><label class="form-label small" for="r_start">Créneau disponible</label><select class="form-select" id="r_start" name="start" required><option value="">Choisissez d'abord une date</option></select></div>
        <div class="col-12"><button class="btn btn-outline-primary">Déplacer et prévenir le client</button></div>
      </form></details></div>
    <?php endif ?>
    <?php endif ?>

    <?php if ($revenue_on && in_array($b['status'], ['in_progress', 'completed'], true)): ?>
    <div class="panel"><h2>Montant encaissé</h2>
      <form method="post" action="/admin/reservations/<?= (int) $b['id'] ?>/montant" class="row g-2"><?= csrf_field() ?>
        <div class="col-6"><input class="form-control" name="declared_amount" inputmode="numeric" value="<?= e($b['declared_amount'] ?? '') ?>" placeholder="<?= (int) $b['total'] ?>" aria-label="Montant encaissé"></div>
        <div class="col-6"><input class="form-control" name="payment_note" maxlength="255" value="<?= e($b['payment_note'] ?? '') ?>" placeholder="Espèces, Wave…" aria-label="Mode de règlement"></div>
        <div class="col-12"><button class="btn btn-outline-primary">Enregistrer</button></div></form>
    </div>
    <?php endif ?>

    <div class="panel"><details><summary class="fw-bold">Historique</summary><ul class="timeline mt-2 mb-0"><?php foreach ($history as $h): ?><li><?= status_badge($h['to_status']) ?> <span class="small text-muted"><?= e(date('d/m H:i', strtotime($h['created_at'] . ' UTC'))) ?> · <?= e($h['user_name'] ?? 'Client / système') ?></span><?= $h['note'] ? '<br><span class="small">' . e($h['note']) . '</span>' : '' ?></li><?php endforeach ?></ul></details></div>
    <div class="panel"><details><summary class="fw-bold">Messages envoyés au client (<?= count($logs) ?>)</summary>
      <?php if (!$logs): ?><p class="text-muted mt-2 mb-0">Aucun.</p><?php endif ?>
      <?php foreach ($logs as $l): ?><p class="mb-1 mt-2 small"><span class="badge text-bg-<?= $l['status'] === 'sent' ? 'success' : ($l['status'] === 'failed' ? 'danger' : 'secondary') ?>"><?= e($l['status']) ?></span> <?= e($l['channel']) ?> · <?= e($l['event']) ?> <span class="text-muted"><?= e(date('d/m H:i', strtotime($l['created_at'] . ' UTC'))) ?></span><?= $l['error'] ? '<br><span class="text-danger">' . e($l['error']) . '</span>' : '' ?></p><?php endforeach ?></details></div>
  </div>
</div>

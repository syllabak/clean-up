<p class="mb-1"><a href="/admin/reservations">← Réservations</a></p>
<h1><?= e($b['reference']) ?> <?= status_badge($b['status']) ?></h1>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="panel"><h2>Intervention</h2>
      <p class="mb-1"><strong><?= e($b['service_name']) ?></strong></p>
      <p class="mb-1"><?= e(fmt_date($b['scheduled_date'])) ?> de <?= e($b['start_time']) ?> à <?= e($b['end_time']) ?> (<?= e(fmt_duration((int) $b['duration_minutes'])) ?>)</p>
      <p class="mb-1">Adresse : <?= e($b['address_text']) ?> — <?= e($b['neighborhood_name']) ?>, <?= e($b['commune_name']) ?>, <?= e($b['city_name']) ?></p>
      <?php if ($b['instructions']): ?><p class="mb-1">Instructions : <?= e($b['instructions']) ?></p><?php endif ?>
      <?php foreach ($items as $it): foreach (json_decode((string) $it['details'], true) ?: [] as [$k, $v]): ?><p class="mb-0 small text-muted"><?= e($k) ?> : <?= e($v) ?></p><?php endforeach; endforeach ?>
      <hr><p class="mb-0">Sous-total <?= money((int) $b['subtotal']) ?> + déplacement <?= money((int) $b['travel_fee']) ?> = <strong><?= money((int) $b['total']) ?></strong></p>
    </div>
    <div class="panel"><h2>Client</h2>
      <p class="mb-1"><strong><?= e($b['customer_first_name'] . ' ' . $b['customer_last_name']) ?></strong></p>
      <p class="mb-0"><a href="tel:+<?= e($b['customer_phone']) ?>"><?= e(phone_fmt($b['customer_phone'])) ?></a><?= $b['customer_email'] ? ' · <a href="mailto:' . e($b['customer_email']) . '">' . e($b['customer_email']) . '</a>' : '' ?></p>
    </div>
    <div class="panel"><h2>Notes internes</h2>
      <form method="post" action="/admin/reservations/<?= (int) $b['id'] ?>/note" class="d-flex gap-2 mb-3"><?= csrf_field() ?><input class="form-control" name="note" maxlength="2000" placeholder="Ajouter une note…" required><button class="btn btn-outline-primary">Ajouter</button></form>
      <?php foreach ($notes as $n): ?><p class="mb-2 small"><strong><?= e($n['user_name'] ?? 'Système') ?></strong> · <span class="text-muted"><?= e(date('d/m H:i', strtotime($n['created_at'] . ' UTC'))) ?></span><br><?= nl2br(e($n['note'])) ?></p><?php endforeach ?>
    </div>
    <?php if ($photos): ?><div class="panel"><h2>Photos de l'intervention</h2><div class="photo-grid"><?php foreach ($photos as $ph): ?><a href="/photo/<?= (int) $ph['id'] ?>" target="_blank" rel="noopener"><img src="/photo/<?= (int) $ph['id'] ?>" alt="Photo <?= $ph['kind'] === 'after' ? 'après' : 'avant' ?>" loading="lazy"></a><?php endforeach ?></div></div><?php endif ?>
    <div class="panel"><h2>Notifications envoyées</h2>
      <?php if (!$logs): ?><p class="text-muted mb-0">Aucune.</p><?php endif ?>
      <?php foreach ($logs as $l): ?><p class="mb-1 small"><span class="badge text-bg-<?= $l['status'] === 'sent' ? 'success' : ($l['status'] === 'failed' ? 'danger' : 'secondary') ?>"><?= e($l['status']) ?></span> <?= e($l['channel']) ?> → <?= e($l['recipient']) ?> · <?= e($l['event']) ?><?= $l['error'] ? ' · <span class="text-danger">' . e($l['error']) . '</span>' : '' ?></p><?php endforeach ?>
    </div>
  </div>
  <div class="col-lg-5">
    <?php if ($next): ?>
    <div class="panel"><h2>Statut</h2>
      <form method="post" action="/admin/reservations/<?= (int) $b['id'] ?>/statut" class="row g-2">
        <?= csrf_field() ?>
        <div class="col-12"><select class="form-select" name="to" aria-label="Nouveau statut"><?php foreach ($next as $s): ?><option value="<?= e($s) ?>"><?= e(status_label($s)) ?></option><?php endforeach ?></select></div>
        <div class="col-12"><input class="form-control" name="note" maxlength="255" placeholder="Motif ou note (facultatif)"></div>
        <div class="col-12"><button class="btn btn-primary">Changer le statut</button></div>
      </form>
    </div>
    <?php endif ?>
    <?php if (!in_array($b['status'], ['completed', 'cancelled'], true)): ?>
    <div class="panel"><h2>Affectation</h2>
      <p class="small text-muted">Actuellement : <?= e($b['team_name'] ?? 'aucune équipe') ?><?= $b['staff_name'] ? ' · ' . e($b['staff_name']) : '' ?></p>
      <form method="post" action="/admin/reservations/<?= (int) $b['id'] ?>/affecter" class="row g-2">
        <?= csrf_field() ?>
        <div class="col-12"><label class="form-label small" for="team_id">Équipe</label><select class="form-select" id="team_id" name="team_id"><option value="">—</option><?php foreach ($teams as $t): ?><option value="<?= $t['id'] ?>" <?= (int) $b['team_id'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach ?></select></div>
        <div class="col-12"><label class="form-label small" for="staff_id">Agent (facultatif)</label><select class="form-select" id="staff_id" name="staff_id"><option value="">Toute l'équipe</option><?php foreach ($staff as $s): ?><option value="<?= $s['id'] ?>" <?= (int) ($b['staff_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= e(($s['team_name'] ?? 'Sans équipe') . ' — ' . $s['name']) ?></option><?php endforeach ?></select></div>
        <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="force" value="1" id="force"><label class="form-check-label small" for="force">Forcer malgré un conflit (sera tracé)</label></div>
        <div class="col-12"><button class="btn btn-primary">Affecter</button></div>
      </form>
    </div>
    <?php if (!in_array($b['status'], ['in_progress'], true)): ?>
    <div class="panel"><h2>Déplacer</h2>
      <form method="post" action="/admin/reservations/<?= (int) $b['id'] ?>/deplacer" class="row g-2" id="resched" data-booking="<?= (int) $b['id'] ?>" data-csrf="<?= e(\App\Core\Csrf::token()) ?>">
        <?= csrf_field() ?>
        <div class="col-12"><label class="form-label small" for="r_date">Nouvelle date</label><input class="form-control" type="date" id="r_date" name="date" min="<?= date('Y-m-d') ?>" required></div>
        <div class="col-12"><label class="form-label small" for="r_start">Créneau disponible</label><select class="form-select" id="r_start" name="start" required><option value="">Choisissez d'abord une date</option></select></div>
        <div class="col-12"><button class="btn btn-outline-primary">Déplacer et prévenir le client</button></div>
      </form>
    </div>
    <?php endif ?>
    <?php endif ?>
    <?php if ($revenue_on): ?>
    <div class="panel"><h2>Montant encaissé</h2>
      <p class="small text-muted">Saisie manuelle : l'application ne gère aucun paiement.</p>
      <form method="post" action="/admin/reservations/<?= (int) $b['id'] ?>/montant" class="row g-2"><?= csrf_field() ?>
        <div class="col-6"><input class="form-control" name="declared_amount" inputmode="numeric" value="<?= e($b['declared_amount'] ?? '') ?>" placeholder="<?= (int) $b['total'] ?>" aria-label="Montant encaissé"></div>
        <div class="col-6"><input class="form-control" name="payment_note" maxlength="255" value="<?= e($b['payment_note'] ?? '') ?>" placeholder="Espèces, Wave…" aria-label="Mode de règlement"></div>
        <div class="col-12"><button class="btn btn-outline-primary">Enregistrer</button></div></form>
    </div>
    <?php endif ?>
    <div class="panel"><h2>Historique</h2><ul class="timeline mb-0"><?php foreach ($history as $h): ?><li><?= status_badge($h['to_status']) ?> <span class="small text-muted"><?= e(date('d/m H:i', strtotime($h['created_at'] . ' UTC'))) ?> · <?= e($h['user_name'] ?? 'Client / système') ?></span><?= $h['note'] ? '<br><span class="small">' . e($h['note']) . '</span>' : '' ?></li><?php endforeach ?></ul></div>
  </div>
</div>

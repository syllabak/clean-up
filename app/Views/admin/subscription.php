<?php
use App\Services\SubscriptionService as S;
[$sl, $sc] = S::STATUSES[$s['status']] ?? [$s['status'], 'light'];
$times = []; for ($m = 7 * 60; $m <= 17 * 60 + 30; $m += 30) $times[] = min_to_hm($m);
$manage = url('/abonnement/' . $s['track_token']);
$who = $s['business_name'] ?: $s['first_name'] . ' ' . $s['last_name'];
?>
<p class="mb-1"><a href="/admin/abonnements">← Abonnements</a></p>
<h1 class="mb-2"><?= e($who) ?> <span class="badge text-bg-<?= e($sc) ?> fs-6 align-middle"><?= e($sl) ?></span></h1>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="panel"><h2>Client</h2>
      <p class="mb-1 fs-5"><strong><?= e($s['first_name'] . ' ' . $s['last_name']) ?></strong><?= $s['business_name'] ? ' · ' . e($s['business_name']) : '' ?> <small class="text-muted"><?= $s['kind'] === 'business' ? 'Entreprise' : 'Particulier' ?></small></p>
      <p class="mb-2"><a href="tel:+<?= e($s['phone']) ?>"><?= e(phone_fmt($s['phone'])) ?></a><?= $s['email'] ? ' · <a href="mailto:' . e($s['email']) . '">' . e($s['email']) . '</a>' : '' ?></p>
      <p class="mb-0"><?= contact_buttons($s['phone'], 'Bonjour ' . $s['first_name'] . ', ici ' . setting('company_name', '') . ' au sujet de votre abonnement « ' . $s['plan_name'] . ' ». Votre espace : ' . $manage) ?></p></div>
    <div class="panel"><h2>Abonnement</h2>
      <p class="mb-1"><strong><?= e($s['plan_name']) ?></strong> · <?= e($s['service_name']) ?></p>
      <p class="mb-1"><?= e(S::frequencyLabel($s['frequency'])) ?>, le <?= e(mb_strtolower(DAYS_FR[(int) $s['weekday']])) ?> à <?= e($s['start_time']) ?> · premier passage le <?= e(fmt_date($anchor)) ?></p>
      <p class="mb-1"><?= e($s['address_text']) ?> · <?= e($s['neighborhood_name']) ?><?= $s['instructions'] ? '<br><span class="small text-muted">' . e($s['instructions']) . '</span>' : '' ?></p>
      <?php if ($est): ?><?php foreach ($est['details'] as [$k, $v]): ?><p class="mb-0 small text-muted"><?= e($k) ?> : <?= e($v) ?></p><?php endforeach ?>
      <hr><p class="mb-0">Par passage : <strong><?= e(money($est['total'])) ?></strong> <small class="text-muted">(sous-total <?= e(money($est['subtotal'])) ?>, remise −<?= e(money($est['discount'])) ?> soit <?= (int) $s['discount_percent'] ?> %, déplacement <?= e(money($est['travel_fee'])) ?>)</small></p>
      <?php else: ?><p class="small text-danger mb-0">Le prix ne peut plus être calculé : le service ou ses options ont changé. Vérifiez le service avant d'activer.</p><?php endif ?>
      <?php if ($s['notes']): ?><p class="mt-2 mb-0"><strong>Message du client :</strong> <?= nl2br(e($s['notes'])) ?></p><?php endif ?>
      <p class="small mt-2 mb-0 text-muted">Espace du client : <a href="<?= e($manage) ?>" target="_blank" rel="noopener"><?= e($manage) ?></a></p></div>

    <div class="panel"><h2>Passages <?php if ($s['status'] === 'active'): ?><form class="d-inline" method="post" action="/admin/abonnements/<?= (int) $s['id'] ?>/generer"><?= csrf_field() ?><button class="btn btn-sm btn-outline-primary ms-2">Planifier les prochains jours</button></form><?php endif ?></h2>
      <?php if (!$visits): ?><p class="text-muted mb-0">Aucun passage pour le moment. Ils sont créés à l'activation, puis chaque nuit pour les 21 jours à venir.</p><?php endif ?>
      <?php foreach ($visits as $v): ?>
      <p class="mb-1"><strong><?= e(fmt_date($v['visit_date'])) ?></strong><?= $v['start_time'] ? ' à ' . e($v['start_time']) : '' ?>
        <?php if ($v['bid']): ?><a class="small" href="/admin/reservations/<?= (int) $v['bid'] ?>"><?= e($v['reference']) ?></a> <?= status_badge($v['bstatus']) ?> <span class="small"><?= e(money((int) $v['total'])) ?></span>
        <?php elseif ($v['status'] === 'unavailable'): ?><span class="badge text-bg-warning">À replanifier</span>
        <?php else: ?><span class="badge text-bg-secondary">Ignoré</span><?php endif ?>
        <?= $v['note'] ? '<br><small class="text-muted">' . e($v['note']) . '</small>' : '' ?></p>
      <?php endforeach ?></div>
    <div class="panel"><h2>Notes internes</h2>
      <form method="post" action="/admin/abonnements/<?= (int) $s['id'] ?>/note" class="d-flex gap-2 mb-2"><?= csrf_field() ?><input class="form-control" name="note" maxlength="500" placeholder="Ajouter une note…" required><button class="btn btn-outline-primary">Ajouter</button></form>
      <?= $s['admin_notes'] ? '<p class="small text-muted mb-0">' . nl2br(e($s['admin_notes'])) . '</p>' : '' ?></div>
  </div>
  <div class="col-lg-5">
    <?php if ($s['status'] === 'requested'): ?>
    <div class="panel"><h2>Activer l'abonnement</h2><p class="small text-muted">Après avoir confirmé avec le client. Les passages des 21 prochains jours sont ajoutés au planning, confirmés, sans message automatique.</p>
      <form method="post" action="/admin/abonnements/<?= (int) $s['id'] ?>/activer" class="row g-2"><?= csrf_field() ?>
        <div class="col-6"><label class="form-label small" for="weekday">Jour</label><select class="form-select" id="weekday" name="weekday"><?php foreach ([1, 2, 3, 4, 5, 6] as $d): ?><option value="<?= $d ?>" <?= (int) $s['weekday'] === $d ? 'selected' : '' ?>><?= e(DAYS_FR[$d]) ?></option><?php endforeach ?></select></div>
        <div class="col-6"><label class="form-label small" for="start_time">Heure</label><select class="form-select" id="start_time" name="start_time"><?php foreach ($times as $t): ?><option <?= $s['start_time'] === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach ?></select></div>
        <div class="col-6"><label class="form-label small" for="start_date">À partir du</label><input class="form-control" type="date" id="start_date" name="start_date" value="<?= e($s['start_date']) ?>" min="<?= date('Y-m-d') ?>"></div>
        <div class="col-6"><label class="form-label small" for="discount_percent">Remise (%)</label><input class="form-control" type="number" min="0" max="50" id="discount_percent" name="discount_percent" value="<?= (int) $s['discount_percent'] ?>"></div>
        <div class="col-12"><button class="btn btn-primary">Activer et planifier</button></div></form></div>
    <?php endif ?>
    <?php if ($s['status'] === 'active'): ?>
    <div class="panel"><h2>Mettre en pause</h2><form method="post" action="/admin/abonnements/<?= (int) $s['id'] ?>/pause" class="d-flex gap-2"><?= csrf_field() ?>
      <input class="form-control" type="date" name="until" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" aria-label="Jusqu'au (laisser vide : jusqu'à la reprise)"><button class="btn btn-outline-primary">Pause</button></form>
      <p class="small text-muted mt-2 mb-0">Date vide : en pause jusqu'à ce que vous le repreniez. Les passages de la période sont annulés, sans message.</p></div>
    <?php elseif ($s['status'] === 'paused'): ?>
    <div class="panel"><h2>Reprendre</h2><p class="small text-muted"><?= $s['paused_until'] ? 'Reprise automatique le ' . e(fmt_date($s['paused_until'])) . '.' : 'En pause sans date de fin.' ?></p>
      <form method="post" action="/admin/abonnements/<?= (int) $s['id'] ?>/reprendre"><?= csrf_field() ?><button class="btn btn-primary">Reprendre maintenant</button></form></div>
    <?php endif ?>
    <?php if ($s['status'] !== 'cancelled'): ?>
    <div class="panel"><h2>Résilier</h2><form method="post" action="/admin/abonnements/<?= (int) $s['id'] ?>/resilier" data-confirm="Résilier cet abonnement ? Les passages à venir seront annulés." class="d-flex gap-2"><?= csrf_field() ?>
      <input class="form-control" name="reason" maxlength="255" placeholder="Motif (facultatif)" aria-label="Motif"><button class="btn btn-outline-danger">Résilier</button></form></div>
    <?php else: ?><div class="panel"><p class="mb-0 text-muted">Résilié le <?= e(fmt_date(substr((string) $s['cancelled_at'], 0, 10))) ?><?= $s['cancel_reason'] ? ' : ' . e($s['cancel_reason']) : '' ?>.</p></div><?php endif ?>
  </div>
</div>

<?php
use App\Services\SubscriptionService as S;
[$stl, $stc] = S::STATUSES[$s['status']] ?? [$s['status'], 'light'];
$freq = S::frequencyLabel($s['frequency']);
$token = $s['track_token'];
$wa = preg_replace('/\D/', '', (string) setting('company_whatsapp', ''));
?>
<div class="page-head"><div class="container"><h1>Mon abonnement <span class="badge text-bg-<?= e($stc) ?> fs-6 align-middle"><?= e($stl) ?></span></h1><p class="mb-0"><?= e($s['plan_name']) ?></p></div></div>
<div class="container section"><div class="row g-4">
  <div class="col-lg-7">
    <?php if ($s['status'] === 'requested'): ?><div class="panel"><p class="mb-1"><strong>Demande reçue.</strong></p><p class="mb-0">Nous vous contactons pour confirmer le jour, l'heure et le prix. Les passages apparaissent ici dès que l'abonnement est activé. Gardez cette page : l'adresse est personnelle.</p></div><?php endif ?>
    <div class="panel"><h2 class="h5">Votre abonnement</h2>
      <p class="mb-1"><strong><?= e($s['service_name']) ?></strong> · <?= e($freq) ?></p>
      <p class="mb-1">Le <?= e(mb_strtolower(DAYS_FR[(int) $s['weekday']])) ?> à <?= e($s['start_time']) ?> · <?= e($s['address_text']) ?>, <?= e($s['neighborhood_name']) ?></p>
      <?php if ($s['business_name']): ?><p class="mb-1"><?= e($s['business_name']) ?></p><?php endif ?>
      <?php if ($est): ?><p class="mb-0">Prix par passage : <strong><?= e(money($est['total'])) ?></strong><?php if ($est['discount'] > 0): ?> <small class="text-muted">(remise abonnement −<?= e(money($est['discount'])) ?>, déplacement <?= $est['travel_fee'] ? e(money($est['travel_fee'])) : 'offert' ?>)</small><?php endif ?></p><?php endif ?>
      <?php if ($s['status'] === 'paused'): ?><p class="mt-2 mb-0 text-muted">En pause<?= $s['paused_until'] ? ' jusqu\'au ' . e(fmt_date($s['paused_until'])) : ' jusqu\'à ce que vous le repreniez' ?>.</p><?php endif ?>
    </div>
    <?php if (in_array($s['status'], ['active', 'paused'], true)): ?>
    <div class="panel"><h2 class="h5">Prochains passages</h2>
      <?php if (!$visits): ?><p class="text-muted mb-0"><?= $s['status'] === 'paused' ? 'Aucun passage tant que l\'abonnement est en pause.' : 'Les prochains passages seront planifiés très bientôt.' ?></p><?php endif ?>
      <?php foreach ($visits as $v): $skipped = $v['status'] === 'skipped' || ($v['booking_status'] ?? '') === 'cancelled'; ?>
      <p class="mb-1"><strong><?= e(fmt_date($v['visit_date'])) ?></strong><?= $v['start_time'] ? ' à ' . e($v['start_time']) : '' ?>
        <?php if ($v['status'] === 'unavailable'): ?><span class="badge text-bg-warning">À replanifier : nous vous appelons</span>
        <?php elseif ($skipped): ?><span class="badge text-bg-secondary">Annulé</span>
        <?php elseif ($v['reference']): ?><a class="small" href="/suivi/<?= e($v['reference']) ?>">Voir la réservation <?= e($v['reference']) ?></a><?php endif ?>
        <?= $v['note'] && $v['status'] === 'booked' ? '<br><small class="text-muted">' . e($v['note']) . '</small>' : '' ?></p>
      <?php endforeach ?></div>
    <?php endif ?>
  </div>
  <div class="col-lg-5">
    <?php if ($s['status'] === 'active'): ?>
    <div class="panel"><h2 class="h5">Mettre en pause</h2><p class="small text-muted">Vacances, déplacement : les passages sont annulés pendant la pause, puis l'abonnement reprend.</p>
      <form method="post" action="/abonnement/<?= e($token) ?>/pause" class="d-flex flex-wrap gap-2"><?= csrf_field() ?>
        <select class="form-select" name="for" style="max-width:240px" aria-label="Durée de la pause"><option value="2w">2 semaines</option><option value="1m">4 semaines</option><option value="3m">3 mois</option><option value="">Jusqu'à ce que je reprenne</option></select>
        <button class="btn btn-outline-primary">Mettre en pause</button></form></div>
    <?php elseif ($s['status'] === 'paused'): ?>
    <div class="panel"><h2 class="h5">Reprendre</h2><form method="post" action="/abonnement/<?= e($token) ?>/reprendre"><?= csrf_field() ?><button class="btn btn-primary">Reprendre mon abonnement</button></form></div>
    <?php endif ?>
    <?php if (in_array($s['status'], ['requested', 'active', 'paused'], true)): ?>
    <div class="panel"><h2 class="h5">Résilier</h2><p class="small text-muted">Les passages à venir sont annulés. Vous pourrez vous réabonner quand vous voulez.</p>
      <form method="post" action="/abonnement/<?= e($token) ?>/resilier" data-confirm="Résilier cet abonnement ? Les passages à venir seront annulés."><?= csrf_field() ?>
        <input class="form-control mb-2" name="reason" maxlength="255" placeholder="Pourquoi ? (facultatif)" aria-label="Motif de résiliation"><button class="btn btn-outline-danger">Résilier l'abonnement</button></form></div>
    <?php endif ?>
    <div class="panel"><h2 class="h5">Une question ?</h2><p class="mb-2">Écrivez-nous en rappelant votre nom.</p>
      <?php if ($wa !== ''): ?><a class="btn btn-outline-success" target="_blank" rel="noopener" href="<?= e(wa_link($wa, 'Bonjour, au sujet de mon abonnement « ' . $s['plan_name'] . ' ».')) ?>">WhatsApp</a><?php endif ?>
      <a class="btn btn-outline-secondary" href="tel:<?= e(preg_replace('/[^\d+]/', '', (string) setting('company_phone'))) ?>">Appeler</a></div>
  </div>
</div></div>

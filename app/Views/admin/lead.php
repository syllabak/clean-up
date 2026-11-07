<?php use App\Services\CrmService; $open = in_array($l['status'], CrmService::OPEN_STATUSES, true); ?>
<p class="mb-1"><a href="/admin/crm">← Prospects</a></p>
<h1><?= e($l['name']) ?> <?= CrmService::statusBadge($l['status']) ?></h1>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="panel"><h2>Coordonnées</h2>
      <?php if ($l['phone']): ?><p class="mb-1"><a href="tel:+<?= e($l['phone']) ?>"><?= e(phone_fmt($l['phone'])) ?></a> · <a href="https://wa.me/<?= e($l['phone']) ?>" target="_blank" rel="noopener">WhatsApp</a></p><?php endif ?>
      <?php if ($l['email']): ?><p class="mb-1"><a href="mailto:<?= e($l['email']) ?>"><?= e($l['email']) ?></a></p><?php endif ?>
      <p class="mb-1">Canal : <strong><?= e($l['channel_name'] ?? 'Non renseigné') ?></strong></p>
      <p class="mb-1">Service : <?= e($l['service_name'] ?? 'Non précisé') ?> · Valeur estimée : <?= money((int) $l['estimated_value']) ?></p>
      <p class="mb-1">Responsable : <?= e($l['user_name'] ?? 'Non assigné') ?></p>
      <?php if ($l['lost_reason']): ?><p class="mb-1 text-danger">Raison de la perte : <?= e($l['lost_reason']) ?></p><?php endif ?>
      <?php if ($l['notes']): ?><p class="mb-1 small text-muted"><?= nl2br(e($l['notes'])) ?></p><?php endif ?>
      <a class="btn btn-sm btn-outline-secondary" href="/admin/crud/leads/<?= (int) $l['id'] ?>/modifier">Modifier la fiche</a>
      <?php if ($l['customer_id']): ?><a class="btn btn-sm btn-outline-primary" href="/admin/clients/<?= (int) $l['customer_id'] ?>">Voir le client</a><?php endif ?>
    </div>
    <?php if ($open): ?>
    <div class="panel"><h2>Faire avancer</h2>
      <form method="post" action="/admin/crm/prospects/<?= (int) $l['id'] ?>/statut" class="row g-2 mb-3"><?= csrf_field() ?>
        <div class="col-6"><select class="form-select form-select-sm" name="status" aria-label="Statut"><?php foreach (CrmService::LEAD_STATUSES as $k => [$lab]): ?><option value="<?= e($k) ?>" <?= $k === $l['status'] ? 'selected' : '' ?>><?= e($lab) ?></option><?php endforeach ?></select></div>
        <div class="col-6"><input class="form-control form-control-sm" name="lost_reason" maxlength="255" placeholder="Si perdu : pourquoi ?" aria-label="Raison de la perte"></div>
        <div class="col-12"><button class="btn btn-sm btn-primary">Changer le statut</button></div>
      </form>
      <form method="post" action="/admin/crm/prospects/<?= (int) $l['id'] ?>/relance" class="d-flex gap-2 mb-3"><?= csrf_field() ?>
        <input class="form-control form-control-sm" type="date" name="next_follow_up" value="<?= e($l['next_follow_up']) ?>" aria-label="Prochaine relance"><button class="btn btn-sm btn-outline-secondary text-nowrap">Prochaine relance</button></form>
      <form method="post" action="/admin/crm/prospects/<?= (int) $l['id'] ?>/convertir" data-confirm="Créer le client et marquer ce prospect comme gagné ?"><?= csrf_field() ?><button class="btn btn-sm btn-success">Convertir en client</button></form>
    </div>
    <?php endif ?>
  </div>
  <div class="col-lg-7"><?= \App\Core\View::partial('partials/interactions', ['action' => '/admin/crm/prospects/' . (int) $l['id'] . '/echange', 'interactions' => $interactions]) ?></div>
</div>

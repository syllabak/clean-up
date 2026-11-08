<?php use App\Services\CrmService; ?>
<p class="mb-1"><a href="/admin/crud/customers">← Clients</a></p>
<h1><?= e($c['first_name'] . ' ' . $c['last_name']) ?> <span class="badge text-bg-<?= $stats['segment'] === 'VIP' ? 'warning' : 'secondary' ?>"><?= e($stats['segment']) ?></span></h1>
<div class="row g-3 mb-1">
  <div class="col-6 col-lg-3"><div class="kpi"><div class="n"><?= $stats['done'] ?> / <?= $stats['count'] ?></div><div class="l">prestations terminées / total</div></div></div>
  <div class="col-6 col-lg-3"><div class="kpi"><div class="n"><?= money($stats['revenue']) ?></div><div class="l">chiffre d'affaires cumulé</div></div></div>
  <div class="col-6 col-lg-3"><div class="kpi"><div class="n"><?= money($stats['avg']) ?></div><div class="l">panier moyen</div></div></div>
  <div class="col-6 col-lg-3"><div class="kpi"><div class="n fs-5"><?= e($c['channel_name'] ?? 'Non renseigné') ?></div><div class="l">canal d'acquisition</div></div></div>
</div>
<div class="row g-3 mt-0">
  <div class="col-lg-5">
    <div class="panel"><h2>Coordonnées</h2>
      <p class="mb-1"><a href="tel:+<?= e($c['phone']) ?>"><?= e(phone_fmt($c['phone'])) ?></a> · <a href="https://wa.me/<?= e($c['phone']) ?>" target="_blank" rel="noopener">WhatsApp</a></p>
      <?php if ($c['email']): ?><p class="mb-1"><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></p><?php endif ?>
      <?php foreach ($addresses as $a): ?><p class="mb-1 small text-muted"><?= e($a['address_text']) ?><?= $a['neighborhood_name'] ? ' — ' . e($a['neighborhood_name']) : '' ?></p><?php endforeach ?>
      <?php if ($c['notes']): ?><p class="small"><?= nl2br(e($c['notes'])) ?></p><?php endif ?>
      <a class="btn btn-sm btn-outline-secondary" href="/admin/crud/customers/<?= (int) $c['id'] ?>/modifier">Modifier</a>
    </div>
    <div class="panel"><h2>Acquisition et consentement</h2>
      <form method="post" action="/admin/clients/<?= (int) $c['id'] ?>/canal" class="row g-2"><?= csrf_field() ?>
        <div class="col-12"><select class="form-select form-select-sm" name="channel_id" aria-label="Canal d'acquisition"><option value="">Non renseigné</option>
          <?php foreach ($channels as $ch): ?><option value="<?= (int) $ch['id'] ?>" <?= (int) $c['channel_id'] === (int) $ch['id'] ? 'selected' : '' ?>><?= e($ch['name']) ?></option><?php endforeach ?></select></div>
        <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" id="mc" name="marketing_consent" value="1" <?= $c['marketing_consent'] ? 'checked' : '' ?>><label class="form-check-label" for="mc">Accepte de recevoir nos offres</label></div>
        <?php if (!empty($c['marketing_consent_source']) || !empty($c['marketing_consent_at']) || !empty($c['marketing_optout_at'])): ?><div class="col-12 small text-muted ms-2"><?php
          $src = \App\Services\ConsentService::SOURCES[$c['marketing_consent_source'] ?? ''] ?? ($c['marketing_consent_source'] ?? '');
          if ($c['marketing_consent']) echo 'Consentement' . ($c['marketing_consent_at'] ? ' donné le ' . e(date('d/m/Y H:i', strtotime($c['marketing_consent_at'] . ' UTC'))) : ' (date inconnue)') . ($src ? ' · ' . e($src) : '');
          elseif ($c['marketing_optout_at']) echo 'Désinscrit le ' . e(date('d/m/Y H:i', strtotime($c['marketing_optout_at'] . ' UTC'))) . ($src ? ' · ' . e($src) : ''); ?></div><?php endif ?>
        <div class="col-12"><button class="btn btn-sm btn-primary">Enregistrer</button></div>
      </form>
    </div>
    <?php if ($leads): ?><div class="panel"><h2>Prospects liés</h2><?php foreach ($leads as $l): ?><a class="d-block" href="/admin/crm/prospects/<?= (int) $l['id'] ?>">Prospect n° <?= (int) $l['id'] ?> <?= CrmService::statusBadge($l['status']) ?></a><?php endforeach ?></div><?php endif ?>
  </div>
  <div class="col-lg-7">
    <div class="panel"><h2>Réservations</h2>
      <?php if (!$bookings): ?><p class="text-muted small mb-0">Aucune réservation.</p><?php else: ?>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Date</th><th>Service</th><th>Statut</th><th class="text-end">Montant</th><th></th></tr></thead><tbody>
        <?php foreach ($bookings as $b): ?><tr><td class="text-nowrap"><?= e(date('d/m/Y', strtotime($b['scheduled_date']))) ?></td><td><?= e($b['service_name']) ?></td><td><?= status_badge($b['status']) ?></td>
          <td class="text-end"><?= money((int) ($b['declared_amount'] ?? $b['total'])) ?></td><td><a class="btn btn-sm btn-outline-primary" href="/admin/reservations/<?= (int) $b['id'] ?>">Ouvrir</a></td></tr><?php endforeach ?>
      </tbody></table></div><?php endif ?>
    </div>
    <?= \App\Core\View::partial('partials/interactions', ['action' => '/admin/clients/' . (int) $c['id'] . '/echange', 'interactions' => $interactions]) ?>
  </div>
</div>

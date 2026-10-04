<p class="mb-2"><a href="/agent">← Mes missions</a></p>
<div class="d-flex justify-content-between align-items-center mb-2"><h1 class="h4 mb-0"><?= e($b['service_name']) ?></h1><?= status_badge($b['status']) ?></div>
<p class="fs-4 fw-bold mb-3"><?= e(fmt_date($b['scheduled_date'])) ?> · <?= e($b['start_time']) ?>–<?= e($b['end_time']) ?></p>

<?php if ($next): ?>
<form method="post" action="/agent/mission/<?= (int) $b['id'] ?>/statut" class="mb-3"><?= csrf_field() ?><input type="hidden" name="to" value="<?= e($next) ?>">
  <button class="btn btn-lemon big-action"><?= e($next_label) ?></button></form>
<?php elseif ($b['status'] === 'completed'): ?><div class="alert alert-success">Mission terminée. Merci !</div><?php endif ?>

<div class="panel">
  <h2 class="h6">Client</h2>
  <p class="mb-2 fw-semibold"><?= e($b['customer_first_name'] . ' ' . $b['customer_last_name']) ?></p>
  <a class="btn btn-tel btn-sm" href="tel:+<?= e($b['customer_phone']) ?>">Appeler <?= e(phone_fmt($b['customer_phone'])) ?></a>
</div>
<div class="panel">
  <h2 class="h6">Adresse</h2>
  <p class="mb-1"><?= e($b['address_text']) ?></p><p class="mb-2 text-muted small"><?= e($b['neighborhood_name']) ?>, <?= e($b['commune_name']) ?></p>
  <?php if ($b['instructions']): ?><p class="small"><strong>Accès :</strong> <?= e($b['instructions']) ?></p><?php endif ?>
  <a class="btn btn-primary btn-sm" href="<?= e($maps) ?>" target="_blank" rel="noopener">Ouvrir l'itinéraire</a>
</div>
<div class="panel">
  <h2 class="h6">À faire</h2>
  <?php foreach ($items as $it): ?><p class="mb-1 fw-semibold"><?= e($it['name']) ?><?= $it['formula_name'] ? ' — ' . e($it['formula_name']) : '' ?><?= (int) $it['quantity'] > 1 ? ' × ' . (int) $it['quantity'] : '' ?></p>
    <?php foreach (json_decode((string) $it['details'], true) ?: [] as [$k, $v]): if ($k === 'Formule') continue; ?><p class="mb-0 small text-muted"><?= e($k) ?> : <?= e($v) ?></p><?php endforeach ?><?php endforeach ?>
  <p class="mt-2 mb-0 small">Durée prévue : <?= e(fmt_duration((int) $b['duration_minutes'])) ?> · Montant à encaisser : <strong><?= money((int) $b['total']) ?></strong></p>
</div>

<?php if ($b['status'] !== 'cancelled'): ?>
<div class="panel">
  <h2 class="h6">Photos avant / après</h2>
  <?php if ($photos): ?><div class="photo-grid mb-3"><?php foreach ($photos as $ph): ?><a href="/photo/<?= (int) $ph['id'] ?>" target="_blank" rel="noopener"><img src="/photo/<?= (int) $ph['id'] ?>" alt="Photo <?= $ph['kind'] === 'after' ? 'après' : 'avant' ?>" loading="lazy"></a><?php endforeach ?></div><?php endif ?>
  <form method="post" action="/agent/mission/<?= (int) $b['id'] ?>/photo" enctype="multipart/form-data" class="row g-2"><?= csrf_field() ?>
    <div class="col-12"><input class="form-control" type="file" name="photo" accept="image/*" capture="environment" required aria-label="Photo"></div>
    <div class="col-6"><select class="form-select" name="kind" aria-label="Type de photo"><option value="before">Avant</option><option value="after">Après</option></select></div>
    <div class="col-6"><button class="btn btn-outline-primary w-100">Envoyer</button></div></form>
</div>
<?php endif ?>
<div class="panel">
  <h2 class="h6">Notes</h2>
  <form method="post" action="/agent/mission/<?= (int) $b['id'] ?>/note" class="mb-3"><?= csrf_field() ?><textarea class="form-control mb-2" name="note" rows="2" maxlength="2000" placeholder="Remarque, dégât constaté, client absent…" required></textarea><button class="btn btn-outline-primary btn-sm">Ajouter la note</button></form>
  <?php foreach ($notes as $n): ?><p class="mb-2 small"><strong><?= e($n['user_name'] ?? 'Système') ?></strong> · <span class="text-muted"><?= e(date('d/m H:i', strtotime($n['created_at'] . ' UTC'))) ?></span><br><?= nl2br(e($n['note'])) ?></p><?php endforeach ?>
</div>

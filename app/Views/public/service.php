<?php
$minDur = (int) $s['base_duration'] + ($formulas ? min(array_map(fn($f) => (int) $f['duration_minutes'], $formulas)) : 0);
$unit = $s['billing_unit'] === 'per_sqm' ? ' /m²' : ($s['billing_unit'] === 'per_item' ? ' /unité' : '');
$wa = preg_replace('/\D/', '', (string) setting('company_whatsapp', ''));
?>
<div class="page-head"><div class="container"><p class="mb-1"><a href="/services">← Tous les services</a></p><h1><?= e($s['name']) ?></h1><?php if ($s['short_description']): ?><p class="mb-0 text-muted"><?= e($s['short_description']) ?></p><?php endif ?></div></div>
<div class="container section">
  <div class="row g-4">
    <div class="col-lg-8">
      <?php if ($s['image']): ?><img class="img-fluid rounded-3 mb-3" src="<?= e($s['image']) ?>" alt="<?= e($s['name']) ?>"><?php endif ?>
      <?php if ($images): ?><div class="d-flex flex-wrap gap-2 mb-3"><?php foreach ($images as $im): ?><img class="rounded-3" style="height:110px" src="<?= e($im['path']) ?>" alt=""><?php endforeach ?></div><?php endif ?>
      <?php if ($s['description']): ?><div class="prose"><?= nl2br(e($s['description'])) ?></div><?php endif ?>
      <?php if ($formulas): ?>
        <h2 class="h4 mt-4">Choisissez votre formule</h2>
        <div class="row g-3"><?php foreach ($formulas as $f): $inc = array_filter(array_map('trim', explode("\n", (string) $f['includes']))); ?>
          <div class="<?= count($formulas) === 3 ? 'col-md-4' : 'col-md-6' ?>"><div class="formula-card <?= $f['popular'] ? 'popular' : '' ?>">
            <?php if ($f['popular']): ?><span class="badge text-bg-warning formula-flag">La plus choisie</span><?php endif ?>
            <h3 class="h5 mb-1"><?= e($f['name']) ?></h3>
            <p class="formula-price mb-1"><?= money((int) $f['amount']) ?><small><?= e($unit) ?></small></p>
            <?php if ($f['description']): ?><p class="text-muted small mb-2"><?= e($f['description']) ?></p><?php endif ?>
            <?php if ($inc): ?><ul class="formula-inc"><?php foreach ($inc as $i): ?><li><?= e($i) ?></li><?php endforeach ?></ul><?php endif ?>
            <?php if ((int) $f['duration_minutes']): ?><p class="small text-muted mb-2">+ <?= e(fmt_duration((int) $f['duration_minutes'])) ?> d'intervention</p><?php endif ?>
            <a class="btn <?= $f['popular'] ? 'btn-lemon' : 'btn-outline-primary' ?> w-100 mt-auto" href="/reserver?service=<?= e($s['slug']) ?>&formula=<?= (int) $f['id'] ?>">Choisir cette formule</a>
          </div></div>
        <?php endforeach ?></div>
      <?php endif ?>
      <?php if ($options): ?>
        <h2 class="h4 mt-4">Options à ajouter</h2>
        <ul class="list-unstyled"><?php foreach ($options as $o): ?><li class="py-2 border-bottom d-flex justify-content-between gap-3"><span><strong><?= e($o['name']) ?></strong><?php if ($o['description']): ?><br><span class="small text-muted"><?= e($o['description']) ?></span><?php endif ?></span><strong class="text-nowrap">+ <?= money((int) $o['price']) ?></strong></li><?php endforeach ?></ul>
      <?php endif ?>
      <?php if ($s['conditions']): ?><div class="alert alert-info mt-4"><strong>Bon à savoir :</strong> <?= nl2br(e($s['conditions'])) ?></div><?php endif ?>
      <h2 class="h4 mt-4">Comment ça se passe</h2>
      <ol class="steps"><li><strong>Vous réservez</strong><br>Prix calculé en direct, créneau choisi par vous.</li><li><strong>Nous confirmons</strong><br>Un message vous confirme l'équipe et l'heure.</li><li><strong>Nous venons chez vous</strong><br>Vous réglez directement à l'équipe.</li></ol>
    </div>
    <div class="col-lg-4"><div class="summary">
      <p class="mb-1 text-muted">À partir de</p><p class="total mb-1"><?= e(from_label($s, $from_price)) ?></p>
      <p class="small text-muted mb-3">Durée estimée dès <?= e(fmt_duration(max(15, $minDur))) ?>. Le prix exact s'affiche pendant la réservation, selon vos choix et votre quartier.</p>
      <a class="btn btn-lemon btn-lg w-100 mb-2" href="/reserver?service=<?= e($s['slug']) ?>">Réserver ce service</a>
      <?php if ($wa): ?><a class="btn btn-outline-secondary w-100" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Bonjour, une question sur : ' . $s['name']) ?>" target="_blank" rel="noopener">Poser une question sur WhatsApp</a><?php endif ?>
    </div></div>
  </div>
</div>

<div class="page-head"><div class="container"><p class="mb-1"><a href="/services">← Tous les services</a></p><h1><?= e($s['name']) ?></h1></div></div>
<div class="container section">
  <div class="row g-4">
    <div class="col-lg-8">
      <?php if ($s['image']): ?><img class="img-fluid rounded-3 mb-3" src="<?= e($s['image']) ?>" alt="<?= e($s['name']) ?>"><?php endif ?>
      <?php foreach ($images as $im): ?><img class="img-fluid rounded-3 mb-3" src="<?= e($im['path']) ?>" alt=""><?php endforeach ?>
      <div class="prose"><?= e($s['description']) ?></div>
      <?php if ($formulas): ?>
        <h2 class="h4 mt-4">Formules</h2>
        <div class="row g-2"><?php foreach ($formulas as $f): ?>
          <div class="col-md-6"><div class="panel mb-0"><strong><?= e($f['name']) ?></strong> <span class="float-end fw-bold text-primary"><?= money((int) $f['amount']) ?><?= $s['billing_unit'] === 'per_sqm' ? ' /m²' : '' ?></span><br><span class="text-muted small"><?= e($f['description']) ?></span></div></div>
        <?php endforeach ?></div>
      <?php endif ?>
      <?php if ($options): ?>
        <h2 class="h4 mt-4">Options</h2>
        <ul class="list-unstyled"><?php foreach ($options as $o): ?><li class="py-1 border-bottom d-flex justify-content-between"><span><?= e($o['name']) ?></span><strong>+ <?= money((int) $o['price']) ?></strong></li><?php endforeach ?></ul>
      <?php endif ?>
      <?php if ($s['conditions']): ?><div class="alert alert-info mt-4"><strong>Bon à savoir :</strong> <?= e($s['conditions']) ?></div><?php endif ?>
    </div>
    <div class="col-lg-4"><div class="summary">
      <p class="mb-1 text-muted">À partir de</p><p class="total mb-3"><?= e(from_label($s, $from_price)) ?></p>
      <p class="small text-muted">Le prix exact s'affiche pendant la réservation, selon vos choix et votre quartier.</p>
      <a class="btn btn-lemon btn-lg w-100" href="/reserver?service=<?= e($s['slug']) ?>">Réserver ce service</a>
    </div></div>
  </div>
</div>

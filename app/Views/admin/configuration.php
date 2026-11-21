<h1>Configuration</h1>
<p class="text-muted">Ce qui se règle rarement. Pour le travail de tous les jours, utilisez le menu à gauche.</p>
<?php foreach ($groups as $title => $cards): ?>
<h2 class="h6 text-uppercase text-muted mt-4 mb-2"><?= e($title) ?></h2>
<div class="row g-3">
  <?php foreach ($cards as [$label, $desc, $href]): ?>
  <div class="col-md-6 col-xl-4"><a class="panel d-block h-100 mb-0 text-decoration-none" href="<?= e($href) ?>"><strong class="d-block text-body"><?= e($label) ?></strong><span class="small text-muted"><?= e($desc) ?></span></a></div>
  <?php endforeach ?>
</div>
<?php endforeach ?>

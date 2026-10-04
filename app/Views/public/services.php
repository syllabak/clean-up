<div class="page-head"><div class="container"><h1>Nos services</h1><p class="mb-0 text-muted">Tous nos services se réalisent chez vous, sur rendez-vous.</p></div></div>
<div class="container section">
  <?php foreach ($cats as $c): if (!$c['services']) continue; ?>
    <h2 class="h4 mt-2 mb-3"><?= e($c['name']) ?></h2>
    <div class="row g-3 mb-4">
      <?php foreach ($c['services'] as $s): ?>
      <div class="col-md-6 col-lg-4">
        <a class="service-card" href="/services/<?= e($s['slug']) ?>">
          <div class="thumb" <?= $s['image'] ? 'style="background-image:url(\'' . e($s['image']) . '\')"' : '' ?>><?= $s['image'] ? '' : e(mb_substr($s['name'], 0, 1)) ?></div>
          <div class="body"><h3><?= e($s['name']) ?></h3><p class="mb-0 text-muted"><?= e($s['short_description']) ?></p>
            <span class="price-from">À partir de <?= e(from_label($s, $s['from_price'])) ?></span></div>
        </a>
      </div>
      <?php endforeach ?>
    </div>
  <?php endforeach ?>
</div>

<?php
$sid = (int) $s['id']; $unit = ['fixed' => 'forfait', 'per_item' => "à l'unité", 'per_sqm' => 'au m²'][$s['billing_unit']] ?? $s['billing_unit'];
$bad = count(array_filter($checks, fn($c) => $c[0] === 'bad')); $warn = count(array_filter($checks, fn($c) => $c[0] === 'warn'));
$href = fn($t) => match (true) { $t === 'formules' => '#formules', $t === 'champs' => '#champs', $t === 'edit' => "/admin/crud/services/$sid/modifier", default => (string) $t };
$new = fn($res) => "/admin/crud/$res/nouveau?service_id=$sid";
?>
<p class="mb-1"><a href="/admin/crud/services">← Services</a></p>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <h1 class="mb-0 me-auto"><?= e($s['name']) ?>
    <?php if (!$s['active']): ?><span class="badge text-bg-secondary fs-6">Inactif</span><?php elseif ($s['availability_status'] !== 'available'): ?><span class="badge text-bg-warning fs-6">Indisponible</span><?php else: ?><span class="badge text-bg-success fs-6">En ligne</span><?php endif ?></h1>
  <?php if ($s['active']): ?><a class="btn btn-sm btn-outline-secondary" href="/services/<?= e($s['slug']) ?>" target="_blank" rel="noopener">Voir sur le site</a><?php endif ?>
  <a class="btn btn-sm btn-outline-primary" href="/admin/crud/services/<?= $sid ?>/modifier">Modifier le service</a>
  <form method="post" action="/admin/services/<?= $sid ?>/dupliquer" data-confirm="Dupliquer ce service avec ses formules, options et champs ?"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary">Dupliquer</button></form>
</div>
<p class="text-muted"><?= e($category ?? 'Sans catégorie') ?> · facturation : <?= e($unit) ?> · durée de base <?= e(fmt_duration((int) $s['base_duration'])) ?><?= (int) $s['duration_per_unit'] ? ' + ' . (int) $s['duration_per_unit'] . ' min par m² supplémentaire' : '' ?><?= (int) $s['base_price'] ? ' · prix de base ' . e(money((int) $s['base_price'])) : '' ?></p>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="panel"><h2>Prêt à être réservé ? <span class="badge text-bg-<?= $bad ? 'danger' : ($warn ? 'warning' : 'success') ?>"><?= $bad ? "$bad blocage(s)" : ($warn ? "$warn conseil(s)" : 'Tout est bon') ?></span></h2>
      <?php foreach ($checks as [$lvl, $msg, $to]): ?>
        <div class="d-flex gap-2 py-1 border-bottom"><span aria-hidden="true"><?= $lvl === 'ok' ? '✅' : ($lvl === 'warn' ? '⚠️' : '⛔') ?></span><span class="visually-hidden"><?= $lvl === 'ok' ? 'OK' : ($lvl === 'warn' ? 'Conseil' : 'Blocage') ?> : </span>
          <span class="flex-grow-1"><?= e($msg) ?></span><?php if ($to): ?><a class="small text-nowrap" href="<?= e($href($to)) ?>">Corriger</a><?php endif ?></div>
      <?php endforeach ?>
    </div>

    <div class="panel" id="formules"><div class="d-flex align-items-center mb-2"><h2 class="mb-0 me-auto">Formules et tarifs</h2><a class="btn btn-sm btn-primary" href="<?= e($new('service_prices')) ?>">Ajouter une formule</a></div>
      <?php if (!$formulas): ?><p class="text-muted small mb-0">Aucune formule : le prix de base (<?= e(money((int) $s['base_price'])) ?>) s'applique.</p><?php else: ?>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Formule</th><th class="text-end">Prix<?= $s['billing_unit'] === 'per_sqm' ? ' /m²' : '' ?></th><th class="text-end">Durée</th><th></th></tr></thead><tbody>
        <?php foreach ($formulas as $f): ?><tr class="<?= $f['active'] ? '' : 'text-muted' ?>"><td><strong><?= e($f['name']) ?></strong><?= $f['popular'] ? ' <span class="badge text-bg-warning">La plus choisie</span>' : '' ?><?= $f['active'] ? '' : ' <span class="badge text-bg-secondary">Inactif</span>' ?>
          <?php if (trim((string) $f['includes']) !== ''): ?><div class="small text-muted"><?= e(implode(' · ', array_filter(array_map('trim', explode("\n", (string) $f['includes']))))) ?></div><?php endif ?></td>
          <td class="text-end text-nowrap"><?= e(money((int) $f['amount'])) ?></td><td class="text-end text-nowrap"><?= (int) $f['duration_minutes'] ? '+' . e(fmt_duration((int) $f['duration_minutes'])) : '—' ?></td>
          <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="/admin/crud/service_prices/<?= (int) $f['id'] ?>/modifier">Modifier</a></td></tr><?php endforeach ?>
      </tbody></table></div><?php endif ?>
    </div>

    <div class="panel" id="options"><div class="d-flex align-items-center mb-2"><h2 class="mb-0 me-auto">Options supplémentaires</h2><a class="btn btn-sm btn-primary" href="<?= e($new('service_options')) ?>">Ajouter une option</a></div>
      <?php foreach ($options as $o): ?><div class="d-flex justify-content-between align-items-center py-1 border-bottom <?= $o['active'] ? '' : 'text-muted' ?>"><span><?= e($o['name']) ?><?= $o['active'] ? '' : ' <span class="badge text-bg-secondary">Inactif</span>' ?></span>
        <span class="text-nowrap">+ <?= e(money((int) $o['price'])) ?><?= (int) $o['duration_minutes'] ? ' · +' . e(fmt_duration((int) $o['duration_minutes'])) : '' ?> <a class="btn btn-sm btn-outline-primary ms-2" href="/admin/crud/service_options/<?= (int) $o['id'] ?>/modifier">Modifier</a></span></div><?php endforeach;
      if (!$options): ?><p class="text-muted small mb-0">Aucune option.</p><?php endif ?>
    </div>

    <div class="panel" id="champs"><div class="d-flex align-items-center mb-2"><h2 class="mb-0 me-auto">Questions posées au client</h2><a class="btn btn-sm btn-primary" href="<?= e($new('service_fields')) ?>">Ajouter une question</a></div>
      <?php foreach ($fields as $f): ?><div class="border rounded p-2 mb-2 <?= $f['active'] ? '' : 'text-muted' ?>">
        <div class="d-flex justify-content-between align-items-center"><strong><?= e($f['label']) ?></strong>
          <span class="text-nowrap small"><?= ['select' => 'Liste', 'number' => 'Nombre', 'text' => 'Texte'][$f['type']] ?? e($f['type']) ?><?= $f['role'] === 'area' ? ' · surface m²' : ($f['role'] === 'quantity' ? ' · quantité' : '') ?><?= $f['required'] ? '' : ' · facultatif' ?>
            <a class="btn btn-sm btn-outline-primary ms-2" href="/admin/crud/service_fields/<?= (int) $f['id'] ?>/modifier">Modifier</a></span></div>
        <?php if ($f['type'] === 'select'): ?><div class="small mt-1"><?php foreach ($f['choices'] as $c): ?><span class="badge text-bg-light border me-1 <?= $c['active'] ? '' : 'text-decoration-line-through' ?>"><?= e($c['label']) ?><?= (int) $c['price_delta'] ? ' +' . e(money((int) $c['price_delta'])) : '' ?><?= (float) $c['multiplier'] !== 1.0 ? ' ×' . e(rtrim(rtrim(number_format((float) $c['multiplier'], 2, ',', ''), '0'), ',')) : '' ?></span><?php endforeach ?>
          <a href="/admin/crud/service_field_choices/nouveau?field_id=<?= (int) $f['id'] ?>">+ choix</a> · <a href="/admin/crud/service_field_choices?field_id=<?= (int) $f['id'] ?>">gérer</a></div><?php endif ?></div><?php endforeach;
      if (!$fields): ?><p class="text-muted small mb-0">Aucune question : le client ne choisit que la formule et les options.</p><?php endif ?>
    </div>

    <div class="panel" id="photos"><div class="d-flex align-items-center mb-2"><h2 class="mb-0 me-auto">Photos</h2><a class="btn btn-sm btn-primary" href="<?= e($new('service_images')) ?>">Ajouter une photo</a></div>
      <div class="d-flex flex-wrap gap-2"><?php if ($s['image']): ?><div><img src="<?= e($s['image']) ?>" alt="" style="height:90px;border-radius:8px"><div class="small text-muted">Principale</div></div><?php endif ?>
        <?php foreach ($images as $im): ?><a href="/admin/crud/service_images/<?= (int) $im['id'] ?>/modifier"><img src="<?= e($im['path']) ?>" alt="" style="height:90px;border-radius:8px"></a><?php endforeach ?></div>
      <?php if (!$s['image'] && !$images): ?><p class="text-muted small mb-0">Aucune photo. La photo principale se règle dans « Modifier le service ».</p><?php endif ?>
    </div>
  </div>

  <div class="col-lg-5"><div class="panel position-sticky" style="top:90px" id="sim" data-currency="<?= e(setting('currency', 'FCFA')) ?>" data-url="/admin/services/<?= $sid ?>/simuler"><h2>Simulateur de prix</h2>
    <p class="small text-muted">Même calcul que sur le site, même si le service est inactif. Testez vos tarifs avant de publier.</p>
    <form id="sim-form" class="row g-2"><?= csrf_field() ?>
      <?php if ($formulas): ?><div class="col-12"><label class="form-label small mb-0" for="sim-formula">Formule</label><select class="form-select form-select-sm" id="sim-formula" name="formula_id"><?php foreach ($formulas as $f): if (!$f['active']) continue; ?><option value="<?= (int) $f['id'] ?>"><?= e($f['name']) ?> — <?= e(money((int) $f['amount'])) ?></option><?php endforeach ?></select></div><?php endif ?>
      <?php foreach ($fields as $f): if (!$f['active']) continue; ?><div class="col-12"><label class="form-label small mb-0" for="sf-<?= e($f['field_key']) ?>"><?= e($f['label']) ?></label>
        <?php if ($f['type'] === 'select'): ?><select class="form-select form-select-sm" id="sf-<?= e($f['field_key']) ?>" data-field="<?= e($f['field_key']) ?>"><?php if (!$f['required']): ?><option value="">—</option><?php endif; foreach ($f['choices'] as $c): if (!$c['active']) continue; ?><option value="<?= (int) $c['id'] ?>"><?= e($c['label']) ?></option><?php endforeach ?></select>
        <?php else: ?><input class="form-control form-control-sm" id="sf-<?= e($f['field_key']) ?>" data-field="<?= e($f['field_key']) ?>" type="<?= $f['type'] === 'number' ? 'number' : 'text' ?>" value="<?= $f['role'] === 'quantity' ? '1' : '' ?>"><?php endif ?></div><?php endforeach ?>
      <?php foreach ($options as $o): if (!$o['active']) continue; ?><div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" data-option="<?= (int) $o['id'] ?>" id="so-<?= (int) $o['id'] ?>"><label class="form-check-label small" for="so-<?= (int) $o['id'] ?>"><?= e($o['name']) ?> (+<?= e(money((int) $o['price'])) ?>)</label></div><?php endforeach ?>
      <div class="col-12"><label class="form-label small mb-0" for="sim-hood">Quartier (frais de déplacement)</label><select class="form-select form-select-sm" id="sim-hood"><option value="">Sans déplacement</option><?php foreach ($hoods as $h): ?><option value="<?= (int) $h['id'] ?>"><?= e($h['name']) ?> (<?= $h['travel_fee'] ? e(money((int) $h['travel_fee'])) : 'offert' ?>)</option><?php endforeach ?></select></div>
    </form>
    <div id="sim-out" class="mt-3" aria-live="polite"></div>
  </div></div>
</div>

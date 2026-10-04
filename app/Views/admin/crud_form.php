<?php $type = $c['type']; $action = $row ? "/admin/crud/$type/" . (int) $row['id'] : "/admin/crud/$type"; ?>
<p class="mb-1"><a href="/admin/crud/<?= e($type) ?><?= e($filterQs) ?>">← <?= e($c['title']) ?></a></p>
<h1><?= e($mode) ?></h1>
<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="panel" novalidate>
  <?= csrf_field() ?>
  <div class="row g-3">
  <?php foreach ($c['fields'] as $k => $f):
      $t = $f['type']; $v = $values[$k] ?? ''; $id = 'f_' . $k; $err = $errors[$k] ?? null; $ro = !empty($f['readonly']);
      $req = !empty($f['required']) && !($t === 'password' && $row);
      $cls = 'form-control' . ($err ? ' is-invalid' : ''); $wide = in_array($t, ['textarea', 'pivot', 'weekdays'], true); ?>
    <div class="<?= $wide ? 'col-12' : 'col-md-6' ?>">
    <?php if ($t === 'bool'): ?>
      <div class="form-check pt-md-4"><input type="checkbox" class="form-check-input" id="<?= $id ?>" name="<?= e($k) ?>" value="1" <?= (string) $v === '1' || $v === 1 || $v === true ? 'checked' : '' ?>><label class="form-check-label" for="<?= $id ?>"><?= e($f['label']) ?></label></div>
    <?php else: ?>
      <label class="form-label" for="<?= $id ?>"><?= e($f['label']) ?><?= $req ? ' *' : '' ?></label>
      <?php if ($t === 'textarea'): ?><textarea class="<?= $cls ?>" id="<?= $id ?>" name="<?= e($k) ?>" rows="<?= (int) ($f['rows'] ?? 4) ?>" <?= $ro ? 'readonly' : '' ?>><?= e($v) ?></textarea>
      <?php elseif ($t === 'select'): ?><select class="form-select <?= $err ? 'is-invalid' : '' ?>" id="<?= $id ?>" name="<?= e($k) ?>"><option value="">—</option>
        <?php foreach ($opts[$k] as $ov => $ol): ?><option value="<?= e($ov) ?>" <?= (string) $v === (string) $ov ? 'selected' : '' ?>><?= e($ol) ?></option><?php endforeach ?></select>
      <?php elseif ($t === 'pivot'): ?><div class="row g-1">
        <?php foreach ($opts[$k] as $ov => $ol): ?><div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" id="<?= $id . $ov ?>" name="<?= e($k) ?>[]" value="<?= (int) $ov ?>" <?= in_array((string) $ov, array_map('strval', (array) $v), true) ? 'checked' : '' ?>><label class="form-check-label" for="<?= $id . $ov ?>"><?= e($ol) ?></label></div></div><?php endforeach ?></div>
      <?php elseif ($t === 'weekdays'): ?><div class="d-flex flex-wrap gap-3">
        <?php foreach (DAYS_FR as $n => $dl): ?><div class="form-check"><input class="form-check-input" type="checkbox" id="<?= $id . $n ?>" name="<?= e($k) ?>[]" value="<?= $n ?>" <?= in_array((string) $n, array_map('strval', is_array($v) ? $v : ($v === '' ? [] : explode(',', (string) $v))), true) ? 'checked' : '' ?>><label class="form-check-label" for="<?= $id . $n ?>"><?= e($dl) ?></label></div><?php endforeach ?></div>
      <?php elseif ($t === 'image'): ?><?php if ($v): ?><div class="mb-1"><img src="<?= e($v) ?>" alt="" style="max-height:70px;border-radius:8px"></div><?php endif ?><input class="form-control" type="file" id="<?= $id ?>" name="<?= e($k) ?>" accept="image/jpeg,image/png,image/webp">
      <?php else:
          $itype = ['number' => 'number', 'email' => 'email', 'tel' => 'tel', 'time' => 'time', 'date' => 'date', 'password' => 'password', 'decimal' => 'text'][$t] ?? 'text'; ?>
        <input class="<?= $cls ?>" id="<?= $id ?>" name="<?= e($k) ?>" type="<?= $itype ?>" value="<?= $t === 'password' ? '' : e($v) ?>" <?= isset($f['min']) ? 'min="' . (int) $f['min'] . '"' : '' ?> <?= isset($f['max']) ? 'max="' . (int) $f['max'] . '"' : '' ?> <?= $t === 'password' ? 'autocomplete="new-password"' : '' ?> <?= $ro ? 'readonly' : '' ?>>
      <?php endif ?>
    <?php endif ?>
      <?php if ($err): ?><div class="invalid-msg"><?= e($err) ?></div><?php endif ?>
      <?php if (!empty($f['help'])): ?><div class="form-text"><?= e($f['help']) ?></div><?php endif ?>
    </div>
  <?php endforeach ?>
  </div>
  <div class="mt-4 d-flex gap-2"><button class="btn btn-primary">Enregistrer</button><a class="btn btn-outline-secondary" href="/admin/crud/<?= e($type) ?><?= e($filterQs) ?>">Annuler</a></div>
</form>

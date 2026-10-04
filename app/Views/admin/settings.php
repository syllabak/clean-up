<h1>Paramètres</h1>
<form method="post" action="/admin/parametres" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <?php foreach ($groups as $group => $fields): ?>
  <div class="panel"><h2><?= e($group) ?></h2><div class="row g-3">
    <?php foreach ($fields as $key => $def): [$label, $type] = $def; $help = $def[2] ?? ''; $v = setting($key, ''); ?>
    <div class="col-md-6">
      <?php if ($type === 'bool'): ?>
        <div class="form-check pt-4"><input type="hidden" name="<?= e($key) ?>" value="0"><input class="form-check-input" type="checkbox" id="s_<?= e($key) ?>" name="<?= e($key) ?>" value="1" <?= $v === '1' ? 'checked' : '' ?>><label class="form-check-label" for="s_<?= e($key) ?>"><?= e($label) ?></label></div>
      <?php else: ?>
        <label class="form-label" for="s_<?= e($key) ?>"><?= e($label) ?></label>
        <?php if ($type === 'textarea'): ?><textarea class="form-control" id="s_<?= e($key) ?>" name="<?= e($key) ?>" rows="3"><?= e($v) ?></textarea>
        <?php elseif ($type === 'image'): ?><?php if ($v): ?><div class="mb-1"><img src="<?= e($v) ?>" alt="" style="max-height:70px;border-radius:8px"></div><?php endif ?><input class="form-control" type="file" id="s_<?= e($key) ?>" name="<?= e($key) ?>" accept="image/jpeg,image/png,image/webp">
        <?php else: ?><input class="form-control" id="s_<?= e($key) ?>" name="<?= e($key) ?>" type="<?= $type === 'number' ? 'number' : ($type === 'email' ? 'email' : 'text') ?>" value="<?= e($v) ?>"><?php endif ?>
      <?php endif ?>
      <?php if ($help): ?><div class="form-text"><?= e($help) ?></div><?php endif ?>
    </div>
    <?php endforeach ?>
  </div></div>
  <?php endforeach ?>
  <div class="panel"><h2>Notifications automatiques</h2>
    <p class="small text-muted">Cochez les envois à activer. Le contenu des messages se modifie dans « Modèles de messages ».</p>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Événement</th><th class="text-center">Email</th><th class="text-center">SMS</th></tr></thead><tbody>
      <?php foreach ($events as $ev => $label): ?><tr><td><?= e($label) ?></td>
        <?php foreach (['email', 'sms'] as $ch): $k = \App\Services\NotificationService::toggleKey($ev, $ch); ?>
        <td class="text-center"><input type="hidden" name="<?= e($k) ?>" value="0"><input class="form-check-input" type="checkbox" name="<?= e($k) ?>" value="1" <?= setting($k, '1') === '1' ? 'checked' : '' ?> aria-label="<?= e($label . ' — ' . $ch) ?>"></td><?php endforeach ?></tr><?php endforeach ?>
    </tbody></table></div>
  </div>
  <button class="btn btn-primary btn-lg">Enregistrer</button>
</form>

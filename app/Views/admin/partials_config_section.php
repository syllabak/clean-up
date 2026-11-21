<?php /* Un bloc de réglages : formulaire d'enregistrement, adresse de webhook, test. Variables : $sec, $def, $base (préfixe des routes). */ ?>
<form method="post" action="<?= e($base) ?>/<?= e($sec) ?>" class="panel" id="<?= e($sec) ?>" autocomplete="off">
  <?= csrf_field() ?>
  <h2><?= e($def['title']) ?></h2><p class="small text-muted"><?= e($def['intro']) ?></p>
  <?php if (!empty($def['webhook'])): ?><p class="small mb-3"><strong><?= e($def['webhook_label'] ?? 'Désinscription automatique (STOP) : donnez cette adresse à votre fournisseur pour les messages reçus') ?></strong> : <code><?= e(url($def['webhook'])) ?></code></p><?php endif ?>
  <div class="row g-3">
  <?php $ph = ['optout_text' => 'Répondez STOP pour ne plus recevoir nos offres.', 'batch_size' => '50', 'email_port' => '587', 'wa_version' => 'v21.0', 'wa_template_lang' => 'fr', 'wa_template_params' => '{client}'];
  foreach ($def['fields'] as $key => $f): [$label, $type] = $f; $help = $f[2] ?? ''; $opts = $f[3] ?? []; $v = (string) setting('mk_' . $key, ''); ?>
    <div class="col-md-6">
    <?php if ($type === 'bool'): ?>
      <div class="form-check pt-4"><input type="hidden" name="<?= e($key) ?>" value="0"><input class="form-check-input" type="checkbox" id="f_<?= e($key) ?>" name="<?= e($key) ?>" value="1" <?= $v === '1' ? 'checked' : '' ?>><label class="form-check-label" for="f_<?= e($key) ?>"><?= e($label) ?></label></div>
    <?php else: ?>
      <label class="form-label" for="f_<?= e($key) ?>"><?= e($label) ?></label>
      <?php if ($type === 'select'): ?><select class="form-select" id="f_<?= e($key) ?>" name="<?= e($key) ?>"><?php foreach ($opts as $ov => $ol): ?><option value="<?= e($ov) ?>" <?= (string) $ov === $v ? 'selected' : '' ?>><?= e($ol) ?></option><?php endforeach ?></select>
      <?php elseif ($type === 'textarea'): ?><textarea class="form-control" id="f_<?= e($key) ?>" name="<?= e($key) ?>" rows="3"><?= e($v) ?></textarea>
      <?php elseif ($type === 'secret'): ?><input class="form-control" id="f_<?= e($key) ?>" name="<?= e($key) ?>" type="password" value="" autocomplete="new-password" placeholder="<?= $v !== '' ? '•••••••• enregistré' : 'Non renseigné' ?>">
        <?php if ($v !== ''): ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" id="c_<?= e($key) ?>" name="clear_<?= e($key) ?>" value="1"><label class="form-check-label small" for="c_<?= e($key) ?>">Effacer la valeur enregistrée</label></div><?php endif ?>
      <?php else: ?><input class="form-control" id="f_<?= e($key) ?>" name="<?= e($key) ?>" type="<?= $type === 'number' ? 'number' : ($type === 'email' ? 'email' : 'text') ?>" value="<?= e($v) ?>" placeholder="<?= e($ph[$key] ?? '') ?>"><?php endif ?>
    <?php endif ?>
    <?php if ($help): ?><div class="form-text"><?= e($help) ?></div><?php endif ?>
    </div>
  <?php endforeach ?>
  </div>
  <div class="mt-3"><button class="btn btn-primary">Enregistrer</button></div>
</form>
<?php if (($def['test'] ?? '') === 'connexion'): ?>
<form method="post" action="<?= e($base) ?>/<?= e($sec) ?>/tester" class="panel -mt-2 d-flex flex-wrap gap-2 align-items-center"><?= csrf_field() ?>
  <button class="btn btn-outline-primary">Tester la connexion</button>
  <span class="small text-muted">Crée une demande de paiement d'essai (100 FCFA) chez <?= e($def['title']) ?> avec les clés enregistrées. Rien n'est débité tant que personne ne la règle.</span>
</form>
<?php elseif (!empty($def['test'])): ?>
<form method="post" action="<?= e($base) ?>/<?= e($sec) ?>/tester" class="panel -mt-2 d-flex flex-wrap gap-2 align-items-end">
  <?= csrf_field() ?>
  <div class="flex-grow-1"><label class="form-label small mb-1" for="t_<?= e($sec) ?>">Tester <?= e($def['title']) ?> : envoyer un message d'essai à</label>
    <input class="form-control" id="t_<?= e($sec) ?>" name="to" placeholder="<?= $def['test'] === 'email' ? 'vous@exemple.com' : '77 123 45 67' ?>" required></div>
  <button class="btn btn-outline-primary">Envoyer un test</button>
</form>
<?php endif ?>

<?php $err = fn(string $k) => $errors[$k] ?? null; $ch = $_SESSION['_old']['channel'] ?? 'whatsapp'; ?>
<h1>Nouvelle campagne</h1>
<form method="post" action="/admin/marketing/campagnes" class="panel">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label" for="name">Nom de la campagne</label><input class="form-control <?= $err('name') ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= old('name') ?>" required maxlength="150">
      <?php if ($err('name')): ?><div class="invalid-feedback"><?= e($err('name')) ?></div><?php endif ?></div>
    <div class="col-md-3"><label class="form-label" for="channel">Canal</label><select class="form-select" id="channel" name="channel">
      <?php foreach (\App\Services\MarketingService::CHANNELS as $k => $l): ?><option value="<?= e($k) ?>" <?= $ch === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select></div>
    <div class="col-md-3"><label class="form-label" for="scheduled_at">Envoi (facultatif)</label><input class="form-control <?= $err('scheduled_at') ? 'is-invalid' : '' ?>" type="datetime-local" id="scheduled_at" name="scheduled_at" value="<?= old('scheduled_at') ?>">
      <div class="form-text">Vide : dès le lancement.</div></div>
    <div class="col-md-6"><label class="form-label" for="audience">Public visé</label><select class="form-select" id="audience" name="audience">
      <?php foreach (\App\Services\MarketingService::AUDIENCES as $k => $l): ?><option value="<?= e($k) ?>" <?= old('audience', 'consent') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select>
      <div class="form-text">Seuls les clients qui ont accepté de recevoir des offres sont contactés.</div></div>
    <div class="col-md-6"><label class="form-label" for="audience_channel_id">Si « canal précis » : lequel ?</label><select class="form-select <?= $err('audience_channel_id') ? 'is-invalid' : '' ?>" id="audience_channel_id" name="audience_channel_id"><option value="">—</option>
      <?php foreach ($channels as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) old('audience_channel_id') === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?></select></div>
    <div class="col-12"><label class="form-label" for="subject">Objet (email uniquement)</label><input class="form-control <?= $err('subject') ? 'is-invalid' : '' ?>" id="subject" name="subject" value="<?= old('subject') ?>" maxlength="190">
      <?php if ($err('subject')): ?><div class="invalid-feedback"><?= e($err('subject')) ?></div><?php endif ?></div>
    <div class="col-12"><label class="form-label" for="body">Message</label><textarea class="form-control <?= $err('body') ? 'is-invalid' : '' ?>" id="body" name="body" rows="6" required maxlength="1500"><?= old('body', "Bonjour {client}, ce mois-ci : -10 % sur le nettoyage de canapés avec {entreprise}. Réservez en 2 minutes : {lien}") ?></textarea>
      <?php if ($err('body')): ?><div class="invalid-feedback"><?= e($err('body')) ?></div><?php endif ?>
      <div class="form-text">Variables : {client} {nom} {entreprise} {telephone} {lien}. Le lien est suivi : les réservations qui en viennent sont comptées pour cette campagne. La mention de désinscription est ajoutée automatiquement. Pour un SMS, restez court (160 caractères = 1 SMS).</div></div>
  </div>
  <div class="mt-3"><button class="btn btn-primary">Créer en brouillon</button> <a class="btn btn-outline-secondary" href="/admin/marketing/campagnes">Annuler</a></div>
</form>

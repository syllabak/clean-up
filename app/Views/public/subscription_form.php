<?php
$err = fn(string $k) => isset($errors[$k]) ? '<div class="invalid-msg">' . e($errors[$k]) . '</div>' : '';
$oldArr = fn(string $k): array => (array) ($_SESSION['_old'][$k] ?? []);
$freq = \App\Services\SubscriptionService::frequencyLabel($plan['frequency']);
$times = []; for ($m = 7 * 60; $m <= 17 * 60 + 30; $m += 30) $times[] = min_to_hm($m);
?>
<div class="page-head"><div class="container"><h1><?= e($plan['name']) ?></h1><p class="mb-0"><?= e($freq) ?><?= (int) $plan['discount_percent'] > 0 ? ' · −' . (int) $plan['discount_percent'] . ' % à chaque passage' : '' ?> · <?= e($plan['service_name']) ?></p></div></div>
<div class="container section" id="subform" data-service="<?= (int) $plan['service_id'] ?>" data-discount="<?= (int) $plan['discount_percent'] ?>" data-csrf="<?= e($csrf) ?>" data-currency="<?= e(setting('currency', 'FCFA')) ?>">
<form method="post" action="/abonnements/<?= (int) $plan['id'] ?>" novalidate class="row g-4">
  <?= csrf_field() ?>
  <div class="d-none" aria-hidden="true"><label for="website">Ne pas remplir</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
  <div class="col-lg-8">
    <div class="panel mb-3"><h2 class="h5">Vous êtes</h2>
      <div class="d-flex gap-3 mb-2"><?php foreach (['person' => 'Un particulier', 'business' => 'Une entreprise'] as $k => $l): if ($plan['audience'] !== 'all' && $plan['audience'] !== $k) continue; ?>
        <label class="form-check"><input class="form-check-input" type="radio" name="kind" value="<?= $k ?>" <?= (old('kind', $plan['audience'] === 'business' ? 'business' : 'person') === $k) ? 'checked' : '' ?>> <span class="form-check-label"><?= $l ?></span></label><?php endforeach ?></div><?= $err('kind') ?>
      <div class="mb-2" id="bizbox"><label class="form-label" for="business_name">Nom de l'entreprise</label><input class="form-control <?= isset($errors['business_name']) ? 'is-invalid' : '' ?>" id="business_name" name="business_name" value="<?= old('business_name') ?>"><?= $err('business_name') ?></div>
      <div class="row g-2">
        <div class="col-md-6"><label class="form-label" for="first_name">Prénom</label><input class="form-control <?= isset($errors['first_name']) ? 'is-invalid' : '' ?>" id="first_name" name="first_name" autocomplete="given-name" value="<?= old('first_name') ?>"><?= $err('first_name') ?></div>
        <div class="col-md-6"><label class="form-label" for="last_name">Nom</label><input class="form-control <?= isset($errors['last_name']) ? 'is-invalid' : '' ?>" id="last_name" name="last_name" autocomplete="family-name" value="<?= old('last_name') ?>"><?= $err('last_name') ?></div>
        <div class="col-md-6"><label class="form-label" for="phone">Téléphone (WhatsApp de préférence)</label><input class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="77 123 45 67" value="<?= old('phone') ?>"><?= $err('phone') ?></div>
        <div class="col-md-6"><label class="form-label" for="email">Email (facultatif)</label><input class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" id="email" name="email" type="email" autocomplete="email" value="<?= old('email') ?>"><?= $err('email') ?></div></div></div>

    <div class="panel mb-3"><h2 class="h5">Votre besoin</h2>
      <?php if ($formulas): ?><div class="mb-3"><label class="form-label" for="formula_id">Formule</label><select class="form-select <?= isset($errors['formula_id']) ? 'is-invalid' : '' ?>" id="formula_id" name="formula_id"><option value="">Choisir…</option>
        <?php foreach ($formulas as $f): ?><option value="<?= (int) $f['id'] ?>" <?= old('formula_id') === (string) $f['id'] ? 'selected' : '' ?>><?= e($f['name']) ?><?= $f['description'] ? ' — ' . e($f['description']) : '' ?> (<?= e(money((int) $f['amount'])) ?>)</option><?php endforeach ?></select><?= $err('formula_id') ?></div><?php endif ?>
      <?php foreach ($fields as $f): $k = $f['field_key']; ?>
      <div class="mb-3"><label class="form-label" for="f_<?= e($k) ?>"><?= e($f['label']) ?><?= $f['required'] ? '' : ' (facultatif)' ?></label>
        <?php if ($f['type'] === 'select'): ?><select class="form-select <?= isset($errors['fields.' . $k]) ? 'is-invalid' : '' ?>" id="f_<?= e($k) ?>" name="fields[<?= e($k) ?>]"><option value="">Choisir…</option><?php foreach ($f['choices'] as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (($_SESSION['_old']['fields'][$k] ?? '') === (string) $c['id']) ? 'selected' : '' ?>><?= e($c['label']) ?></option><?php endforeach ?></select>
        <?php else: ?><input class="form-control <?= isset($errors['fields.' . $k]) ? 'is-invalid' : '' ?>" id="f_<?= e($k) ?>" name="fields[<?= e($k) ?>]" type="number" inputmode="numeric" min="<?= (int) ($f['min_value'] ?? 0) ?>" max="<?= (int) ($f['max_value'] ?? 100000) ?>" value="<?= e($_SESSION['_old']['fields'][$k] ?? '') ?>"><?php endif ?>
        <?php if ($f['help']): ?><div class="form-text"><?= e($f['help']) ?></div><?php endif ?><?= $err('fields.' . $k) ?></div>
      <?php endforeach ?>
      <?php if ($options): ?><span class="form-label d-block">Options</span><?php foreach ($options as $o): ?>
        <label class="form-check d-block"><input class="form-check-input" type="checkbox" name="options[]" value="<?= (int) $o['id'] ?>" <?= in_array((string) $o['id'], array_map('strval', $oldArr('options')), true) ? 'checked' : '' ?>> <span class="form-check-label"><?= e($o['name']) ?> <small class="text-muted">+ <?= e(money((int) $o['price'])) ?></small></span></label><?php endforeach ?><?php endif ?></div>

    <div class="panel mb-3"><h2 class="h5">Où et quand</h2><div class="row g-2">
      <div class="col-md-6"><label class="form-label" for="neighborhood_id">Quartier</label><select class="form-select <?= isset($errors['neighborhood_id']) ? 'is-invalid' : '' ?>" id="neighborhood_id" name="neighborhood_id"><option value="">Choisir…</option><?php foreach ($hoods as $h): ?><option value="<?= (int) $h['id'] ?>" <?= old('neighborhood_id') === (string) $h['id'] ? 'selected' : '' ?>><?= e($h['name']) ?></option><?php endforeach ?></select><?= $err('neighborhood_id') ?></div>
      <div class="col-md-6"><label class="form-label" for="address_text">Adresse ou repère</label><input class="form-control <?= isset($errors['address_text']) ? 'is-invalid' : '' ?>" id="address_text" name="address_text" value="<?= old('address_text') ?>" placeholder="Rue, villa, près de…"><?= $err('address_text') ?></div>
      <div class="col-md-4"><label class="form-label" for="weekday">Jour du passage</label><select class="form-select <?= isset($errors['weekday']) ? 'is-invalid' : '' ?>" id="weekday" name="weekday"><option value="">Choisir…</option><?php foreach ([1, 2, 3, 4, 5, 6] as $d): ?><option value="<?= $d ?>" <?= old('weekday') === (string) $d ? 'selected' : '' ?>><?= e(DAYS_FR[$d]) ?></option><?php endforeach ?></select><?= $err('weekday') ?></div>
      <div class="col-md-4"><label class="form-label" for="start_time">Heure souhaitée</label><select class="form-select <?= isset($errors['start_time']) ? 'is-invalid' : '' ?>" id="start_time" name="start_time"><?php foreach ($times as $t): ?><option value="<?= $t ?>" <?= old('start_time', '09:00') === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach ?></select><?= $err('start_time') ?></div>
      <div class="col-md-4"><label class="form-label" for="start_date">Premier passage à partir du</label><input class="form-control <?= isset($errors['start_date']) ? 'is-invalid' : '' ?>" id="start_date" name="start_date" type="date" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" max="<?= date('Y-m-d', strtotime('+180 days')) ?>" value="<?= old('start_date', date('Y-m-d', strtotime('+7 day'))) ?>"><?= $err('start_date') ?></div>
      <div class="col-12"><label class="form-label" for="instructions">Instructions d'accès (facultatif)</label><input class="form-control" id="instructions" name="instructions" maxlength="1000" value="<?= old('instructions') ?>" placeholder="Code portail, étage, gardien…"></div>
      <div class="col-12"><label class="form-label" for="notes">Un message pour nous (facultatif)</label><textarea class="form-control" id="notes" name="notes" rows="2" maxlength="1500" placeholder="Plusieurs sites, facture, horaires particuliers…"><?= old('notes') ?></textarea></div></div></div>
    <p class="small text-muted">Aucun paiement en ligne : vous réglez chaque passage à l'équipe, après l'intervention. Nous vous appelons pour confirmer le jour et l'heure avant le premier passage.</p>
    <button class="btn btn-primary btn-lg">Envoyer ma demande d'abonnement</button>
  </div>
  <aside class="col-lg-4"><div class="summary" aria-live="polite">
    <p class="fw-bold mb-1">Estimation par passage</p>
    <p class="mb-0 text-muted small">Prix avec votre remise d'abonnement</p><p class="total mb-1" id="sub-total">—</p>
    <p class="small text-muted mb-0" id="sub-detail">Choisissez votre formule et votre quartier pour voir le prix.</p>
    <hr><p class="small mb-0"><?= e($plan['description']) ?></p>
    <?php if ($plan['service_conditions']): ?><p class="small text-muted mt-2 mb-0"><?= e($plan['service_conditions']) ?></p><?php endif ?>
  </div></aside>
</form></div>

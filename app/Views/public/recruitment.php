<?php $err = fn(string $k) => isset($errors[$k]) ? '<div class="invalid-msg">' . e($errors[$k]) . '</div>' : ''; $oldArr = fn(string $k): array => (array) ($_SESSION['_old'][$k] ?? []); ?>
<div class="page-head"><div class="container"><h1>Rejoindre l'équipe</h1><p class="mb-0">Remplissez ce formulaire, cela prend deux minutes. Nous vous rappelons ou vous écrivons sur WhatsApp.</p></div></div>
<div class="container section"><div class="row g-4">
  <div class="col-lg-4">
    <div class="panel">
      <h2 class="h5">Ce que nous cherchons</h2>
      <ul class="mb-3">
        <li>Des personnes sérieuses, ponctuelles et soigneuses</li>
        <li>Laveurs auto, nettoyage de canapés, matelas et tapis, ménage, bureaux, vitres</li>
        <li>Débutants bienvenus : la formation est assurée</li>
      </ul>
      <h2 class="h5">Comment ça se passe</h2>
      <ol class="mb-0">
        <li>Vous remplissez le formulaire</li>
        <li>Nous vous appelons pour un court entretien</li>
        <li>Un essai pratique, puis une journée de formation</li>
        <li>Votre première mission, près de chez vous</li>
      </ol>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="panel">
    <form method="post" action="/recrutement" novalidate>
      <?= csrf_field() ?>
      <div class="d-none" aria-hidden="true"><label for="website">Ne pas remplir</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
      <input type="hidden" name="src" value="<?= e(\App\Core\Request::str('src')) ?>">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label" for="name">Votre nom</label><input class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" autocomplete="name" value="<?= old('name') ?>" required><?= $err('name') ?></div>
        <div class="col-md-6"><label class="form-label" for="phone">Téléphone</label><input class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="77 123 45 67" value="<?= old('phone') ?>" required><?= $err('phone') ?></div>
        <div class="col-md-6"><label class="form-label" for="neighborhood_id">Votre quartier</label><select class="form-select" id="neighborhood_id" name="neighborhood_id"><option value="">Choisir…</option><?php foreach ($hoods as $h): ?><option value="<?= (int) $h['id'] ?>" <?= old('neighborhood_id') === (string) $h['id'] ? 'selected' : '' ?>><?= e($h['name']) ?></option><?php endforeach ?></select></div>
        <div class="col-md-6"><label class="form-label" for="email">Email (facultatif)</label><input class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" id="email" name="email" type="email" autocomplete="email" value="<?= old('email') ?>"><?= $err('email') ?></div>

        <div class="col-12"><span class="form-label d-block">Ce que vous savez faire</span>
          <div class="d-flex flex-wrap gap-2"><?php foreach ($skills as $k => $l): $on = in_array($k, $oldArr('skills'), true); ?>
            <label class="form-check"><input class="form-check-input" type="checkbox" name="skills[]" value="<?= e($k) ?>" <?= $on ? 'checked' : '' ?>> <span class="form-check-label"><?= e($l) ?></span></label><?php endforeach ?></div><?= $err('skills') ?></div>

        <div class="col-md-6"><label class="form-label" for="experience">Votre expérience</label><select class="form-select <?= isset($errors['experience']) ? 'is-invalid' : '' ?>" id="experience" name="experience" required><option value="">Choisir…</option><?php foreach ($experience as $k => $l): ?><option value="<?= e($k) ?>" <?= old('experience') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select><?= $err('experience') ?></div>
        <div class="col-md-6"><label class="form-label" for="availability">Votre disponibilité</label><select class="form-select <?= isset($errors['availability']) ? 'is-invalid' : '' ?>" id="availability" name="availability" required><option value="">Choisir…</option><?php foreach ($availability as $k => $l): ?><option value="<?= e($k) ?>" <?= old('availability') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select><?= $err('availability') ?></div>

        <div class="col-12"><span class="form-label d-block">Avez-vous WhatsApp sur ce numéro ?</span>
          <label class="form-check form-check-inline"><input class="form-check-input" type="radio" name="has_whatsapp" value="1" <?= old('has_whatsapp', '1') !== '0' ? 'checked' : '' ?>> <span class="form-check-label">Oui</span></label>
          <label class="form-check form-check-inline"><input class="form-check-input" type="radio" name="has_whatsapp" value="0" <?= old('has_whatsapp') === '0' ? 'checked' : '' ?>> <span class="form-check-label">Non</span></label></div>

        <div class="col-12"><label class="form-label" for="message">Un mot pour nous (facultatif)</label><textarea class="form-control" id="message" name="message" rows="3" maxlength="1500" placeholder="Votre parcours, ce qui vous motive…"><?= old('message') ?></textarea></div>

        <div class="col-12">
          <label class="form-check d-block"><input class="form-check-input" type="checkbox" name="adult" value="1" <?= old('adult') === '1' ? 'checked' : '' ?>> <span class="form-check-label">J'ai 18 ans ou plus.</span></label><?= $err('adult') ?>
          <label class="form-check d-block mt-1"><input class="form-check-input" type="checkbox" name="consent" value="1" <?= old('consent') === '1' ? 'checked' : '' ?>> <span class="form-check-label">J'accepte que <?= e(setting('company_name')) ?> me contacte au sujet de ma candidature et conserve ces informations pour la traiter.</span></label><?= $err('consent') ?>
        </div>
        <div class="col-12"><button class="btn btn-primary btn-lg">Envoyer ma candidature</button></div>
      </div>
    </form>
    </div>
  </div>
</div></div>

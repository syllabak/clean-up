<div class="page-head"><div class="container"><h1>Contact</h1></div></div>
<div class="container section"><div class="row g-4">
  <div class="col-md-5">
    <p><strong>Téléphone</strong><br><a href="tel:<?= e(preg_replace('/[^\d+]/', '', (string) setting('company_phone'))) ?>"><?= e(setting('company_phone')) ?></a></p>
    <p><strong>Email</strong><br><a href="mailto:<?= e(setting('company_email')) ?>"><?= e(setting('company_email')) ?></a></p>
    <p><strong>Adresse</strong><br><?= e(setting('company_address')) ?></p>
    <p><strong>Horaires</strong><br><?= e(setting('opening_text')) ?></p>
  </div>
  <div class="col-md-7">
    <form method="post" action="/contact" novalidate>
      <?= csrf_field() ?>
      <div class="d-none" aria-hidden="true"><label for="website">Ne pas remplir</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
      <?php foreach ([['name', 'Votre nom', 'text', 'name'], ['phone', 'Téléphone', 'tel', 'tel'], ['email', 'Email', 'email', 'email']] as [$n, $l, $t, $ac]): ?>
      <div class="mb-3"><label class="form-label" for="<?= $n ?>"><?= $l ?></label><input class="form-control <?= isset($errors[$n]) ? 'is-invalid' : '' ?>" id="<?= $n ?>" name="<?= $n ?>" type="<?= $t ?>" autocomplete="<?= $ac ?>" value="<?= old($n) ?>"><?php if (isset($errors[$n])): ?><div class="invalid-msg"><?= e($errors[$n]) ?></div><?php endif ?></div>
      <?php endforeach ?>
      <div class="mb-3"><label class="form-label" for="message">Message</label><textarea class="form-control <?= isset($errors['message']) ? 'is-invalid' : '' ?>" id="message" name="message" rows="5"><?= old('message') ?></textarea><?php if (isset($errors['message'])): ?><div class="invalid-msg"><?= e($errors['message']) ?></div><?php endif ?></div>
      <button class="btn btn-primary btn-lg">Envoyer</button>
    </form>
  </div>
</div></div>

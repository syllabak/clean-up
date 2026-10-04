<div class="page-head"><div class="container"><h1>Suivre ma réservation</h1></div></div>
<div class="container section" style="max-width:520px">
  <form method="post" action="/suivi" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label" for="reference">Numéro de référence</label><input class="form-control" id="reference" name="reference" value="<?= old('reference') ?>" placeholder="NX-261004-AB12" required autocomplete="off"></div>
    <div class="mb-3"><label class="form-label" for="phone">Téléphone utilisé pour la réservation</label><input class="form-control" id="phone" name="phone" type="tel" inputmode="tel" required></div>
    <button class="btn btn-primary btn-lg">Afficher</button>
  </form>
</div>

<div class="page-head"><div class="container"><h1>Réserver une prestation</h1></div></div>
<div class="container section wiz" id="wizard" data-currency="<?= e($currency) ?>" data-csrf="<?= e(\App\Core\Csrf::token()) ?>" data-preselect="<?= e($preselect) ?>" data-preformula="<?= (int) $preformula ?>">
  <script type="application/json" id="services-data"><?= json_encode($services, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <ol class="wiz-progress" id="progress">
    <li>Votre besoin</li><li>Où et quand</li><li>Vos coordonnées</li>
  </ol>
  <div class="row g-4">
    <div class="col-lg-8">
      <div id="msg" class="alert alert-danger d-none" role="alert"></div>

      <section class="wiz-step" data-step="0" aria-labelledby="t0">
        <h2 class="h4" id="t0">Quel service souhaitez-vous ?</h2>
        <div class="row g-2 mt-1" id="service-list"></div>
      </section>

      <section class="wiz-step d-none" data-step="1" id="sec-needs" aria-labelledby="t1">
        <h2 class="h4" id="t1">Vos besoins</h2>
        <p class="text-muted small" id="svc-cond"></p>
        <div id="formulas" class="mb-3"></div>
        <div id="fields" class="row g-3 mb-3"></div>
        <div id="options-wrap" class="d-none"><h3 class="h6">Options supplémentaires</h3><div id="options" class="row g-2"></div></div>
      </section>

      <section class="wiz-step" data-step="2" aria-labelledby="t2">
        <h2 class="h4" id="t2">Où intervenons-nous ?</h2>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label" for="hood">Votre quartier</label><select id="hood" class="form-select"><option value="">Chargement…</option></select></div>
          <div class="col-md-6"><label class="form-label" for="address">Adresse précise</label><input id="address" class="form-control" maxlength="500" autocomplete="street-address" placeholder="Rue, villa, repère connu…"></div>
          <div class="col-12"><p id="hood-info" class="small text-muted mb-0"></p></div>
          <div class="col-12"><details><summary class="small text-primary">Ajouter des instructions d'accès (code portail, étage…)</summary><textarea id="instructions" class="form-control mt-2" rows="2" maxlength="1000" placeholder="Code portail, étage, où se garer…"></textarea></details></div>
        </div>
      </section>

      <section class="wiz-step" data-step="3" aria-labelledby="t3">
        <h2 class="h4" id="t3">Comment vous joindre ?</h2>
        <p class="small text-muted" id="prefill-note" hidden>Informations reprises de votre dernière réservation, gardées sur cet appareil uniquement. <a href="#" id="prefill-clear">Ce n'est pas vous ? Effacer</a></p>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label" for="full_name">Votre nom</label><input id="full_name" class="form-control" autocomplete="name" maxlength="200" placeholder="Prénom et nom"></div>
          <div class="col-md-6"><label class="form-label" for="phone">Téléphone</label><input id="phone" class="form-control" type="tel" inputmode="tel" autocomplete="tel" placeholder="77 123 45 67"></div>
          <div class="col-12"><details><summary class="small text-primary">Email ou autres précisions (facultatif)</summary><div class="row g-3 mt-0">
            <div class="col-md-6"><label class="form-label" for="email">Email</label><input id="email" class="form-control" type="email" autocomplete="email" maxlength="190"></div>
            <?php if (!empty($channels)): ?><div class="col-md-6"><label class="form-label" for="channel">Comment nous avez-vous connus ?</label><select id="channel" class="form-select"><option value="">—</option>
              <?php foreach ($channels as $ch): ?><option value="<?= (int) $ch['id'] ?>"><?= e($ch['name']) ?></option><?php endforeach ?></select></div><?php endif ?>
          </div></details></div>
          <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="consent" value="1"><label class="form-check-label small" for="consent">Je veux recevoir les offres de <?= e(setting('company_name', 'Clean Up')) ?> par SMS, WhatsApp ou email (facultatif, désinscription à tout moment en répondant STOP).</label></div></div>
          <div class="d-none" aria-hidden="true"><label for="website">Ne pas remplir</label><input id="website" tabindex="-1" autocomplete="off"></div>
        </div>
      </section>

      <section class="wiz-step" data-step="4" aria-labelledby="t4">
        <h2 class="h4" id="t4">Choisissez un créneau</h2>
        <p class="text-muted small">Durée estimée : <strong id="dur-label"></strong>. Les créneaux marqués d'un liseré jaune regroupent nos passages dans votre quartier.</p>
        <div id="days-loading" class="text-muted">Choisissez votre quartier pour voir les créneaux disponibles.</div>
        <div id="days" class="day-strip mb-3" role="group" aria-label="Jours disponibles"></div>
        <div id="slots-wrap" class="d-none"><h3 class="h6" id="slots-title"></h3><div id="slots" class="slot-grid" role="group" aria-label="Heures disponibles"></div></div>
      </section>

      <section class="wiz-step" data-step="5" aria-labelledby="t5">
        <h2 class="h4" id="t5">Vérifiez avant de valider</h2>
        <div class="panel" id="recap"></div>
        <p class="small text-muted">En validant, vous acceptez les <a href="/conditions-generales" target="_blank">conditions générales</a>. Aucun paiement en ligne : vous réglez directement avec notre équipe.</p>
      </section>

      <div class="d-flex justify-content-between mt-4">
        <button type="button" class="btn btn-outline-secondary" id="prev">Retour</button>
        <button type="button" class="btn btn-primary btn-lg" id="next">Continuer</button>
      </div>
    </div>
    <aside class="col-lg-4"><div class="summary" aria-live="polite">
      <p class="fw-bold mb-1">Votre réservation</p>
      <dl id="side-summary"><dt>Service</dt><dd>—</dd></dl>
      <hr><p class="mb-0 text-muted small">Total estimé</p><p class="total mb-0" id="side-total">—</p>
    </div></aside>
  </div>
  <div class="mobile-total" aria-hidden="true"><span>Total estimé</span><strong id="mobile-total">—</strong></div>
</div>

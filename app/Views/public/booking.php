<div class="page-head"><div class="container"><h1>Réserver une prestation</h1></div></div>
<div class="container section wiz" id="wizard" data-currency="<?= e($currency) ?>" data-csrf="<?= e(\App\Core\Csrf::token()) ?>" data-preselect="<?= e($preselect) ?>">
  <script type="application/json" id="services-data"><?= json_encode($services, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <ol class="wiz-progress" id="progress">
    <li>Service</li><li>Détails</li><li>Lieu</li><li>Vos coordonnées</li><li>Date et heure</li><li>Récapitulatif</li>
  </ol>
  <div class="row g-4">
    <div class="col-lg-8">
      <div id="msg" class="alert alert-danger d-none" role="alert"></div>

      <section class="wiz-step" data-step="0" aria-labelledby="t0">
        <h2 class="h4" id="t0">Quel service souhaitez-vous ?</h2>
        <div class="row g-2 mt-1" id="service-list"></div>
      </section>

      <section class="wiz-step" data-step="1" aria-labelledby="t1">
        <h2 class="h4" id="t1">Vos besoins</h2>
        <p class="text-muted small" id="svc-cond"></p>
        <div id="formulas" class="mb-3"></div>
        <div id="fields" class="row g-3 mb-3"></div>
        <div id="options-wrap" class="d-none"><h3 class="h6">Options supplémentaires</h3><div id="options" class="row g-2"></div></div>
      </section>

      <section class="wiz-step" data-step="2" aria-labelledby="t2">
        <h2 class="h4" id="t2">Où intervenons-nous ?</h2>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label" for="region">Région</label><select id="region" class="form-select"></select></div>
          <div class="col-md-6"><label class="form-label" for="city">Ville</label><select id="city" class="form-select" disabled></select></div>
          <div class="col-md-6"><label class="form-label" for="commune">Commune</label><select id="commune" class="form-select" disabled></select></div>
          <div class="col-md-6"><label class="form-label" for="hood">Quartier</label><select id="hood" class="form-select" disabled></select></div>
          <div class="col-12"><p id="hood-info" class="small text-muted mb-0"></p></div>
          <div class="col-12"><label class="form-label" for="address">Adresse précise</label><textarea id="address" class="form-control" rows="2" maxlength="500" placeholder="Rue, numéro de villa, repère connu…"></textarea></div>
          <div class="col-12"><label class="form-label" for="instructions">Instructions d'accès (facultatif)</label><textarea id="instructions" class="form-control" rows="2" maxlength="1000" placeholder="Code portail, étage, où se garer…"></textarea></div>
        </div>
      </section>

      <section class="wiz-step" data-step="3" aria-labelledby="t3">
        <h2 class="h4" id="t3">Comment vous joindre ?</h2>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label" for="first_name">Prénom</label><input id="first_name" class="form-control" autocomplete="given-name" maxlength="100"></div>
          <div class="col-md-6"><label class="form-label" for="last_name">Nom</label><input id="last_name" class="form-control" autocomplete="family-name" maxlength="100"></div>
          <div class="col-md-6"><label class="form-label" for="phone">Téléphone</label><input id="phone" class="form-control" type="tel" inputmode="tel" autocomplete="tel" placeholder="77 123 45 67"></div>
          <div class="col-md-6"><label class="form-label" for="email">Email (facultatif)</label><input id="email" class="form-control" type="email" autocomplete="email" maxlength="190"></div>
          <div class="d-none" aria-hidden="true"><label for="website">Ne pas remplir</label><input id="website" tabindex="-1" autocomplete="off"></div>
        </div>
      </section>

      <section class="wiz-step" data-step="4" aria-labelledby="t4">
        <h2 class="h4" id="t4">Choisissez un créneau</h2>
        <p class="text-muted small">Durée estimée : <strong id="dur-label"></strong>. Les créneaux marqués d'un liseré jaune regroupent nos passages dans votre quartier.</p>
        <div id="days-loading" class="text-muted">Chargement des disponibilités…</div>
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
</div>

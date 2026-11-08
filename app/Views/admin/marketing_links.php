<h1>Liens suivis</h1>
<p class="text-muted">Un lien étiqueté indique d'où vient chaque visiteur. Mettez-le dans vos annonces, vos messages et vos affiches (en QR code) : les réservations sont rattachées au bon canal, avec le coût par client et le retour sur investissement dans « Gestion et résultats ».</p>
<form method="get" action="/admin/marketing/liens" class="panel">
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label" for="preset">Je veux un lien pour</label><select class="form-select" id="preset" name="preset"><option value="">— personnalisé —</option>
      <?php foreach ($presets as $k => [$l]): ?><option value="<?= e($k) ?>" <?= \App\Core\Request::str('preset') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select></div>
    <div class="col-md-4"><label class="form-label" for="page">Page</label><select class="form-select" id="page" name="page"><?php foreach ($pages as $p => $l): ?><option value="<?= e($p) ?>" <?= $page === $p ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select></div>
    <div class="col-md-4"><label class="form-label" for="campaign">Nom de la campagne</label><input class="form-control" id="campaign" name="campaign" value="<?= e($campaign) ?>" placeholder="ex. promo-canapes-novembre"></div>
    <div class="col-md-6"><label class="form-label" for="source">Source (si personnalisé)</label><input class="form-control" id="source" name="source" value="<?= e(\App\Core\Request::str('source')) ?>" placeholder="facebook, google…"></div>
    <div class="col-md-6"><label class="form-label" for="medium">Type (si personnalisé)</label><input class="form-control" id="medium" name="medium" value="<?= e(\App\Core\Request::str('medium')) ?>" placeholder="cpc, paid_social, message…"></div>
  </div>
  <div class="mt-3"><button class="btn btn-primary">Générer le lien</button></div>
</form>
<?php if ($url): ?><div class="panel"><h2>Votre lien</h2><input class="form-control" readonly value="<?= e($url) ?>" aria-label="Lien suivi"><p class="small text-muted mt-2 mb-0">Source « <?= e($source) ?> », type « <?= e($medium) ?> »<?= $campaign !== '' ? ', campagne « ' . e(slugify($campaign)) . ' »' : '' ?>.</p></div><?php endif ?>
<div class="panel"><h2>Conseils par plateforme</h2><ul class="small mb-0">
  <li><strong>Google Ads</strong> : activez le « balisage automatique » dans les paramètres du compte. Chaque clic porte alors un identifiant (gclid) reconnu par le site, sans lien spécial.</li>
  <li><strong>Facebook et Instagram Ads</strong> : dans l'annonce, champ « Paramètres d'URL » : <code>utm_source=facebook&amp;utm_medium=paid_social&amp;utm_campaign={{campaign.name}}</code>. Les clics portent aussi un identifiant (fbclid).</li>
  <li><strong>WhatsApp, SMS, email</strong> : les campagnes de l'application ajoutent le lien suivi toute seule via la variable {lien}.</li>
  <li><strong>Affiches et flyers</strong> : générez le lien « Flyer », puis transformez-le en QR code.</li></ul></div>

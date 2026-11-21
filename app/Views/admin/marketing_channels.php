<h1>Canaux marketing</h1>
<p class="text-muted">Tout se règle ici : aucune modification de fichier sur le serveur. Les clés et mots de passe ne sont jamais réaffichés ; laissez le champ vide pour garder la valeur enregistrée.</p>
<?php foreach ($sections as $sec => $def): $base = '/admin/marketing/canaux'; ?>
<?= \App\Core\View::partial('admin/partials_config_section', compact('sec', 'def', 'base')) ?>
<?php endforeach ?>
<div class="panel"><h2>Publicité en ligne : mise en route</h2>
  <ol class="mb-0 small">
    <li>Créez vos annonces dans <strong>Meta Ads Manager</strong> et <strong>Google Ads</strong> : ces plateformes gardent la main sur la création, le budget et le ciblage.</li>
    <li>Collez ici les identifiants (pixel Meta, balise Google) pour que le site mesure les réservations venues de vos annonces, avec l'accord du visiteur.</li>
    <li>Dans chaque annonce, utilisez un lien généré dans <a href="/admin/marketing/liens">Liens suivis</a> : chaque réservation est ensuite rattachée au bon canal, et le coût par client apparaît dans « Gestion et résultats ».</li>
    <li>Saisissez ce que vous dépensez en publicité dans <a href="/admin/crud/expenses">Dépenses</a>, rattaché au canal, pour obtenir le retour sur investissement.</li>
  </ol></div>

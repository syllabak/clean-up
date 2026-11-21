<h1>Messagerie et paiement</h1>
<p class="text-muted">Tout se règle ici : aucune modification de fichier sur le serveur. Les clés et mots de passe ne sont jamais réaffichés ; laissez le champ vide pour garder la valeur enregistrée.</p>
<div class="panel"><h2 class="h6">Où en êtes-vous ?</h2>
  <ul class="mb-0 small">
    <li>Email : <strong><?= e($status['email']) ?></strong></li>
    <li>SMS : <strong><?= e($status['sms']) ?></strong></li>
    <li>Paiement en ligne : <strong><?= e($status['payment']) ?></strong></li>
  </ul>
  <p class="small text-muted mt-2 mb-0">Le client est prévenu par email s'il en a donné un, sinon par SMS (réglage dans Paramètres → Notifications).</p></div>
<?php foreach ($sections as $sec => $def): ?>
<?= \App\Core\View::partial('admin/partials_config_section', compact('sec', 'def', 'base')) ?>
<?php endforeach ?>

<div class="page-head"><div class="container"><h1>Candidature reçue</h1></div></div>
<div class="container section" style="max-width:720px">
  <p class="fs-5">Merci, nous avons bien reçu votre candidature.</p>
  <p>Nous vous appelons ou vous écrivons sur WhatsApp dans les prochains jours pour fixer un court entretien. Gardez votre téléphone près de vous.</p>
  <?php $wa = preg_replace('/\D/', '', (string) setting('company_whatsapp', '')); ?>
  <p class="d-flex flex-wrap gap-2">
    <?php if ($wa !== ''): ?><a class="btn btn-outline-success" target="_blank" rel="noopener" href="<?= e(wa_link($wa, 'Bonjour, je viens de déposer ma candidature sur le site.')) ?>">Nous écrire sur WhatsApp</a><?php endif ?>
    <a class="btn btn-primary" href="/">Retour à l'accueil</a>
  </p>
</div>

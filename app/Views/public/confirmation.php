<div class="container section" style="max-width:760px">
  <h1 class="h2">Votre réservation est enregistrée</h1>
  <p class="lead">Merci <?= e($b['customer_first_name']) ?>. Gardez précieusement votre numéro de référence :</p>
  <p><span class="ref-box"><?= e($b['reference']) ?></span></p>
  <div class="panel">
    <p class="mb-1"><strong><?= e($b['service_name']) ?></strong></p>
    <p class="mb-1">Le <?= e(fmt_date($b['scheduled_date'])) ?> à <?= e($b['start_time']) ?> (durée estimée : <?= e(fmt_duration((int) $b['duration_minutes'])) ?>)</p>
    <p class="mb-1">Adresse : <?= e($b['address_text']) ?> — <?= e($b['neighborhood_name']) ?></p>
    <?php foreach ($items as $it): foreach (json_decode((string) $it['details'], true) ?: [] as [$k, $v]): ?><p class="mb-0 small text-muted"><?= e($k) ?> : <?= e($v) ?></p><?php endforeach; endforeach ?>
    <hr><p class="mb-0 fs-5">Total estimé : <strong><?= money((int) $b['total']) ?></strong> <span class="small text-muted">(à régler à l'équipe)</span></p>
  </div>
  <h2 class="h5 mt-4">Et maintenant ?</h2>
  <ol class="steps mb-4">
    <li><strong>Nous confirmons</strong><br>Nous vous contactons très vite au <?= e(phone_fmt($b['customer_phone'])) ?> pour confirmer votre créneau.</li>
    <li><strong>Nous vous prévenons</strong><br>Vous recevez l'équipe affectée, puis un rappel avant l'intervention.</li>
    <li><strong>Nous venons chez vous</strong><br>Merci de prévoir l'accès<?= $b['instructions'] ? ' (' . e(mb_strimwidth((string) $b['instructions'], 0, 60, '…')) . ')' : '' ?>. Vous réglez directement à l'équipe.</li>
  </ol>
  <?php $wa = preg_replace('/\D/', '', (string) setting('company_whatsapp', '')); ?>
  <p class="d-flex flex-wrap gap-2"><a class="btn btn-primary" href="/suivi/<?= e($b['reference']) ?>">Suivre ma réservation</a>
    <a class="btn btn-outline-primary" href="/suivi/<?= e($b['reference']) ?>/calendrier">Ajouter à mon agenda</a>
    <?php if ($wa): ?><a class="btn btn-outline-secondary" target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Bonjour, ma réservation ' . $b['reference']) ?>">Écrire sur WhatsApp</a><?php endif ?>
    <a class="btn btn-outline-secondary" href="/">Retour à l'accueil</a></p>
  <?php if (!empty($track_link)): ?>
  <div class="panel small"><strong>Votre lien de suivi personnel</strong> : vous le recevez aussi par message. Gardez-le : il vous évite de retaper la référence et le téléphone.
    <div class="mt-1"><code class="text-break"><?= e($track_link) ?></code></div>
    <a class="d-inline-block mt-2" target="_blank" rel="noopener" href="https://wa.me/<?= e(preg_replace('/\D/', '', (string) $b['customer_phone'])) ?>?text=<?= rawurlencode('Mon suivi ' . $b['reference'] . ' : ' . $track_link) ?>">Me l'envoyer sur WhatsApp</a></div>
  <?php endif ?>
</div>

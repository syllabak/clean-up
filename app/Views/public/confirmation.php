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
  <p>Votre réservation est <strong>en attente de confirmation</strong>. Nous vous contactons très vite au <?= e(phone_fmt($b['customer_phone'])) ?>.</p>
  <p><a class="btn btn-primary" href="/suivi/<?= e($b['reference']) ?>">Suivre ma réservation</a> <a class="btn btn-outline-secondary" href="/">Retour à l'accueil</a></p>
</div>

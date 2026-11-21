<?php $paid = !empty($b['paid_at']); ?>
<?php if ($waiting): ?><meta http-equiv="refresh" content="8"><?php endif ?>
<div class="page-head"><div class="container"><h1><?= $paid ? 'Paiement reçu' : ($cancelled ? 'Paiement annulé' : 'Paiement en cours') ?></h1></div></div>
<div class="container section" style="max-width:640px">
  <?php if ($paid): ?>
    <p class="fs-5">Merci, nous avons bien reçu votre paiement de <strong><?= e(money((int) $b['total'])) ?></strong> pour la réservation <strong><?= e($b['reference']) ?></strong>.</p>
    <p>Rien d'autre à régler : notre équipe vient à la date prévue.</p>
  <?php elseif ($cancelled): ?>
    <p class="fs-5">Le paiement a été annulé. Aucune somme n'a été prélevée.</p>
    <p>Votre réservation <strong><?= e($b['reference']) ?></strong> reste valable : vous pouvez réessayer, ou régler l'équipe après l'intervention.</p>
  <?php elseif ($waiting): ?>
    <p class="fs-5">Nous attendons la confirmation de votre paiement.</p>
    <p class="text-muted">Cela prend souvent quelques secondes, parfois une minute avec Mobile Money. Cette page se met à jour toute seule.</p>
  <?php else: ?>
    <p class="fs-5">Nous n'avons pas encore reçu la confirmation de votre paiement.</p>
    <p>Si vous avez bien payé, ne payez pas une deuxième fois : la confirmation peut arriver avec un peu de retard. Sinon, vous pouvez réessayer ou régler l'équipe après l'intervention. En cas de doute, écrivez-nous en indiquant la référence <strong><?= e($b['reference']) ?></strong>.</p>
  <?php endif ?>
  <p class="d-flex flex-wrap gap-2"><a class="btn btn-primary" href="<?= e($link) ?>">Voir ma réservation</a><a class="btn btn-outline-secondary" href="/">Retour à l'accueil</a></p>
</div>

<?php $freq = fn(string $f) => \App\Services\SubscriptionService::frequencyLabel($f); ?>
<div class="page-head"><div class="container"><h1>Abonnements</h1><p class="mb-0">Un passage régulier, au même jour et à la même heure, avec une remise à chaque visite. Pour les particuliers comme pour les entreprises.</p></div></div>
<div class="container section">
  <div class="row g-3 mb-4">
    <?php foreach ([['1', 'Vous choisissez une offre', 'et vos préférences de jour et d\'heure.'], ['2', 'Nous confirmons avec vous', 'par un appel ou un message WhatsApp.'], ['3', 'Nos équipes passent', 'automatiquement : vous n\'avez plus à réserver.']] as [$n, $t, $d]): ?>
    <div class="col-md-4"><div class="panel h-100"><p class="mb-1"><span class="badge text-bg-warning me-1"><?= $n ?></span><strong><?= e($t) ?></strong></p><p class="mb-0 small text-muted"><?= e($d) ?></p></div></div>
    <?php endforeach ?>
  </div>
  <?php if (!$plans): ?><p class="text-muted">Aucune offre d'abonnement n'est ouverte pour le moment. <a href="/contact">Contactez-nous</a> : nous pouvons étudier votre besoin.</p><?php endif ?>
  <div class="row g-3">
    <?php foreach ($plans as $p): ?>
    <div class="col-md-6 col-lg-4"><div class="panel h-100 d-flex flex-column">
      <p class="mb-1"><span class="badge text-bg-secondary"><?= e($freq($p['frequency'])) ?></span> <?php if ((int) $p['discount_percent'] > 0): ?><span class="badge text-bg-success">−<?= (int) $p['discount_percent'] ?> % à chaque passage</span><?php endif ?>
        <?php if ($p['audience'] === 'business'): ?><span class="badge text-bg-dark">Entreprises</span><?php endif ?></p>
      <h2 class="h5"><?= e($p['name']) ?></h2>
      <p class="small text-muted mb-1"><?= e($p['service_name']) ?></p>
      <p class="flex-grow-1"><?= e($p['description']) ?></p>
      <a class="btn btn-primary" href="/abonnements/<?= (int) $p['id'] ?>">Je m'abonne</a>
    </div></div>
    <?php endforeach ?>
  </div>
  <div class="panel mt-4"><h2 class="h5">Questions fréquentes</h2>
    <p class="mb-1"><strong>Comment je paie ?</strong> Comme pour une réservation : après chaque intervention, directement à l'équipe. Pas de prélèvement.</p>
    <p class="mb-1"><strong>Puis-je arrêter ?</strong> Oui, à tout moment, depuis votre espace abonnement : pause de quelques semaines ou résiliation. Les passages à venir sont alors annulés.</p>
    <p class="mb-1"><strong>Et si un jour ne me convient pas ?</strong> Vous annulez ce passage seul, l'abonnement continue. Vous pouvez aussi nous écrire sur WhatsApp.</p>
    <p class="mb-0"><strong>Une entreprise avec plusieurs sites ?</strong> <a href="/contact">Parlons-en</a> : nous préparons une offre adaptée.</p></div>
</div>

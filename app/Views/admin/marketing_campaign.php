<?php [$sl, $sc] = \App\Services\MarketingService::STATUSES[$c['status']] ?? [$c['status'], 'light']; $id = (int) $c['id']; ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-2"><h1 class="mb-0 me-auto"><?= e($c['name']) ?></h1><span class="badge text-bg-<?= e($sc) ?> fs-6"><?= e($sl) ?></span></div>
<p class="text-muted"><?= e(\App\Services\MarketingService::CHANNELS[$c['channel']]) ?> · <?= e(\App\Services\MarketingService::AUDIENCES[$c['audience']] ?? '') ?><?= $c['scheduled_at'] ? ' · prévue le ' . e(date('d/m/Y H:i', strtotime($c['scheduled_at'] . ' UTC'))) : '' ?></p>
<?php if ($problem): ?><div class="alert alert-warning"><?= e($problem) ?></div><?php endif ?>

<div class="row g-3">
  <div class="col-lg-7"><div class="panel"><h2>Message</h2>
    <?php if ($c['subject']): ?><p class="mb-1"><strong>Objet :</strong> <?= e($c['subject']) ?></p><?php endif ?>
    <pre class="small mb-0" style="white-space:pre-wrap"><?= e($c['body']) ?></pre></div>

  <?php if ($c['status'] !== 'draft'): ?>
  <div class="panel"><h2>Suivi des envois</h2>
    <div class="d-flex flex-wrap gap-3 mb-2"><?php foreach (['sent' => 'Envoyés', 'pending' => 'En attente', 'manual' => 'À envoyer à la main', 'failed' => 'Échecs', 'skipped' => 'Ignorés', 'cancelled' => 'Annulés'] as $k => $l): if (!$counts[$k]) continue; ?><span><strong><?= (int) $counts[$k] ?></strong> <span class="small text-muted"><?= e($l) ?></span></span><?php endforeach ?></div>
    <?php if ($counts['total']): ?><div class="progress mb-3" role="progressbar" aria-label="Avancement"><div class="progress-bar" style="width:<?= (int) round($counts['sent'] / max(1, $counts['total']) * 100) ?>%"></div></div><?php endif ?>
    <div class="table-responsive"><table class="table table-sm align-middle small mb-0"><thead><tr><th>Client</th><th>Destinataire</th><th>État</th></tr></thead><tbody>
      <?php foreach ($messages as $m): ?><tr><td><?= e($m['first_name'] . ' ' . $m['last_name']) ?></td><td><?= e($m['recipient']) ?></td>
        <td><?= e($m['status']) ?><?= $m['error'] ? '<br><span class="text-danger">' . e($m['error']) . '</span>' : '' ?></td></tr><?php endforeach ?>
    </tbody></table></div></div>
  <?php endif ?></div>

  <div class="col-lg-5">
    <?php if ($preview !== null): ?>
    <div class="panel"><h2>Avant de lancer</h2>
      <p class="mb-1"><strong><?= (int) $preview['count'] ?></strong> client(s) seront contactés.</p>
      <?php if (!$preview['count']): ?><p class="small text-warning-emphasis">Aucun client ne correspond : il faut qu'il ait accepté de recevoir des offres (fiche client) et qu'il ait <?= $c['channel'] === 'email' ? 'un email valide' : 'un numéro de téléphone' ?>.</p><?php endif ?>
      <?php if ($preview['sample'] !== null): ?><p class="small text-muted mb-1">Aperçu du premier message :</p><pre class="small bg-light p-2 rounded" style="white-space:pre-wrap"><?= e($preview['sample']) ?></pre><?php endif ?>
      <form method="post" action="/admin/marketing/campagnes/<?= $id ?>/tester" class="d-flex gap-2 mb-3"><?= csrf_field() ?>
        <input class="form-control form-control-sm" name="to" placeholder="<?= $c['channel'] === 'email' ? 'votre email' : 'votre numéro' ?>" required><button class="btn btn-sm btn-outline-primary">Tester</button></form>
      <form method="post" action="/admin/marketing/campagnes/<?= $id ?>/lancer" data-confirm="Lancer la campagne vers <?= (int) $preview['count'] ?> client(s) ?"><?= csrf_field() ?><button class="btn btn-primary" <?= ($problem || !$preview['count']) ? 'disabled' : '' ?>>Lancer la campagne</button></form>
    </div>
    <?php else: ?>
    <div class="panel"><h2>Résultats</h2>
      <p class="mb-1"><strong><?= (int) $results['bookings'] ?></strong> réservation(s) venues du lien de la campagne</p>
      <p class="mb-0"><strong><?= money($results['revenue']) ?></strong> de chiffre d'affaires estimé</p></div>
    <div class="panel"><h2>Actions</h2>
      <?php if ($c['channel'] === 'whatsapp' && $wa_mode === 'link' && $counts['manual']): ?><a class="btn btn-success mb-2 d-block" href="/admin/marketing/campagnes/<?= $id ?>/whatsapp">Ouvrir les messages WhatsApp (<?= (int) $counts['manual'] ?>)</a><?php endif ?>
      <?php if (in_array($c['status'], ['queued', 'sending'], true) && $counts['pending']): ?>
        <form method="post" action="/admin/marketing/campagnes/<?= $id ?>/lot" class="mb-2"><?= csrf_field() ?><button class="btn btn-outline-primary w-100">Envoyer un lot maintenant (50)</button></form><?php endif ?>
      <?php if (in_array($c['status'], ['queued', 'sending'], true)): ?>
        <form method="post" action="/admin/marketing/campagnes/<?= $id ?>/annuler" data-confirm="Annuler la campagne ? Les messages non envoyés ne partiront pas."><?= csrf_field() ?><button class="btn btn-outline-danger w-100">Annuler la campagne</button></form><?php endif ?>
    </div>
    <?php endif ?>
  </div>
</div>

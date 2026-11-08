<div class="d-flex align-items-center gap-2 mb-2"><h1 class="mb-0 me-auto">WhatsApp : <?= e($c['name']) ?></h1><a class="btn btn-outline-secondary btn-sm" href="/admin/marketing/campagnes/<?= (int) $c['id'] ?>">Retour à la campagne</a></div>
<p class="text-muted">Sans API, WhatsApp ne peut pas partir tout seul. Pour chaque client : ouvrez la conversation (le message est déjà écrit), envoyez-le, puis revenez marquer « Envoyé ». <?= (int) $counts['sent'] ?> envoyé(s), <?= (int) $counts['manual'] ?> restant(s).</p>
<div class="panel p-0"><div class="table-responsive"><table class="table align-middle mb-0 small">
  <thead><tr><th>Client</th><th>Numéro</th><th>Message</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr>
    <td><?= e($r['first_name'] . ' ' . $r['last_name']) ?></td><td class="text-nowrap"><?= e(phone_fmt($r['recipient'])) ?></td><td style="max-width:360px"><span class="text-muted"><?= e(mb_strimwidth($r['content'], 0, 140, '…')) ?></span></td>
    <td class="text-nowrap"><a class="btn btn-sm btn-success" href="<?= e($r['link']) ?>" target="_blank" rel="noopener noreferrer">Ouvrir WhatsApp</a>
      <form method="post" action="/admin/marketing/campagnes/<?= (int) $c['id'] ?>/whatsapp/<?= (int) $r['id'] ?>" class="d-inline"><?= csrf_field() ?>
        <button name="result" value="sent" class="btn btn-sm btn-outline-primary">Envoyé</button><button name="result" value="skipped" class="btn btn-sm btn-outline-secondary">Passer</button></form></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="4" class="text-center text-muted py-4">Tous les messages ont été traités.</td></tr><?php endif ?>
</tbody></table></div></div>

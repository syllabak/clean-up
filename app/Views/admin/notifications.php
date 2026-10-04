<div class="d-flex align-items-center gap-2 mb-3"><h1 class="mb-0 me-auto">Journal des envois</h1>
  <form method="post" action="/admin/notifications/relancer-tout" data-confirm="Relancer tous les envois en échec ?"><?= csrf_field() ?><button class="btn btn-sm btn-outline-primary">Relancer tous les échecs</button></form></div>
<p class="small text-muted">Un envoi en échec est retenté automatiquement jusqu'à <?= (int) $max ?> fois (tâche planifiée). « Relancer » force une tentative immédiate.</p>
<div class="mb-2"><?php foreach (['' => 'Tous', 'failed' => 'Échecs', 'sent' => 'Envoyés', 'pending' => 'En attente'] as $k => $l): ?><a class="btn btn-sm <?= $status === $k ? 'btn-primary' : 'btn-outline-secondary' ?>" href="?status=<?= $k ?>"><?= $l ?></a> <?php endforeach ?></div>
<div class="panel p-0"><div class="table-responsive"><table class="table align-middle mb-0 small">
  <thead><tr><th>Date</th><th>Événement</th><th>Canal</th><th>Destinataire</th><th>Réservation</th><th>État</th><th>Tentatives</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr>
    <td class="text-nowrap"><?= e(date('d/m H:i', strtotime($r['created_at'] . ' UTC'))) ?></td><td><?= e($r['event']) ?><br><span class="text-muted"><?= e($r['audience']) ?></span></td><td><?= e($r['channel']) ?></td><td><?= e($r['recipient']) ?></td>
    <td><?= $r['booking_id'] ? '<a href="/admin/reservations/' . (int) $r['booking_id'] . '">' . e($r['reference']) . '</a>' : '—' ?></td>
    <td><span class="badge text-bg-<?= $r['status'] === 'sent' ? 'success' : ($r['status'] === 'failed' ? 'danger' : 'secondary') ?>"><?= e($r['status']) ?></span><?= $r['error'] ? '<br><span class="text-danger">' . e($r['error']) . '</span>' : '' ?></td>
    <td><?= (int) $r['attempts'] ?></td>
    <td><?php if ($r['status'] !== 'sent'): ?><form method="post" action="/admin/notifications/<?= (int) $r['id'] ?>/relancer"><?= csrf_field() ?><button class="btn btn-sm btn-outline-primary">Relancer</button></form><?php endif ?></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">Aucun envoi.</td></tr><?php endif ?>
</tbody></table></div></div>
<?= \App\Core\View::partial('partials/pagination', ['page' => $page, 'pages' => $pages]) ?>

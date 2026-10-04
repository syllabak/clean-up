<h1>Journal d'audit</h1>
<div class="panel p-0"><div class="table-responsive"><table class="table table-sm align-middle mb-0 small">
  <thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Objet</th><th>Détails</th><th>IP</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr><td class="text-nowrap"><?= e(date('d/m/Y H:i', strtotime($r['created_at'] . ' UTC'))) ?></td><td><?= e($r['user_name'] ?? '—') ?></td><td><?= e($r['action']) ?></td><td><?= e($r['entity']) ?> <?= $r['entity_id'] ? '#' . (int) $r['entity_id'] : '' ?></td><td class="text-muted"><?= e(mb_substr((string) $r['data'], 0, 140)) ?></td><td><?= e($r['ip']) ?></td></tr><?php endforeach ?>
</tbody></table></div></div>
<?= \App\Core\View::partial('partials/pagination', ['page' => $page, 'pages' => $pages]) ?>

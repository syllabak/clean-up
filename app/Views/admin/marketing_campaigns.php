<div class="d-flex align-items-center gap-2 mb-3"><h1 class="mb-0 me-auto">Campagnes</h1><a class="btn btn-primary" href="/admin/marketing/campagnes/nouveau">Nouvelle campagne</a></div>
<div class="panel p-0"><div class="table-responsive"><table class="table align-middle mb-0 small">
  <thead><tr><th>Campagne</th><th>Canal</th><th>Public</th><th>État</th><th>Envoyés</th><th>Créée</th></tr></thead><tbody>
  <?php foreach ($rows as $c): [$l, $col] = \App\Services\MarketingService::STATUSES[$c['status']] ?? [$c['status'], 'light']; ?>
    <tr><td><a href="/admin/marketing/campagnes/<?= (int) $c['id'] ?>"><?= e($c['name']) ?></a></td><td><?= e(\App\Services\MarketingService::CHANNELS[$c['channel']] ?? $c['channel']) ?></td>
      <td><?= e(\App\Services\MarketingService::AUDIENCES[$c['audience']] ?? $c['audience']) ?></td><td><span class="badge text-bg-<?= e($col) ?>"><?= e($l) ?></span></td>
      <td><?= (int) $c['sent'] ?> / <?= (int) $c['total'] ?></td><td class="text-nowrap"><?= e(date('d/m/Y', strtotime($c['created_at'] . ' UTC'))) ?></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">Aucune campagne.</td></tr><?php endif ?>
</tbody></table></div></div>

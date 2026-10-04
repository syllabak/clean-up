<?php
$card = function (array $b): string {
    ob_start(); ?>
    <a class="mission" href="/agent/mission/<?= (int) $b['id'] ?>">
      <div class="d-flex justify-content-between align-items-start"><span class="tm"><?= e($b['start_time']) ?></span><?= status_badge($b['status']) ?></div>
      <div class="fw-semibold mt-1"><?= e($b['service_name']) ?></div>
      <div class="small text-muted"><?= e($b['customer_first_name'] . ' ' . $b['customer_last_name']) ?> · <?= e($b['neighborhood_name']) ?></div>
    </a><?php return ob_get_clean();
};
?>
<h1 class="h4 mb-1">Bonjour <?= e(explode(' ', $staff['name'])[0]) ?></h1>
<p class="text-muted"><?= e($staff['team_name'] ?? 'Sans équipe') ?> · <?= e(fmt_date($today)) ?></p>
<h2 class="h6 text-uppercase-none mt-3">Aujourd'hui (<?= count($todays) ?>)</h2>
<?php if (!$todays): ?><p class="text-muted">Aucune mission aujourd'hui.</p><?php endif ?>
<?php foreach ($todays as $b) echo $card($b) ?>
<h2 class="h6 mt-4">À venir</h2>
<?php if (!$upcoming): ?><p class="text-muted">Rien de prévu dans les 14 prochains jours.</p><?php endif ?>
<?php $lastDay = ''; foreach ($upcoming as $b): if ($lastDay !== $b['scheduled_date']): $lastDay = $b['scheduled_date']; ?><p class="fw-semibold mb-1 mt-3"><?= e(fmt_date($b['scheduled_date'])) ?></p><?php endif; echo $card($b); endforeach ?>
<p class="text-center mt-4"><a class="btn btn-outline-secondary btn-sm" href="/agent">Actualiser</a></p>

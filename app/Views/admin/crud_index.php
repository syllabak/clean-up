<?php
$type = $c['type'];
$cell = function (string $col, $def, array $row) use ($labels) {
    $v = $row[$col] ?? null; $t = is_array($def) ? $def[1] : 'text';
    return match ($t) {
        'money' => money((int) $v), 'bool' => $v ? '<span class="badge text-bg-success">Oui</span>' : '<span class="badge text-bg-secondary">Non</span>',
        'ref' => e($labels[$col][$v] ?? '—'), 'day' => e(DAYS_FR[(int) $v] ?? ''), 'datetime' => $v ? e(date('d/m/Y H:i', strtotime($v . ' UTC'))) : '—',
        'excerpt' => e(mb_strimwidth((string) $v, 0, 90, '…')), 'event' => e(\App\Services\NotificationService::EVENTS[$v] ?? $v),
        'weekdays' => $v === '' || $v === null ? 'Tous' : e(implode(', ', array_map(fn($d) => mb_substr(DAYS_FR[(int) $d] ?? '', 0, 3), explode(',', $v)))),
        default => e($v),
    };
};
?>
<?php if ($parent): ?><p class="mb-1"><?php if ($parent['back']): ?><a href="/admin/crud/<?= e($parent['back']) ?>">← Retour</a> · <?php endif ?>Pour : <strong><?= e($parent['label']) ?></strong></p><?php endif ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <h1 class="mb-0 me-auto"><?= e($c['title']) ?> <span class="fs-6 text-muted">(<?= $total ?>)</span></h1>
  <?php if (!empty($c['search'])): ?><form class="d-flex gap-1" method="get"><input class="form-control form-control-sm" name="q" value="<?= e(\App\Core\Request::str('q')) ?>" placeholder="Rechercher" aria-label="Rechercher"><?php foreach ($c['filters'] ?? [] as $k): if (\App\Core\Request::int($k)): ?><input type="hidden" name="<?= e($k) ?>" value="<?= \App\Core\Request::int($k) ?>"><?php endif; endforeach ?><button class="btn btn-sm btn-outline-secondary">OK</button></form><?php endif ?>
  <?php if (($c['can_create'] ?? true) !== false): ?><a class="btn btn-primary btn-sm" href="/admin/crud/<?= e($type) ?>/nouveau<?= e($filterQs) ?>">Ajouter</a><?php endif ?>
</div>
<div class="panel p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><?php foreach ($c['list'] as $col => $def): ?><th><?= e(is_array($def) ? $def[0] : $def) ?></th><?php endforeach ?><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr>
    <?php foreach ($c['list'] as $col => $def): ?><td><?= $cell($col, $def, $r) ?></td><?php endforeach ?>
    <td class="text-end text-nowrap">
      <?php foreach ($c['links'] ?? [] as [$lab, $tpl]): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(strtr($tpl, ['{id}' => (int) $r['id'], '{phone}' => rawurlencode((string) ($r['phone'] ?? ''))])) ?>"><?= e($lab) ?></a> <?php endforeach ?>
      <a class="btn btn-sm btn-outline-primary" href="/admin/crud/<?= e($type) ?>/<?= (int) $r['id'] ?>/modifier"><?= ($c['can_create'] ?? true) === false && $type === 'contact_messages' ? 'Lire' : 'Modifier' ?></a>
      <form class="d-inline" method="post" action="/admin/crud/<?= e($type) ?>/<?= (int) $r['id'] ?>/supprimer" data-confirm="Supprimer cet élément ?"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Supprimer</button></form>
    </td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="<?= count($c['list']) + 1 ?>" class="text-center text-muted py-4">Aucun élément.</td></tr><?php endif ?>
</tbody></table></div></div>
<?= \App\Core\View::partial('partials/pagination', ['page' => $page, 'pages' => $pages]) ?>

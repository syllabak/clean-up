<?php
use App\Services\SubscriptionService as S;
$tab = fn(string $k, string $l, int $n) => '<a class="btn btn-sm ' . ($status === $k ? 'btn-primary' : 'btn-outline-secondary') . '" href="/admin/abonnements' . ($k !== '' ? '?status=' . $k : '') . '">' . e($l) . ' <span class="opacity-75">' . $n . '</span></a>';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-2"><h1 class="mb-0">Abonnements <small class="text-muted fs-6">(<?= (int) $total ?>)</small></h1>
  <a class="btn btn-sm btn-outline-secondary ms-auto" href="/admin/crud/subscription_plans">Offres et remises</a> <a class="btn btn-sm btn-outline-secondary" href="/abonnements" target="_blank" rel="noopener">Voir la page publique</a></div>
<div class="d-flex flex-wrap gap-2 mb-3">
  <?= $tab('', 'Tous', array_sum($counts)) ?>
  <?php foreach (S::STATUSES as $k => [$l]): ?><?= $tab($k, $k === 'requested' ? 'Demandes' : $l, (int) ($counts[$k] ?? 0)) ?><?php endforeach ?>
  <form class="ms-auto d-flex gap-2" method="get" action="/admin/abonnements"><?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif ?><input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="Nom, entreprise, téléphone" aria-label="Rechercher"><button class="btn btn-sm btn-outline-primary">Chercher</button></form>
</div>
<?php if (!$rows): ?><div class="panel text-muted">Aucun abonnement pour le moment. Les demandes arrivent ici dès qu'un client remplit le formulaire de la page « Abonnements ».</div><?php else: ?>
<div class="panel p-0"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
  <thead><tr><th>Client</th><th>Offre</th><th>Passage</th><th>Statut</th></tr></thead><tbody>
  <?php foreach ($rows as $r): [$sl, $sc] = S::STATUSES[$r['status']] ?? [$r['status'], 'light']; ?>
  <tr>
    <td><a class="fw-bold" href="/admin/abonnements/<?= (int) $r['id'] ?>"><?= e($r['business_name'] ?: $r['first_name'] . ' ' . $r['last_name']) ?></a><?= $r['business_name'] ? '<br><span class="small">' . e($r['first_name'] . ' ' . $r['last_name']) . '</span>' : '' ?><br>
      <?= contact_buttons($r['phone'], 'Bonjour ' . $r['first_name'] . ', ici ' . setting('company_name', '') . ' au sujet de votre demande d\'abonnement.') ?></td>
    <td class="small"><?= e($r['plan_name']) ?><br><span class="text-muted"><?= e($r['hood']) ?> · −<?= (int) $r['discount_percent'] ?> %</span></td>
    <td class="small"><?= e(DAYS_FR[(int) $r['weekday']]) ?> à <?= e($r['start_time']) ?><br><span class="text-muted"><?= $r['next_visit'] ? 'Prochain : ' . e(fmt_date($r['next_visit'])) : ($r['status'] === 'requested' ? 'Début souhaité : ' . e(fmt_date($r['start_date'])) : '—') ?></span>
      <?php if ((int) $r['to_plan'] > 0): ?><br><span class="badge text-bg-warning"><?= (int) $r['to_plan'] ?> à replanifier</span><?php endif ?></td>
    <td><span class="badge text-bg-<?= e($sc) ?>"><?= e($sl) ?></span></td>
  </tr><?php endforeach ?></tbody></table></div></div>
<?= \App\Core\View::partial('partials/pagination', ['page' => $page, 'pages' => $pages]) ?>
<?php endif ?>

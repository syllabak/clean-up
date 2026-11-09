<?php
$label = fn(string $csv) => implode(', ', array_map(fn($k) => $skills[$k] ?? $k, array_filter(explode(',', $csv))));
$tab = fn(string $s, string $l, int $n) => '<a class="btn btn-sm ' . ($status === $s ? 'btn-primary' : 'btn-outline-secondary') . '" href="/admin/recrutement' . ($s !== '' ? '?status=' . $s : '') . '">' . e($l) . ' <span class="opacity-75">' . $n . '</span></a>';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-2"><h1 class="mb-0">Recrutement <small class="text-muted fs-6">(<?= (int) $total ?>)</small></h1></div>
<div class="panel">
  <p class="mb-2"><strong>Lien du formulaire à partager</strong> (WhatsApp, affiche, réseaux) :</p>
  <div class="d-flex flex-wrap gap-2 align-items-center">
    <input class="form-control" style="max-width:420px" readonly value="<?= e($link) ?>" onclick="this.select()" aria-label="Lien du formulaire de recrutement">
    <a class="btn btn-sm btn-outline-secondary" href="/recrutement" target="_blank" rel="noopener">Voir le formulaire</a>
    <a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener" href="<?= e(wa_link('', 'Rejoignez l\'équipe ' . setting('company_name') . ' : ' . $link)) ?>">Partager sur WhatsApp</a>
  </div>
</div>
<div class="d-flex flex-wrap gap-2 mb-3">
  <?= $tab('', 'Toutes', array_sum($counts)) ?>
  <?php foreach (\App\Controllers\Admin\RecruitmentAdminController::STATUSES as $k => [$l]): ?><?= $tab($k, $l, (int) ($counts[$k] ?? 0)) ?><?php endforeach ?>
  <form class="ms-auto d-flex gap-2" method="get" action="/admin/recrutement"><?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif ?><input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="Nom ou téléphone" aria-label="Rechercher"><button class="btn btn-sm btn-outline-primary">Chercher</button></form>
</div>
<?php if (!$rows): ?><div class="panel text-muted">Aucune candidature pour le moment. Partagez le lien ci-dessus pour en recevoir.</div><?php else: ?>
<div class="panel p-0"><div class="table-responsive"><table class="table table-sm align-middle mb-0 orders">
  <thead><tr><th>Candidat</th><th>Profil</th><th>Statut</th></tr></thead><tbody>
  <?php foreach ($rows as $a): [$sl, $sc] = \App\Controllers\Admin\RecruitmentAdminController::STATUSES[$a['status']] ?? [$a['status'], 'light']; ?>
  <tr>
    <td><strong><?= e($a['name']) ?></strong><br><span class="small text-muted"><?= e(date('d/m/Y H:i', strtotime($a['created_at'] . ' UTC'))) ?></span><br>
      <?= contact_buttons($a['phone'], 'Bonjour ' . $a['name'] . ', ici ' . setting('company_name', '') . ' : merci pour votre candidature, nous aimerions vous rencontrer.') ?>
      <?php if (!(int) $a['has_whatsapp']): ?><br><span class="small text-warning">Pas de WhatsApp : appeler</span><?php endif ?></td>
    <td class="small"><?= e($label((string) $a['skills'])) ?><br><span class="text-muted"><?= e($a['hood'] ?? 'Quartier non précisé') ?> · <?= e($experience[$a['experience']] ?? '—') ?> · <?= e($availability[$a['availability']] ?? '—') ?></span>
      <?php if ($a['message'] || $a['notes'] || $a['email']): ?><details class="mt-1"><summary class="text-primary">Détails et notes</summary>
        <?= $a['email'] ? '<div>' . e($a['email']) . '</div>' : '' ?><?= $a['message'] ? '<div class="mt-1">' . nl2br(e($a['message'])) . '</div>' : '' ?><?= $a['notes'] ? '<div class="mt-1 text-muted">' . nl2br(e($a['notes'])) . '</div>' : '' ?></details><?php endif ?>
      <details class="mt-1"><summary class="text-primary">Ajouter une note</summary><form method="post" action="/admin/recrutement/<?= (int) $a['id'] ?>/note" class="d-flex gap-2 mt-1"><?= csrf_field() ?><input class="form-control form-control-sm" name="note" maxlength="500" required aria-label="Note"><button class="btn btn-sm btn-outline-primary">OK</button></form></details></td>
    <td><details class="stmenu"><summary><span class="badge text-bg-<?= e($sc) ?>"><?= e($sl) ?></span> <span class="caret" aria-hidden="true">▾</span></summary><div class="stmenu-list">
      <?php foreach (\App\Controllers\Admin\RecruitmentAdminController::STATUSES as $k => [$l]): if ($k === $a['status']) continue; ?>
      <form method="post" action="/admin/recrutement/<?= (int) $a['id'] ?>/statut"><?= csrf_field() ?><input type="hidden" name="to" value="<?= e($k) ?>"><button><?= e($l) ?></button></form><?php endforeach ?></div></details></td>
  </tr>
  <?php endforeach ?></tbody></table></div></div>
<?= \App\Core\View::partial('partials/pagination', ['page' => $page, 'pages' => $pages]) ?>
<?php endif ?>

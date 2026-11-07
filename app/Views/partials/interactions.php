<?php use App\Services\CrmService; ?>
<div class="panel"><h2>Historique des échanges</h2>
  <form method="post" action="<?= e($action) ?>" class="row g-2 mb-3"><?= csrf_field() ?>
    <div class="col-4 col-md-3"><select class="form-select form-select-sm" name="type" aria-label="Type d'échange"><?php foreach (CrmService::INTERACTION_TYPES as $k => $lab): ?><option value="<?= e($k) ?>"><?= e($lab) ?></option><?php endforeach ?></select></div>
    <div class="col-8 col-md-9"><input class="form-control form-control-sm" name="summary" required maxlength="2000" placeholder="Ce qui s'est dit, ce qui est convenu…" aria-label="Résumé"></div>
    <div class="col-12"><button class="btn btn-sm btn-primary">Ajouter</button></div>
  </form>
  <?php foreach ($interactions as $i): ?>
    <div class="border-top py-2"><span class="badge text-bg-light"><?= e(CrmService::INTERACTION_TYPES[$i['type']] ?? $i['type']) ?></span>
      <span class="small text-muted"><?= e(date('d/m/Y H:i', strtotime($i['created_at'] . ' UTC'))) ?> · <?= e($i['user_name'] ?? 'Système') ?></span>
      <div><?= nl2br(e($i['summary'])) ?></div></div>
  <?php endforeach; if (!$interactions): ?><p class="text-muted small mb-0">Aucun échange enregistré.</p><?php endif ?>
</div>

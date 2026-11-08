<?php
/** Bloc photos « avant intervention ». Variables : $ref (référence, vide à la commande), $csrf, $photos (déjà envoyées), $editable, $id (identifiant du bloc), $max. */
$id = $id ?? 'photos'; $max = $max ?? 8; $photos = $photos ?? []; $editable = $editable ?? true;
$cam = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg>';
$img = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="M21 16l-5-5-8 9"/></svg>';
?>
<div class="cu-photos" id="<?= e($id) ?>" <?= !empty($ref) ? 'data-ref="' . e($ref) . '" data-csrf="' . e($csrf ?? '') . '"' : '' ?> data-max="<?= (int) $max ?>">
  <div class="cu-thumbs"><?php foreach ($photos as $p): ?><div class="cu-th"><img src="/suivi/<?= e($ref) ?>/photo/<?= (int) $p['id'] ?>" alt="Photo de la commande" loading="lazy"></div><?php endforeach ?></div>
  <?php if ($editable): ?>
  <div class="cu-actions">
    <label class="btn btn-outline-primary btn-sm"><?= $cam ?> Prendre une photo<input type="file" accept="image/*" capture="environment" hidden></label>
    <label class="btn btn-outline-secondary btn-sm"><?= $img ?> Importer des photos<input type="file" accept="image/*" multiple hidden></label>
  </div>
  <?php endif ?>
  <p class="cu-msg small text-muted mb-0" role="status"></p>
</div>

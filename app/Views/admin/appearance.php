<h1>Apparence</h1>
<p class="text-muted">Changez le thème, le logo, la couleur des boutons et le bouton du chatbot. Rien à modifier sur le serveur : le résultat est visible tout de suite sur le site.</p>
<form method="post" action="/admin/apparence" enctype="multipart/form-data" id="appearance" class="row g-4">
  <?= csrf_field() ?>
  <div class="col-lg-7">
    <div class="panel mb-3"><h2 class="h6">Thème</h2>
      <div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Thèmes prêts à l'emploi">
        <?php foreach ($presets as $id => [$label, $d, $p, $a]): ?>
          <button type="button" class="btn btn-outline-secondary btn-sm ap-preset" data-dark="<?= e($d) ?>" data-primary="<?= e($p) ?>" data-accent="<?= e($a) ?>">
            <span class="ap-sw" style="background:<?= e($d) ?>"></span><span class="ap-sw" style="background:<?= e($p) ?>"></span><span class="ap-sw" style="background:<?= e($a) ?>"></span> <?= e($label) ?></button>
        <?php endforeach ?>
      </div>
      <div class="row g-3">
        <?php foreach (['theme_dark' => ['Couleur foncée', 'Titres, bandeau du bas'], 'theme_primary' => ['Couleur des boutons principaux', 'Boutons, liens, sélections'], 'theme_accent' => ['Couleur du bouton « Réserver »', 'Bouton d\'appel à l\'action']] as $k => [$lab, $help]): ?>
          <div class="col-sm-4"><label class="form-label" for="<?= e($k) ?>"><?= e($lab) ?></label>
            <input type="color" class="form-control form-control-color w-100" id="<?= e($k) ?>" name="<?= e($k) ?>" value="<?= e($v[$k]) ?>"><div class="form-text"><?= e($help) ?></div></div>
        <?php endforeach ?>
      </div>
    </div>

    <div class="panel mb-3"><h2 class="h6">Logo</h2>
      <?php if ($logo): ?><p><img src="<?= e($logo) ?>" alt="Logo actuel" style="max-height:56px;max-width:220px"></p>
        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="remove_logo" name="remove_logo" value="1"><label class="form-check-label" for="remove_logo">Retirer le logo (revenir à la goutte)</label></div><?php endif ?>
      <label class="form-label" for="logo"><?= $logo ? 'Remplacer le logo' : 'Ajouter un logo' ?></label>
      <input class="form-control" type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp,image/gif">
      <div class="form-text">PNG, JPEG ou WebP, 5 Mo maximum. Un fond transparent (PNG) rend mieux dans l'en-tête.</div>
      <div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="brand_show_name" name="brand_show_name" value="1" <?= $show_name ? 'checked' : '' ?>><label class="form-check-label" for="brand_show_name">Afficher le nom de l'entreprise à côté du logo</label></div>
    </div>

    <div class="panel mb-3"><h2 class="h6">Bouton du chatbot</h2>
      <div class="row g-3">
        <div class="col-12"><label class="form-label" for="cb_label">Texte du bouton</label>
          <input class="form-control" id="cb_label" name="cb_label" maxlength="30" value="<?= e($cb_label) ?>" placeholder="<?= e(\App\Services\ThemeService::CB_DEFAULT_LABEL) ?>"><div class="form-text">30 caractères au maximum. Vide : « <?= e(\App\Services\ThemeService::CB_DEFAULT_LABEL) ?> ».</div></div>
        <div class="col-4"><label class="form-label" for="cb_bg">Fond</label><input type="color" class="form-control form-control-color w-100" id="cb_bg" name="cb_bg" value="<?= e($v['cb_bg']) ?>"></div>
        <div class="col-4"><label class="form-label" for="cb_color">Texte</label><input type="color" class="form-control form-control-color w-100" id="cb_color" name="cb_color" value="<?= e($v['cb_color']) ?>"></div>
        <div class="col-4"><label class="form-label" for="cb_dot">Pastille</label><input type="color" class="form-control form-control-color w-100" id="cb_dot" name="cb_dot" value="<?= e($v['cb_dot']) ?>"></div>
        <div class="col-12"><span class="form-label d-block">Position sur l'écran</span>
          <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="cb_position" id="pos-l" value="left" <?= $cb_position === 'left' ? 'checked' : '' ?>><label class="form-check-label" for="pos-l">En bas à gauche</label></div>
          <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="cb_position" id="pos-r" value="right" <?= $cb_position === 'right' ? 'checked' : '' ?>><label class="form-check-label" for="pos-r">En bas à droite (le bouton WhatsApp passe à gauche)</label></div></div>
      </div>
    </div>

    <div class="d-flex gap-2 flex-wrap"><button class="btn btn-primary">Enregistrer</button>
      <button class="btn btn-outline-danger" name="reset" value="1" data-confirm="Rétablir les couleurs, le logo et le bouton du chatbot d'origine ?">Rétablir l'apparence d'origine</button></div>
  </div>

  <div class="col-lg-5"><div class="panel ap-preview" id="ap-preview" aria-label="Aperçu">
    <h2 class="h6">Aperçu</h2>
    <p class="ap-title">Nettoyage à domicile</p>
    <p><a href="#" class="ap-link" tabindex="-1">Un lien</a></p>
    <p class="d-flex gap-2 flex-wrap"><span class="ap-btn" id="ap-b1">Continuer</span><span class="ap-btn" id="ap-b2">Réserver</span></p>
    <p class="mb-0"><span class="ap-chat" id="ap-chat"><i class="ap-dot" id="ap-dot"></i><span id="ap-chat-t"><?= e($cb_label !== '' ? $cb_label : \App\Services\ThemeService::CB_DEFAULT_LABEL) ?></span></span></p>
  </div></div>
</form>
<div class="panel mt-4"><h2 class="h6">Diagnostic des téléversements</h2>
  <p class="small text-muted">Si une image (logo, photo) ne s'enregistre pas, la cause est affichée ici.</p>
  <ul class="list-unstyled small mb-0"><?php foreach ($diag as [$lab, $ok, $msg]): ?><li class="mb-1"><span class="<?= $ok ? 'text-success' : 'text-danger' ?> fw-bold"><?= $ok ? '✓' : '✗' ?></span> <strong><?= e($lab) ?></strong> : <?= e($msg) ?></li><?php endforeach ?></ul></div>
<style>.ap-sw{display:inline-block;width:.9rem;height:.9rem;border-radius:50%;vertical-align:-2px;margin-right:2px;border:1px solid rgba(0,0,0,.15)}
.ap-preview{position:sticky;top:90px}.ap-title{font-weight:800;font-size:1.2rem;margin:0 0 .3rem}.ap-btn{display:inline-block;padding:.5rem 1rem;border-radius:10px;font-weight:650}
.ap-chat{display:inline-flex;align-items:center;gap:.5rem;padding:.65rem 1rem;border-radius:999px;font-weight:700}.ap-dot{width:.6rem;height:.6rem;border-radius:50%;display:inline-block}</style>
<script src="<?= asset('js/appearance.js') ?>" defer></script>

<section class="hero">
  <?php if (setting('hero_image')): ?><div class="hero-photo" style="background-image:url('<?= e(setting('hero_image')) ?>')"></div><?php endif ?>
  <span class="bubble b1"></span><span class="bubble b2"></span><span class="bubble b3"></span>
  <div class="container position-relative">
    <h1><?= e(setting('hero_title')) ?></h1>
    <p class="lead"><?= e(setting('hero_subtitle')) ?></p>
    <p class="mb-2 fw-semibold">Qu'est-ce qu'on nettoie pour vous ?</p>
    <div class="start-grid">
      <?php foreach ($services as $s): ?>
        <a class="start-chip" href="/reserver?service=<?= e($s['slug']) ?>"><?= e($s['name']) ?><small>dès <?= e(from_label($s, \App\Controllers\ServiceController::fromPrice($s))) ?></small></a>
      <?php endforeach ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <h2>Comment ça se passe</h2>
    <p class="text-muted mb-4">Pas de devis à attendre : le prix est calculé pendant que vous choisissez.</p>
    <ol class="steps">
      <li><strong>Vous choisissez</strong><br>Le service, la formule et les options qui vous conviennent.</li>
      <li><strong>Vous indiquez votre quartier</strong><br>Nous ne proposons que les créneaux où une équipe peut vraiment passer.</li>
      <li><strong>Nous venons chez vous</strong><br>Une équipe équipée se déplace. Vous réglez directement avec elle.</li>
    </ol>
  </div>
</section>

<?php if ($featured): ?>
<section class="section section-foam">
  <div class="container">
    <h2>Les plus demandés</h2>
    <div class="row g-3 mt-2">
      <?php foreach ($featured as $s): ?>
      <div class="col-md-6 col-lg-4">
        <a class="service-card" href="/services/<?= e($s['slug']) ?>">
          <div class="thumb" <?= $s['image'] ? 'style="background-image:url(\'' . e($s['image']) . '\')"' : '' ?>><?= $s['image'] ? '' : e(mb_substr($s['name'], 0, 1)) ?></div>
          <div class="body"><h3><?= e($s['name']) ?></h3><p class="mb-0 text-muted"><?= e($s['short_description']) ?></p>
            <span class="price-from">À partir de <?= e(from_label($s, \App\Controllers\ServiceController::fromPrice($s))) ?></span></div>
        </a>
      </div>
      <?php endforeach ?>
    </div>
    <p class="mt-4 mb-0"><a class="btn btn-primary" href="/services">Voir tous les services</a></p>
  </div>
</section>
<?php endif ?>

<?php if ($testimonials): ?>
<section class="section">
  <div class="container">
    <h2>Ils nous ont ouvert leur porte</h2>
    <div class="row g-3 mt-2">
      <?php foreach ($testimonials as $t): ?>
      <div class="col-md-4"><figure class="quote mb-0"><blockquote class="mb-2">« <?= e($t['content']) ?> »</blockquote><figcaption class="small fw-semibold"><?= e($t['name']) ?> · <?= str_repeat('★', (int) $t['rating']) ?></figcaption></figure></div>
      <?php endforeach ?>
    </div>
  </div>
</section>
<?php endif ?>

<?php if ($faq): ?>
<section class="section section-foam">
  <div class="container" style="max-width:820px">
    <h2>Questions fréquentes</h2>
    <div class="accordion mt-3" id="faqHome">
      <?php foreach ($faq as $i => $q): ?>
      <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q<?= $i ?>"><?= e($q['question']) ?></button></h3>
        <div id="q<?= $i ?>" class="accordion-collapse collapse" data-bs-parent="#faqHome"><div class="accordion-body"><?= nl2br(e($q['answer'])) ?></div></div></div>
      <?php endforeach ?>
    </div>
    <p class="mt-3 mb-0"><a href="/faq">Toutes les questions</a></p>
  </div>
</section>
<?php endif ?>

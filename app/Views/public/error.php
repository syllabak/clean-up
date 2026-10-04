<div class="container section text-center" style="max-width:560px">
  <p class="display-4 fw-bold text-primary mb-0"><?= (int) $code ?></p>
  <h1 class="h3"><?= e($title) ?></h1>
  <?php if (!empty($message)): ?><p><?= e($message) ?></p><?php endif ?>
  <p><a class="btn btn-primary" href="/">Retour à l'accueil</a></p>
</div>

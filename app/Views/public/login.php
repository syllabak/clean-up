<div class="container section" style="max-width:440px">
  <h1 class="h3 mb-3">Espace équipe</h1>
  <form method="post" action="/connexion">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control" id="email" name="email" type="email" value="<?= old('email') ?>" required autocomplete="username" autofocus></div>
    <div class="mb-3"><label class="form-label" for="password">Mot de passe</label><input class="form-control" id="password" name="password" type="password" required autocomplete="current-password"></div>
    <button class="btn btn-primary btn-lg w-100">Se connecter</button>
  </form>
</div>

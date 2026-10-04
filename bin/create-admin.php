<?php
// Usage : php bin/create-admin.php email@exemple.sn "Nom Complet"   (le mot de passe est demandé, ou variable ADMIN_PASSWORD)
require __DIR__ . '/../bootstrap.php';
use App\Core\Database as DB;

[, $email, $name] = array_pad($argv, 3, null);
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$name) { fwrite(STDERR, "Usage : php bin/create-admin.php email \"Nom\"\n"); exit(1); }
$pwd = getenv('ADMIN_PASSWORD') ?: null;
if (!$pwd) {
    fwrite(STDOUT, 'Mot de passe (12 caractères minimum) : ');
    if (PHP_OS_FAMILY !== 'Windows') shell_exec('stty -echo');
    $pwd = trim((string) fgets(STDIN));
    if (PHP_OS_FAMILY !== 'Windows') shell_exec('stty echo');
    echo PHP_EOL;
}
if (mb_strlen($pwd) < 12) { fwrite(STDERR, "Mot de passe trop court (12 caractères minimum).\n"); exit(1); }
$role = (int) DB::val("SELECT id FROM roles WHERE name = 'admin'");
if (!$role) { fwrite(STDERR, "Rôles absents : lancez d'abord php bin/migrate.php\n"); exit(1); }
$email = mb_strtolower($email);
$hash = password_hash($pwd, PASSWORD_DEFAULT);
if ($id = DB::val('SELECT id FROM users WHERE email = ?', [$email])) {
    DB::update('users', (int) $id, ['password_hash' => $hash, 'role_id' => $role, 'active' => 1, 'deleted_at' => null, 'name' => $name, 'updated_at' => now()]);
    echo "Compte mis à jour : $email\n";
} else {
    DB::insert('users', ['company_id' => 1, 'role_id' => $role, 'name' => $name, 'email' => $email, 'password_hash' => $hash, 'active' => 1, 'created_at' => now()]);
    echo "Administrateur créé : $email\n";
}

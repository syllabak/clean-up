#!/usr/bin/env bash
# Installation de Clean Up : vérifie PHP, prépare .env, crée la base, charge le catalogue de Dakar (facultatif) et crée l'administrateur.
# Peut être relancé sans danger : un .env existant n'est pas écrasé, et un administrateur existant n'est pas modifié.
#
# Usage interactif :  ./install.sh
# Sans question     :  ./install.sh --yes --url https://cleanup.sn --email vous@cleanup.sn --name "Votre Nom" --dakar --cron
# Options : --url URL        adresse publique du site (https recommandé)        --email, --name   compte administrateur
#           --password MDP   mot de passe de l'administrateur (sinon généré)    --mysql           utiliser MySQL/MariaDB au lieu de SQLite
#           --dakar          charger le catalogue de Dakar (base vide)           --no-dakar        ne pas le charger
#           --dev            installation locale (debug actif, pas de HTTPS)     --cron            installer les tâches planifiées (crontab)
#           --serve          lancer le serveur de test sur le port 8000 à la fin   --yes            ne rien demander (valeurs par défaut)
set -euo pipefail

cd "$(dirname "$0")"
APP_DIR="$(pwd)"

# ---------------------------------------------------------------- affichage
if [ -t 1 ]; then B=$'\033[1m'; G=$'\033[32m'; Y=$'\033[33m'; R=$'\033[31m'; N=$'\033[0m'; else B=''; G=''; Y=''; R=''; N=''; fi
step() { printf '\n%s▶ %s%s\n' "$B" "$1" "$N"; }
ok()   { printf '  %s✓%s %s\n' "$G" "$N" "$1"; }
warn() { printf '  %s!%s %s\n' "$Y" "$N" "$1"; }
die()  { printf '\n%s✗ %s%s\n' "$R" "$1" "$N" >&2; exit 1; }

# ---------------------------------------------------------------- options
URL=""; EMAIL=""; NAME=""; PASSWORD=""; USE_MYSQL=0; DAKAR=""; DEV=0; CRON=0; SERVE=0; YES=0
while [ $# -gt 0 ]; do
  case "$1" in
    --url) URL="${2:-}"; shift 2 ;; --email) EMAIL="${2:-}"; shift 2 ;; --name) NAME="${2:-}"; shift 2 ;; --password) PASSWORD="${2:-}"; shift 2 ;;
    --mysql) USE_MYSQL=1; shift ;; --dakar) DAKAR=1; shift ;; --no-dakar) DAKAR=0; shift ;; --dev) DEV=1; shift ;;
    --cron) CRON=1; shift ;; --serve) SERVE=1; shift ;; --yes|-y) YES=1; shift ;;
    -h|--help) sed -n 2,11p "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) die "Option inconnue : $1 (voir ./install.sh --help)" ;;
  esac
done
[ -t 0 ] || YES=1   # sans terminal (script, CI) : aucune question

ask() { # ask VARIABLE "Question" "défaut"
  local var="$1" q="$2" def="${3:-}" cur="${!1:-}" reply
  if [ -n "$cur" ] || [ "$YES" = 1 ]; then [ -n "$cur" ] || printf -v "$var" '%s' "$def"; return; fi
  read -r -p "  $q${def:+ [$def]} : " reply; printf -v "$var" '%s' "${reply:-$def}"
}
confirm() { # confirm "Question" O|N  -> 0 si oui
  local q="$1" def="${2:-O}" reply
  if [ "$YES" = 1 ]; then [ "$def" = "O" ]; return; fi
  read -r -p "  $q [$([ "$def" = O ] && echo 'O/n' || echo 'o/N')] : " reply; reply="${reply:-$def}"; [[ "$reply" =~ ^[OoYy] ]]
}

# ---------------------------------------------------------------- 1. PHP
step "1. Vérification de PHP"
command -v php >/dev/null 2>&1 || die "PHP est introuvable. Installez PHP 8.1 ou plus (ex. : sudo apt install php-cli php-sqlite3 php-mbstring php-gd php-curl php-xml)."
PHPV="$(php -r 'echo PHP_VERSION;')"
php -r 'exit(PHP_VERSION_ID >= 80100 ? 0 : 1);' || die "PHP $PHPV détecté : il faut PHP 8.1 ou plus."
ok "PHP $PHPV"
MISSING=""
NEED="mbstring gd json openssl fileinfo"; if [ "$USE_MYSQL" = 1 ]; then NEED="$NEED pdo_mysql"; else NEED="$NEED pdo_sqlite"; fi
for e in $NEED; do php -r "exit(extension_loaded('$e') ? 0 : 1);" || MISSING="$MISSING $e"; done
[ -z "$MISSING" ] || die "Extensions PHP manquantes :$MISSING. Sous Debian/Ubuntu : sudo apt install$(for e in $MISSING; do case $e in pdo_sqlite) printf ' php-sqlite3';; pdo_mysql) printf ' php-mysql';; fileinfo|json|openssl) ;; *) printf ' php-%s' "$e";; esac; done)"
ok "Extensions : $NEED"
php -r 'exit(extension_loaded("curl") ? 0 : 1);' || warn "Extension curl absente : recommandée pour les SMS et les services externes."

# ---------------------------------------------------------------- 2. .env
step "2. Configuration (.env)"
env_set() { # env_set CLÉ VALEUR : met à jour ou ajoute la ligne, sans toucher aux autres
  KEY="$1" VAL="$2" php -r '
    $k = getenv("KEY"); $v = getenv("VAL");
    if (str_contains($v, "\"") && str_contains($v, "\x27")) { fwrite(STDERR, "Valeur impossible pour $k : elle contient des guillemets simples et doubles.\n"); exit(1); }
    if (preg_match("/[\s#\"\x27]/", $v)) $v = str_contains($v, "\"") ? "\x27$v\x27" : "\"$v\"";
    $c = is_file(".env") ? file_get_contents(".env") : ""; $line = "$k=$v"; $re = "/^" . preg_quote($k, "/") . "=.*$/m";
    $c = preg_match($re, $c) ? preg_replace_callback($re, fn() => $line, $c, 1) : rtrim($c) . "\n$line\n";
    file_put_contents(".env", $c);'
}
env_get() { KEY="$1" php -r '$k=getenv("KEY"); foreach (is_file(".env") ? file(".env", FILE_IGNORE_NEW_LINES) : [] as $l) { if (str_starts_with($l, "$k=")) { $v = trim(substr($l, strlen($k) + 1)); echo trim($v, "\"\x27"); exit; } }'; }

NEW_ENV=0
if [ ! -f .env ]; then cp .env.example .env; NEW_ENV=1; ok ".env créé à partir de .env.example"; else ok ".env déjà présent : conservé (seules les options passées en ligne de commande sont appliquées)"; fi

if [ "$NEW_ENV" = 1 ] || [ -n "$URL" ]; then
  if [ "$DEV" = 1 ]; then ask URL "Adresse du site" "http://localhost:8000"; else ask URL "Adresse publique du site" "https://votre-domaine.sn"; fi
  URL="${URL%/}"
  env_set APP_URL "$URL"
  if [ "$DEV" = 1 ]; then env_set APP_ENV local; env_set APP_DEBUG true; else env_set APP_ENV production; env_set APP_DEBUG false; fi
  case "$URL" in https://*|http://localhost*|http://127.0.0.1*) ;; *) [ "$DEV" = 1 ] || warn "L'adresse n'est pas en https : en production, activez HTTPS (cookies sécurisés, HSTS)." ;; esac
fi
if [ "$NEW_ENV" = 1 ] || [ "$(env_get CRON_TOKEN)" = "changez-moi" ] || [ -z "$(env_get CRON_TOKEN)" ]; then
  env_set CRON_TOKEN "$(php -r 'echo bin2hex(random_bytes(24));')"; ok "CRON_TOKEN généré (jeton des tâches planifiées HTTP)"
fi
if [ "$USE_MYSQL" = 1 ]; then
  step "   Base MySQL / MariaDB"
  DBH=""; DBP=""; DBN=""; DBU=""; DBW=""
  ask DBH "Serveur" "localhost"; ask DBP "Port" "3306"; ask DBN "Nom de la base (créée à l'avance, utf8mb4)" "cleanup"; ask DBU "Utilisateur" "cleanup"
  if [ "$YES" = 1 ]; then DBW="${DB_PASSWORD:-}"; else read -r -s -p "  Mot de passe : " DBW; echo; fi
  env_set DB_DRIVER mysql; env_set DB_HOST "$DBH"; env_set DB_PORT "$DBP"; env_set DB_DATABASE "$DBN"; env_set DB_USERNAME "$DBU"; env_set DB_PASSWORD "$DBW"
  ok "MySQL configuré ($DBU@$DBH/$DBN)"
else
  env_set DB_DRIVER sqlite; ok "Base SQLite : $(env_get DB_SQLITE_PATH)"
fi
chmod 600 .env 2>/dev/null && ok "Droits de .env : lecture réservée au propriétaire" || warn "Impossible de restreindre les droits de .env"

# ---------------------------------------------------------------- 3. dossiers
step "3. Dossiers et droits d'écriture"
for d in storage/database storage/logs storage/cache storage/backups storage/uploads storage/uploads/missions public/uploads public/uploads/services; do mkdir -p "$d"; done
chmod -R u+rwX,g+rwX storage public/uploads 2>/dev/null || warn "Droits de storage/ non modifiés : vérifiez que PHP peut y écrire."
[ -w storage ] || die "storage/ n'est pas accessible en écriture pour cet utilisateur."
ok "storage/ et public/uploads/ prêts"

# ---------------------------------------------------------------- 4. base
step "4. Base de données"
php bin/migrate.php | sed 's/^/  /'
ok "Tables créées et à jour"

# ---------------------------------------------------------------- 5. catalogue
step "5. Catalogue de départ pour Dakar"
HAS_SERVICES="$(php -r 'require "bootstrap.php"; echo (int) App\Core\Database::val("SELECT COUNT(*) FROM services");')"
if [ "$HAS_SERVICES" -gt 0 ]; then
  ok "Des services existent déjà : catalogue non rechargé"
else
  if [ -z "$DAKAR" ]; then confirm "Charger les services, tarifs, 33 quartiers et 4 équipes de Dakar ?" O && DAKAR=1 || DAKAR=0; fi
  if [ "$DAKAR" = 1 ]; then php bin/seed-dakar.php | sed 's/^/  /'; ok "Catalogue chargé (à vérifier dans Configuration)"; else warn "Ignoré : saisissez vos services, zones et équipes dans l'administration."; fi
fi

# ---------------------------------------------------------------- 6. administrateur
step "6. Compte administrateur"
HAS_USERS="$(php -r 'require "bootstrap.php"; echo (int) App\Core\Database::val("SELECT COUNT(*) FROM users");')"
CREATED_PWD=""
if [ "$HAS_USERS" -gt 0 ] && [ -z "$EMAIL" ]; then
  ok "Un utilisateur existe déjà : aucun compte créé (pour en ajouter un : php bin/create-admin.php email \"Nom\")"
else
  ask EMAIL "Email de l'administrateur" ""
  ask NAME "Nom complet" "Administrateur"
  [ -n "$EMAIL" ] || die "Indiquez l'email de l'administrateur (--email vous@domaine.sn)."
  if [ -z "$PASSWORD" ]; then
    if [ "$YES" = 1 ]; then PASSWORD="$(php -r 'echo substr(strtr(base64_encode(random_bytes(18)), "+/=", "xyz"), 0, 16);')"; CREATED_PWD="$PASSWORD"
    else
      read -r -s -p "  Mot de passe (12 caractères minimum, vide = en générer un) : " PASSWORD; echo
      if [ -z "$PASSWORD" ]; then PASSWORD="$(php -r 'echo substr(strtr(base64_encode(random_bytes(18)), "+/=", "xyz"), 0, 16);')"; CREATED_PWD="$PASSWORD"; fi
    fi
  fi
  ADMIN_PASSWORD="$PASSWORD" php bin/create-admin.php "$EMAIL" "$NAME" | sed 's/^/  /'
fi

# ---------------------------------------------------------------- 7. tâches planifiées
step "7. Tâches planifiées (rappels, nouvelles tentatives d'envoi, abonnements, sauvegarde)"
PHPBIN="$(command -v php)"
CRON_LINES="*/5 * * * * $PHPBIN $APP_DIR/bin/queue.php >/dev/null 2>&1   # cleanup-queue
0 2 * * * $PHPBIN $APP_DIR/bin/backup.php >/dev/null 2>&1   # cleanup-backup"
if [ "$CRON" = 1 ] || { [ "$YES" = 0 ] && command -v crontab >/dev/null 2>&1 && confirm "Installer ces tâches dans votre crontab ?" N; }; then
  command -v crontab >/dev/null 2>&1 || die "crontab est introuvable sur ce serveur."
  EXISTING="$(crontab -l 2>/dev/null | grep -v '# cleanup-' || true)"   # lu en entier avant d'écrire : aucune de vos autres tâches n'est perdue
  { [ -z "$EXISTING" ] || printf '%s\n' "$EXISTING"; printf '%s\n' "$CRON_LINES"; } | crontab -
  ok "Tâches ajoutées à votre crontab (relancer le script ne crée pas de doublon)"
else
  warn "Non installées. À ajouter avec « crontab -e » :"; printf '%s\n' "$CRON_LINES" | sed 's/^/      /'
  warn "Sans accès à cron : appelez ${URL:-$(env_get APP_URL)}/cron/run avec l'en-tête X-Cron-Token: $(env_get CRON_TOKEN)"
fi

# ---------------------------------------------------------------- fin
FINAL_URL="$(env_get APP_URL)"
printf '\n%s%s Installation terminée.%s\n\n' "$B" "$G✓" "$N"
printf '  Site            : %s\n  Administration  : %s/connexion\n' "$FINAL_URL" "$FINAL_URL"
[ -z "${EMAIL:-}" ] || printf '  Identifiant     : %s\n' "$EMAIL"
if [ -n "$CREATED_PWD" ]; then printf '  Mot de passe    : %s%s%s   <- noté une seule fois, changez-le après la première connexion\n' "$B" "$CREATED_PWD" "$N"; fi
cat <<'EOF'

  À faire ensuite
    1. Serveur web : faites pointer le domaine sur le dossier public/ (storage/ et .env ne doivent jamais être exposés).
    2. HTTPS : activez-le, puis vérifiez APP_URL dans .env.
    3. Emails et SMS : renseignez MAIL_* et SMS_* dans .env (MAIL_DRIVER=smtp, SMS_DRIVER=http). Sans cela, les messages sont seulement écrits dans storage/logs/.
    4. Administration : vérifiez les tarifs (Configuration → Services), renommez les agents modèles, saisissez le téléphone et le WhatsApp de l'entreprise (Paramètres).
    5. Sauvegardes : copiez régulièrement storage/backups/ hors du serveur.
EOF
if [ "$SERVE" = 1 ]; then
  printf '\n  Serveur de test sur http://localhost:8000 (Ctrl+C pour arrêter)\n\n'
  exec php -S 0.0.0.0:8000 -t public public/index.php
fi

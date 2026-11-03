#!/usr/bin/env bash
# Installation au premier lancement du Codespace.
set -e
cd "$(dirname "$0")/.."

# Écrit CLE=valeur dans .env (remplace la ligne si elle existe, sinon l'ajoute)
ecrire_env() {
    if grep -q "^$1=" .env; then
        sed -i "s|^$1=.*|$1=$2|" .env
    else
        echo "$1=$2" >> .env
    fi
}

composer install --no-interaction
npm ci

if [ ! -f .env ]; then
    cp .env.example .env
fi

if grep -q '^APP_KEY=$' .env; then
    php artisan key:generate
fi

# Base MySQL du conteneur « mysql » ; pas de page d'erreur détaillée pendant la démo ; pas de vrais emails
ecrire_env DB_HOST mysql
ecrire_env DB_PASSWORD password
ecrire_env APP_DEBUG false
ecrire_env MAIL_MAILER log

# Clés Reverb
if grep -q '^REVERB_APP_KEY=$' .env; then
    ecrire_env REVERB_APP_ID "$((RANDOM * 100 + RANDOM % 100))"
    ecrire_env REVERB_APP_KEY "$(php -r 'echo bin2hex(random_bytes(10));')"
    ecrire_env REVERB_APP_SECRET "$(php -r 'echo bin2hex(random_bytes(10));')"
fi

# Attendre que MySQL soit prêt
for i in $(seq 1 60); do
    if php -r "new PDO('mysql:host=mysql;dbname=edt_scolaire', 'root', 'password');" 2>/dev/null; then
        break
    fi
    sleep 2
done

# Données de démonstration (École Les Jasmins, mot de passe « password1 »)
php artisan migrate --seed --force

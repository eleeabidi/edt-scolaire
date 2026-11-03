#!/usr/bin/env bash
# Démarrage, à chaque lancement du Codespace : adresses publiques, build, serveurs, ports publics.
set -e
cd "$(dirname "$0")/.."

if [ -z "$CODESPACE_NAME" ] || [ -z "$GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN" ]; then
    echo "Ce script est fait pour GitHub Codespaces."
    exit 0
fi

ecrire_env() {
    if grep -q "^$1=" .env; then
        sed -i "s|^$1=.*|$1=$2|" .env
    else
        echo "$1=$2" >> .env
    fi
}

APP_PUBLIC="https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"
REVERB_PUBLIC="${CODESPACE_NAME}-8080.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"

ecrire_env APP_URL "$APP_PUBLIC"
# Côté serveur : Laravel parle à Reverb dans le conteneur
ecrire_env REVERB_HOST localhost
ecrire_env REVERB_PORT 8080
ecrire_env REVERB_SCHEME http
# Côté navigateur : Reverb par son adresse publique en https
ecrire_env VITE_REVERB_HOST "$REVERB_PUBLIC"
ecrire_env VITE_REVERB_PORT 443
ecrire_env VITE_REVERB_SCHEME https

php artisan config:clear
# Les variables VITE_* sont lues au moment du build
npm run build

# Serveurs en arrière-plan (journaux dans /tmp)
pkill -f "artisan serve" || true
pkill -f "artisan reverb:start" || true
setsid nohup php artisan serve --host=0.0.0.0 --port=8000 > /tmp/serveur.log 2>&1 < /dev/null &
setsid nohup php artisan reverb:start --host=0.0.0.0 --port=8080 > /tmp/reverb.log 2>&1 < /dev/null &

# Ports publics (on réessaie : les ports sont parfois transférés quelques secondes plus tard)
for i in $(seq 1 12); do
    if gh codespace ports visibility 8000:public 8080:public -c "$CODESPACE_NAME" > /dev/null 2>&1; then
        echo "Ports 8000 et 8080 rendus publics."
        break
    fi
    if [ "$i" = 12 ]; then
        echo "Ports pas encore publics : onglet « Ports », clic droit sur 8000 puis 8080, « Port Visibility » > « Public »."
    fi
    sleep 5
done

echo ""
echo "=============================================================="
echo " Lien de l'application à envoyer :"
echo " $APP_PUBLIC"
echo "=============================================================="

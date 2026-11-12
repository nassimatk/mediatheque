#!/bin/sh
# Point d'entrée du conteneur PHP.
# Prépare l'application (dépendances, migrations) puis lance la commande demandée.
set -e
cd /app

# Avant l'installation de Symfony (étape de bootstrap), on lance simplement la commande.
if [ ! -f composer.json ]; then
  echo "[entrypoint] Symfony n'est pas encore installé : voir README.md (étape bootstrap)."
  exec "$@"
fi

# Dépendances
if [ ! -d vendor ]; then
  echo "[entrypoint] Installation des dépendances Composer..."
  composer install --no-interaction --prefer-dist
fi

# Dossier de cache/logs accessible en écriture (confort de dev sous Linux)
mkdir -p var && chmod -R a+rwX var

# Migrations (uniquement si le bundle de migrations est installé)
if [ "${RUN_MIGRATIONS:-1}" = "1" ] && [ -f bin/console ] \
   && php bin/console list --raw 2>/dev/null | grep -q '^doctrine:migrations:migrate'; then
  echo "[entrypoint] Application des migrations..."
  php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

exec "$@"

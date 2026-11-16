#!/bin/sh
set -e

# Migracje i bootstrap wykonuje tylko kontener API (RUN_MIGRATIONS=1),
# worker czeka, aż schemat będzie gotowy.
if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    echo "Czekam na bazę danych…"
    until php bin/console dbal:run-sql -q "SELECT 1" >/dev/null 2>&1; do sleep 2; done
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
    php bin/console app:bootstrap --no-interaction
else
    echo "Czekam na migracje…"
    until php bin/console doctrine:migrations:up-to-date -q >/dev/null 2>&1; do sleep 3; done
fi

exec "$@"

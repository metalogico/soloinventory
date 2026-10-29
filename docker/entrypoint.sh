#!/bin/sh
set -eu
if [ "${1:-}" = "apache2-foreground" ]; then
    : "${APP_KEY:?Set APP_KEY in the private environment file}"
    : "${SOLOINVENTORY_USERNAME:?Set SOLOINVENTORY_USERNAME}"
    : "${SOLOINVENTORY_PASSWORD_HASH:?Set SOLOINVENTORY_PASSWORD_HASH}"
    mkdir -p \
        storage/app/private/soloinventory/photos \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs
    if [ ! -f "${DB_DATABASE}" ]; then
        touch "${DB_DATABASE}"
    fi
    php artisan migrate --force --no-interaction
    php artisan view:cache --no-interaction
fi
exec "$@"

#!/bin/sh
set -eu

attempt=1
until php artisan migrate --force; do
    if [ "$attempt" -ge 30 ]; then
        echo "Database migration did not succeed after 30 attempts." >&2
        exit 1
    fi
    attempt=$((attempt + 1))
    sleep 2
done

php artisan db:seed --force

exec /usr/bin/supervisord -c /etc/supervisord.conf

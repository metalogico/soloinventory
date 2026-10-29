#!/bin/sh
set -eu
sh /var/www/html/docker/backup-cycle.sh
exec php artisan schedule:work --no-interaction

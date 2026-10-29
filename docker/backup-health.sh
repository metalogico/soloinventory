#!/bin/sh
set -eu
test -n "${SOLOINVENTORY_BACKUP_VOLUME_ID}"
test -f /backups/.volume-id
test "$(cat /backups/.volume-id)" = "${SOLOINVENTORY_BACKUP_VOLUME_ID}"
test "$(stat -c %d /backups)" != "$(stat -c %d /var/www/html/storage)"
php -r '
$status=json_decode(@file_get_contents("/var/www/html/storage/app/soloinventory-backup-status.json"),true);
exit(is_array($status) && $status["ok"] === true && time()-$status["completed_at"] < 50400 ? 0 : 1);
'

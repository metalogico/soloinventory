#!/bin/sh
set -eu
umask 077
staging=""
on_exit() {
    result=$?
    if [ "$result" -ne 0 ]; then
        php -r 'file_put_contents("/var/www/html/storage/app/soloinventory-backup-status.json",json_encode(["ok"=>false,"failed_at"=>time()]));'
        printf 'SoloInventory backup failed.\n' >&2
    fi
    if [ -n "$staging" ]; then
        rm -rf "$staging"
    fi
}
trap on_exit EXIT
test -n "${SOLOINVENTORY_BACKUP_VOLUME_ID}"
test -f /backups/.volume-id
test "$(cat /backups/.volume-id)" = "${SOLOINVENTORY_BACKUP_VOLUME_ID}"
test "$(stat -c %d /backups)" != "$(stat -c %d /var/www/html/storage)"
test -r /source/.env
test -r /source/ACCESS.txt
test -r /source/release.json
test -r /source/source.tar.gz
test -r /tls/caddy/pki/authorities/local/root.key
touch /var/www/html/storage/app/private/soloinventory/.lock
chown www-data:www-data /var/www/html/storage/app/private/soloinventory/.lock
chmod 600 /var/www/html/storage/app/private/soloinventory/.lock
staging="$(mktemp -d /backups/.soloinventory-stage.XXXXXX)"
snapshot="$(php artisan soloinventory:backup --to="$staging" --no-interaction --no-ansi)"
case "$snapshot" in "$staging"/soloinventory-*) ;; *) exit 1 ;; esac
test -f "$snapshot/database.sqlite"
mkdir -m 700 "$snapshot/config" "$snapshot/config/docker" "$snapshot/tls-authority"
cp /source/.env /source/ACCESS.txt /source/release.json /source/source.tar.gz "$snapshot/config/"
cp /source/compose.yaml /source/compose.https.yaml /source/compose.backup.yaml /source/Dockerfile "$snapshot/config/"
cp /source/docker/Caddyfile "$snapshot/config/docker/"
cp -a /tls/caddy/pki/authorities/local/. "$snapshot/tls-authority/"
php -r '
$root=$argv[1]; $manifest=["created_at"=>gmdate(DATE_ATOM),"files"=>[]];
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
foreach($iterator as $file) {
    if($file->isFile() && $file->getPathname()!==$root."/manifest.json") {
        $manifest["files"][substr($file->getPathname(),strlen($root)+1)]=hash_file("sha256",$file->getPathname());
    }
}
ksort($manifest["files"]);
file_put_contents($root."/manifest.json",json_encode($manifest,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
' "$snapshot"
final="/backups/$(basename "$snapshot")"
mv "$snapshot" "$final"
php -r '
$state=["ok"=>true,"completed_at"=>time(),"snapshot"=>basename($argv[1])];
$path="/var/www/html/storage/app/soloinventory-backup-status.json";
file_put_contents($path.".tmp",json_encode($state,JSON_THROW_ON_ERROR));
rename($path.".tmp",$path);
' "$final"
printf '%s\n' "$final"

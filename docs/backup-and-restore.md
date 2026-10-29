# Backup and restore

## Application snapshots

`soloinventory:backup --to=/backups --no-interaction` takes the same filesystem lock as inventory writes, uses SQLite `VACUUM INTO`, copies photos and writes a checksum manifest. A snapshot is only renamed from `.partial` to its final name after success.

Each final directory contains:

```text
soloinventory-YYYYMMDD-HHMMSS-xxxxxx/
  database.sqlite
  photos/
  manifest.json
```

The manifest covers the database and every copied photo. Verify the SHA-256 values before restoring. These snapshots do not include `.env`, the source code, the Docker image or the optional HTTPS authority. Preserve those separately. No retention or automatic cleanup is enabled by the base stack.

For a manual recovery from a snapshot, stop the app, keep a copy of the current data, and use a helper container for the same source revision. Copy `database.sqlite` to `storage/app/soloinventory.sqlite` and the snapshot's `photos/` to `storage/app/private/soloinventory/photos/`. Keep ownership writable by `www-data` (UID/GID 33 in the image). Start the recovered data in an isolated Compose project first and check sign-in, item counts and photos. A snapshot may contain private information and must be stored privately.

## Complete cold backup

A cold archive of the data volume is a straightforward recovery point before an update. Stop all writers first. The following commands use the base stack and create an archive on the host, with a private file mode:

```sh
mkdir -p backups
chmod 700 backups
docker compose stop app
docker compose run --rm --no-deps -T app tar -C /var/www/html/storage -czf - . > backups/storage-before-update.tar.gz
chmod 600 backups/storage-before-update.tar.gz
docker compose up -d --wait
```

Check that the archive command succeeds before proceeding. A fixed name is used for readability; use a distinct filename for each retained backup. Copy the archive and a private copy of `.env` to another disk. Retain the source revision and an image export, for example `docker image save sh-soloinventory:local -o backups/app-image.tar`. Match that tag to your configured `SOLOINVENTORY_IMAGE`.

If the scheduled backup service is running, stop it too before taking a cold archive and restart it afterward. For HTTPS, use `-f compose.yaml -f compose.https.yaml` consistently and retain `tls` and `tls_config` as well. You can export each stopped TLS volume with a helper container following the [Docker volume backup procedure](https://docs.docker.com/engine/storage/volumes/#back-up-restore-or-migrate-data-volumes). The CA private key is sensitive; keep the export private.

## Test a cold restore in a separate project

Use the backed-up source revision and matching image. Keep your working installation intact. Copy the backed-up environment file to `.env.restore` and edit these values:

```dotenv
SOLOINVENTORY_ENV_FILE=.env.restore
SOLOINVENTORY_HTTP_PORT=18091
SOLOINVENTORY_BIND_IP=127.0.0.1
APP_URL=http://127.0.0.1:18091
SESSION_SECURE_COOKIE=false
```

Keep its original application key and account credentials. Protect `.env.restore` with mode 600. The new project name below creates independent data and backup volumes. Use an unused port.

```sh
docker compose --env-file .env.restore -p soloinventory-restore run --rm --no-deps -T app tar -C /var/www/html/storage -xzf - < backups/storage-before-update.tar.gz
docker compose --env-file .env.restore -p soloinventory-restore up -d --wait
```

Check the recovered database:

```sh
docker compose --env-file .env.restore -p soloinventory-restore exec app php -r '$db = new PDO("sqlite:" . getenv("DB_DATABASE")); echo $db->query("PRAGMA integrity_check")->fetchColumn(), PHP_EOL;'
```

It must print `ok`. Sign in at `http://127.0.0.1:18091` and check several records and photos. Stop the restore test with the same `--env-file` and `-p` arguments. Keep the recovered volumes until you have accepted the result. Restoring into the main installation is a separate maintenance step; stop every writer and retain the previous volumes first.

## Optional scheduled full-server integration

`compose.backup.yaml` preserves an existing operator-managed integration. It is **not required** for the base installation and needs provisioning beyond `.env.example`:

- Use it together with `compose.https.yaml`; it waits for Caddy to be healthy.
- Mount an existing directory on a disk different from the application data disk using `SOLOINVENTORY_BACKUP_DIR`. Compose refuses to create the host directory automatically.
- Place a `.volume-id` marker in that directory and set `SOLOINVENTORY_BACKUP_VOLUME_ID` to exactly the same value. Device and marker checks deliberately fail closed.
- Provision private `.env` and `ACCESS.txt` files, an accurate `release.json`, and a matching `source.tar.gz` in the source directory. These files are intentionally excluded from Git.
- Preserve Caddy's internal CA. The full backup includes its private key along with application data, configuration and source, so protect the destination accordingly.

The service runs as root, has no network, uses 192 MiB, and backs up on startup and at 08:20 and 20:20 Europe/Rome. Health checks require a successful snapshot less than 14 hours old. It does not delete old backups. Application status is written to `storage/app/soloinventory-backup-status.json`.

After provisioning all prerequisites, use all three Compose files on every command. Retain this integration's safety checks when adapting it to your own server.

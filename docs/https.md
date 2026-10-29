# HTTPS on a private network

The optional `compose.https.yaml` adds Caddy with an internal certificate authority. It publishes HTTPS and an HTTP redirect, and removes the app's direct HTTP port. Caddy uses 128 MiB and its own persistent `tls` and `tls_config` volumes. Compose's [`!reset` merge tag](https://docs.docker.com/reference/compose-file/merge/#reset-value) clears the backend port mapping.

1. Complete the README setup, including the application key and account.
2. Make `soloinventory.home.arpa` resolve to your server's LAN address using your local DNS. You can instead set `SOLOINVENTORY_HOST` to a server IP address.
3. Edit `.env` for the address your devices use:

```dotenv
SOLOINVENTORY_BIND_IP=0.0.0.0
SOLOINVENTORY_HOST=soloinventory.home.arpa
SOLOINVENTORY_HTTP_PORT=8091
SOLOINVENTORY_HTTPS_PORT=8444
APP_URL=https://soloinventory.home.arpa:8444
SESSION_SECURE_COOKIE=true
```

4. Start the merged configuration:

```sh
docker compose -f compose.yaml -f compose.https.yaml config --quiet
docker compose -f compose.yaml -f compose.https.yaml up -d --build --wait
```

5. Retrieve the public root certificate at `http://soloinventory.home.arpa:8091/certificato.crt`. Confirm its fingerprint against the certificate on your server before trusting it. Alternatively, copy it directly from your own container:

```sh
docker compose -f compose.yaml -f compose.https.yaml cp https:/data/caddy/pki/authorities/local/root.crt ./soloinventory-root-ca.crt
```

6. Install that CA certificate as a trusted certificate on each client. Open `https://soloinventory.home.arpa:8444`, confirm that the browser trusts the connection, and install the PWA from the Android browser menu.

The application is online only even when installed. Keep both TLS volumes when recreating the stack: replacing the CA requires clients to trust the replacement. Include them in server recovery plans; never distribute the CA private key.

The override trusts forwarded headers from its internal Caddy proxy. Keep the backend port unpublished. For another reverse proxy, configure `SOLOINVENTORY_TRUSTED_PROXIES` for that proxy and use an explicit Compose override that removes the backend host port. Do not expose an HTTP login on an untrusted network.

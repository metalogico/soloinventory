# Public repository preparation — 29 October 2026

## Scope and decisions

- Preserve application behavior, API routes, database schema and interface.
- Keep the existing Laravel/PHP, Vue 3, Element Plus, Tailwind CSS and SQLite stack, with locked dependencies and pinned Docker base images.
- Use an English public README, matching the interface and existing project landing.
- Reuse the original Sites inventory screenshot and retain photo credits.
- Build the application image locally from source; do not assume a published registry image.
- Keep local assistant rules, generated release metadata, runtime data, environment files, credentials and archives outside the Git publication set.
- Initialize the local repository on `main` and configure the confirmed origin `https://github.com/metalogico/soloinventory.git`. Do not commit, push, publish or deploy.
- Preserve the existing scheduled backup integration, documenting its additional server prerequisites rather than enabling it for a new install.

## Code review

Expanded the Vue templates, JavaScript control flow and CSS declarations. Extracted location suggestion filtering and HTTP error-message selection from dense expressions. Clarified asynchronous search request tracking, controller queries, photo storage paths, account fingerprinting and the steps of backup creation. Existing tests remain in place. The only dependency update is the separately authorized security patch described below.

## Verification

- Baseline and final application suite: **55 passing tests, 241 assertions**.
- Pint applied to the reviewed PHP sources; 49 PHP files and all Docker shell scripts pass syntax checks. Composer metadata validates strictly.
- Vite production build passes. Its existing JavaScript chunk-size warning remains; dependencies and loading behavior were preserved.
- Vue templates produce identical compiled structures after formatting. The extracted location-suggestion callback was exercised in the browser, including selecting a suggestion and saving the item. No browser warnings or errors were observed.
- Base, HTTPS and scheduled-backup Compose configurations validate. The scheduled service retains network and port isolation. The HTTPS merge removes the backend host port and sets secure cookies.
- Docker images build for `linux/arm64` and `linux/amd64`; both start successfully (amd64 under emulation). After the authorized MIME update, both images were rebuilt and startup checks repeated. A fresh Docker installation starts empty, with a generated key and password hash; health, CSRF login, item creation, quantity, Unicode search and authenticated private photos pass with synthetic data.
- Data and photo checks pass after container recreation. A live backup verifies both file checksums; a cold archive restores into a separate Compose project, with SQLite integrity reporting `ok` and an identical photo hash.
- A separate HTTPS stack passes certificate validation against its own CA, a secure session cookie check, CA download and HTTP 308 redirect checks. No client trust settings were changed.
- Publication candidates contain no matches for the checked secret patterns or actual private environment secrets. The built image excludes real environment files, credentials, local assistant metadata and runtime databases.
- The README screenshot is byte-identical to the original landing asset. Photo credits are retained.
- Both private environment files and the frontend lockfile retain their original checksums. The Composer lockfile changes only `symfony/mime`.

The installed application was not rebuilt or recreated; verification uses independent project names, image tags, ports and volumes. No remote server, Site access policy or deployment was changed.

## Approved security update

The final Composer audit reported CVE-2026-45070 in `symfony/mime` 8.1.7. Michele explicitly authorized updating only that package to 8.1.10. A package-by-package comparison confirms that no other locked package changed; `composer.json` and frontend dependencies are unchanged. After the update, the complete Composer and npm audits report **zero known vulnerabilities**, and all 55 tests / 241 assertions pass again. The advisory concerns MIME header parameter names; see the [upstream Symfony advisory](https://github.com/symfony/symfony/security/advisories/GHSA-vqc8-7275-q272).

## License

MIT confirmed by Michele Brandolin on 29 October 2026, with Michele Brandolin as copyright holder. The code license does not replace the licenses of photographs visible in the screenshot.

The repository has no initial commit. All distributable files remain available for review before the first commit. Private files and data remain in their original locations and are ignored. The operator must choose a tested source revision for each future update: original migrations currently evolve in place, so a rebuild does not upgrade an already-applied schema.

## Cleanup and handoff

All three temporary Compose projects, their synthetic data/TLS/backup volumes, the disposable amd64 containers and temporary image tags were removed after successful checks. The existing application container remains running and healthy with its original creation time and image tag. No commit, push, release or deployment was performed. No publication decisions remain open.

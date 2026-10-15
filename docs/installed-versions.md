# Installed versions (T0.1)

Recorded by task T0.1 on 13 October 2026 from `composer.lock` and `package-lock.json`. Compare with `docs/stack-baseline.md`.

## Starter kit

The line Composer printed for the starter kit (card T0.1 step 2):

```text
Installing laravel/react-starter-kit (dev-main 717b8f55aefd82d25d4119eaebdc8e3a72b8d7e5)
```

## Tools (card T0.1 step 1)

| Tool | Version |
| --- | --- |
| PHP | 8.3.6 (so `composer.json` has `"platform": {"php": "8.3.6"}`) |
| Composer | 2.8.12 |
| Node.js | 22.22.0 |
| npm | 10.9.4 |
| MariaDB | 11.8 (cloud session, the version of record); test database `zb_mill_test`, dev database `zb_mill_dev`, one user with a password in `.env` |
| PHP (Hostinger host) | 8.5 (LiteSpeed) |

## Packages

| Package | Version |
| --- | --- |
| `laravel/framework` | 13.35.0 |
| `inertiajs/inertia-laravel` | 3.5.1 |
| `laravel/fortify` | 1.41.0 |
| `laravel/wayfinder` | 0.1.21 |
| `pestphp/pest` | 4.7.8 |
| `pestphp/pest-plugin-laravel` | 4.1.0 |
| `larastan/larastan` | 3.13.0 |
| `phpstan/phpstan` | 2.3.1 |
| `phpunit/phpunit` | 12.5.33 |
| `laravel/pao` | 1.1.5 |
| `react` | 19.3.0 |
| `@inertiajs/react` | 3.9.1 |
| `vite-plus` | 0.3.0 |
| `laravel-vite-plugin` | 3.2.0 |
| `typescript` | 5.9.3 |
| `@fontsource/ibm-plex-sans` | 5.3.0 |

## Differences from `docs/stack-baseline.md`

| # | Baseline says | Found | What was done |
| --- | --- | --- | --- |
| 1 | Cloud "Trusted" network allows `api.github.com` and `codeload.github.com`, so Composer downloads work | The cloud GitHub proxy refuses Composer's GitHub zip downloads (HTTP 403) for every repository not attached to the session | Proposed spec change G-30 (`docs/proposals/G-30-cloud-composer.patch`). T0.1 used its cloud blocks, approved by the human as "option B" |
| 2 | Kit dev packages: pint, pail, sail, mockery, collision, faker, larastan, phpunit | Also `laravel/pao ^1.0.6` (test output for AI agents) | Kept (human decision). Recorded in G-30 |
| 3 | Root files not listed | Kit also has `.npmrc` (`ignore-scripts=true`) and `pnpm-workspace.yaml` | Kept as the kit has them. Recorded in G-30 |
| 4 | Not covered | Laravel 13.35.0 added the HTTP verb `QUERY`; `Route::redirect()` registers it, and Wayfinder 0.1.21 (latest, also `main`) has no `query` in its TypeScript `Method` type, so `npm run types:check` failed | `routes/settings.php`: the one `Route::redirect` now uses `Route::get` with the same `RedirectController` (GET and HEAD only). Question in the pull request |
| 5 | Pest 4.7.8 needs PHPUnit ^12.5.33 | Adding Pest moved PHPUnit from 12.5.39 to 12.5.33 | None needed: still within the kit's `^12.5.23` |
| 6 | Not covered | `npm audit` (all packages): 3 critical advisories in development tools only (`tinypool` inside `oxfmt` inside `vite-plus` 0.3.0). Shipped code: 0. The project check (`npm audit --omit=dev --audit-level=high`) passes | Reported in the pull request. Fixing needs a newer `vite-plus`, which is a package change outside T0.1 |

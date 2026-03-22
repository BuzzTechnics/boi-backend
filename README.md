# boi-backend

**Composer package** shared by BOI Laravel apps (Glow, portals, …).

**Full integration guide:** see **[`doc/README.md`](doc/README.md)** (package vs app, database connection, shared models, Nova & policies).

## boi-api HTTP proxy (plug & play)

Forwards browser calls to your app’s **`/api/boi-api/{path}`** to **`BOI_API_URL`** with server-only **`BOI_API_KEY`** and headers **`X-Boi-User`** / **`X-Boi-App`**.

1. `composer require buzztech/boi-backend` (already auto-discovers `BoiBackendServiceProvider`).
2. Set **`.env`**: `BOI_API_URL`, `BOI_API_KEY`, `BOI_APP` (slug, e.g. `glow`), optional `BOI_USER_HEADER` / `BOI_APP_HEADER`.
3. In **`routes/web.php`** (inside your `auth` + `verified` group):

```php
use Boi\Backend\BoiBackend;

BoiBackend::proxyRoute();
```

4. **Loan applications:** for paths `api/loan-applications/{id}/…`, the proxy checks **`App\Models\Application`** exists and **`user_id`** matches the logged-in user (same convention for all BOI apps).

Publish full defaults: `php artisan vendor:publish --tag=boi-backend-proxy`

## Other features

- **Contracts**: `StatementDataProviderInterface`, `StatementUpdaterInterface` (implemented by the deployed **boi-api** service or tests).
- **PaystackBanks** (`Boi\Backend\Support\PaystackBanks`): sync bank list + short names into your `Bank` model (used by seeders).
- **`Boi\Backend\Models\Bank`**: `banks` on connection `boi_api` (`BOI_DB_*`). **boi-api** can keep `App\Models\Bank` via `config/banks.php`.

EDOC / bank-statement API live in **boi-api**.

## Installation

```bash
composer require buzztech/boi-backend
```

Optional publish tags: `boi-backend-config` (`banks.php`), `boi-backend-boi-api` (`boi_api.php`), `boi-backend-proxy` (`boi_proxy.php`).

## Laravel Nova, factories, seeders

See **[`doc/`](doc/README.md)** — Nova resources and `Gate::policy` are **per app**; factories/seeders live in the package.

## Development

```bash
composer install
composer test
```

## Releasing (`buzztech/boi-backend`)

1. Merge changes to `main` and ensure `composer test` passes.
2. Tag a semver release (Composer / Packagist use the tag, not a `version` field in `composer.json`):

   ```bash
   git tag v0.2.0
   git push origin v0.2.0
   ```

3. **Packagist** (optional): submit [`https://github.com/BuzzTechnics/boi-backend`](https://github.com/BuzzTechnics/boi-backend) once; future tags are picked up automatically.
4. **Consumers** (e.g. `boi-api`): require `"buzztech/boi-backend": "^0.2@dev"` while `main` tracks the next minor (`@dev` allows the `0.2.x-dev` alias; with `prefer-stable`, Composer prefers **the latest `v0.2.x` tag**). For stable-only installs after tags exist, use `"^0.2"`. Add a VCS repository only if the package is not on Packagist:

   ```json
   "repositories": [
       { "type": "vcs", "url": "https://github.com/BuzzTechnics/boi-backend.git" }
   ]
   ```

   If `../boi-backend` exists as a sibling checkout, a **path** repository (as in `boi-api`) keeps local development on a symlinked copy.

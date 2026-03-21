# boi-backend

**Composer package** shared by BOI Laravel apps (Glow, portals, …).

**Full integration guide:** see **[`doc/README.md`](doc/README.md)** (package vs app, database connection, shared models, Nova & policies).

## boi-api HTTP proxy (plug & play)

Forwards browser calls to your app’s **`/api/boi-api/{path}`** to **`BOI_API_URL`** with server-only **`BOI_API_KEY`** and headers **`X-Boi-User`** / **`X-Boi-App`**.

1. `composer require boi/boi-backend` (already auto-discovers `BoiBackendServiceProvider`).
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
composer require boi/boi-backend
```

Optional publish tags: `boi-backend-config` (`banks.php`), `boi-backend-boi-api` (`boi_api.php`), `boi-backend-proxy` (`boi_proxy.php`).

## Laravel Nova, factories, seeders

See **[`doc/`](doc/README.md)** — Nova resources and `Gate::policy` are **per app**; factories/seeders live in the package.

## Development

```bash
composer install
composer test
```

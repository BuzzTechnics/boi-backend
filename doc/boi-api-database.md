# BOI API database connection

Shared tables such as **`banks`** and **`bank_statements`** usually live in the **same database as the deployed boi-api service** (or a replica your app can read/write as designed).

## Laravel connection

Consuming apps should define a dedicated connection (conventionally named **`boi_api`**) in **`config/database.php`**.

Glow-style pattern:

- **`url`** → `env('BOI_DB_URL', …)` (full DSN), or discrete `BOI_DB_HOST`, `BOI_DB_DATABASE`, etc.
- Driver/host must match boi-api (often **PostgreSQL** in production).

The exact keys are app-specific; the important part is: **one named connection** that reaches the DB where those tables exist.

## Which connection do package models use?

Models use the **`UsesBoiApiDatabase`** trait. It resolves:

```text
config('boi_api.eloquent_connection')
```

Default (from merged package config **`config/boi_api.php`**):

- `env('BOI_API_ELOQUENT_CONNECTION', 'boi_api')`

Behavior:

- If the value is a **non-empty string** and exists in **`config('database.connections')`**, that connection is used.
- If unset, empty, or invalid, models fall back to the **application default** connection (useful for **tests** when everything is SQLite in-memory).

## Environment variables (typical)

| Variable | Role |
|----------|------|
| `BOI_DB_URL` | DSN for boi-api DB (preferred when set). |
| `BOI_DB_*` | Host, port, database, user, password, … if not using URL. |
| `BOI_API_ELOQUENT_CONNECTION` | Laravel connection **name** for package models (default `boi_api`). Set to empty in **phpunit** if models should use the default test DB. |

## Config file in the app

The package **merges** `vendor/buzztech/boi-backend/config/boi_api.php` on boot. You **do not** need a duplicate `config/boi_api.php` in the app unless you want to override keys; use **`.env`** for normal cases.

Publish override (optional):

```bash
php artisan vendor:publish --tag=boi-backend-boi-api
```

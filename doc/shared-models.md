# Shared Eloquent models, factories, and seeders

## Models

| Model | Table (default) | Notes |
|-------|-----------------|--------|
| `Boi\Backend\Models\Bank` | `banks` | Soft deletes; `name`, `short_name`, `code`, `edoc_bank_id`. |
| `Boi\Backend\Models\BankStatement` | `bank_statements` | `application_id`, `app` (product slug), account + EDOC fields. |

Both use **`UsesBoiApiDatabase`** (see [BOI API database](boi-api-database.md)).

### `BankStatement` appended URL attributes

For UIs (e.g. Laravel Nova), the model appends:

- **`bank_statement_view_url`** — uses the raw value if already `http(s)://`, otherwise **`Storage::disk(config('filesystems.cloud', 's3'))->url($path)`** (no signed/temporary URLs).
- **`csv_view_url`** — same rule for the `csv_url` column.

Consuming apps render links from these attributes; keep S3/env config in the app.

### Relationships to app models

The package models **do not** define `belongsTo(Application::class)` (app class differs per product). In your app, define the inverse only where needed:

```php
// e.g. App\Models\Application
public function bankStatements()
{
    return $this->hasMany(\Boi\Backend\Models\BankStatement::class);
}
```

## Factories

Registered via **`newFactory()`** on each model:

- `Bank::factory()`
- `BankStatement::factory()`

`BankStatementFactory` uses `config('boi_proxy.app', 'glow')` for the `app` column by default; override attributes in tests as needed.

## Seeders

| Seeder | Behavior |
|--------|----------|
| `Boi\Backend\Database\Seeders\BankSeeder` | Creates **10** banks via factory **only if** the table is currently empty. |
| `Boi\Backend\Database\Seeders\BankStatementSeeder` | No-op unless **`BANK_STATEMENT_SEED_APPLICATION_ID`** is set in `.env`; then creates sample rows for that `application_id`. |

Call from your app’s **`Database\Seeders\DatabaseSeeder`** when appropriate:

```php
$this->call([
    \Boi\Backend\Database\Seeders\BankSeeder::class,
]);
```

## Migrations

The **canonical** migrations for these tables live with **boi-api** (or your shared DB owner). Each consuming app should either:

- Use the **real** boi-api database in dev/staging, or  
- Add a **local** migration that matches that schema on the connection used when `BOI_API_ELOQUENT_CONNECTION` is empty (e.g. SQLite tests).

Mismatch between schema and model **`$fillable`** / casts causes runtime errors, not package bugs.

## Optional reference policies

`Boi\Backend\Policies\BankPolicy` and `BankStatementPolicy` are **examples**. **Register in the app** if you want to use them as-is, or copy logic into `App\Policies\*` (Glow does the latter).

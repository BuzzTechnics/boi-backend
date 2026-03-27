# Package vs application responsibilities

`boi-backend` is shared code. Each product (Glow, portal, …) is still its own Laravel app with its own UX, auth, and admin.

## In the package (`buzztech/boi-backend`)

| Area | Purpose |
|------|---------|
| **Config** | `boi_proxy`, `boi_api`, `banks` (merged/publishable). |
| **HTTP proxy** | `BoiBackend::proxyRoute()` → forwards to `BOI_API_URL` with `BOI_API_KEY`, user/app headers. |
| **Eloquent models** | `Boi\Backend\Models\Bank`, `BankStatement` (+ `UsesBoiApiDatabase`). |
| **Factories** | `Boi\Backend\Database\Factories\BankFactory`, `BankStatementFactory` (via `newFactory()` on models). |
| **Seeders** | `BankSeeder`, `BankStatementSeeder`, `BanksSeeder` (optional; call from app `DatabaseSeeder`). |
| **Contracts** | e.g. statement provider/updater interfaces for boi-api alignment. |
| **Support** | `PaystackBanks`, etc. |
| **Global helpers** | `src/helpers.php`: files/JSON/string utils, `rounded_currency` (Brick), Postgres `apply_search_filter`, `get_lga_ids_by_internal_region` (→ `Lga::idsForInternalRegion`), SharePoint payload helpers, `boi_inertia_shared_props`. |
| **Enterprise integrations** | `BoiThirdPartyClient` (BVN/NIN/CAC + auth token cache), `RubikonClient` (customer / registration lookup). Config: merged `boi_integrations` (`BOI_THIRDPARTY_API_BASE`, `BOI_CAC_VERIFY_BASE`, `RUBIKON_API_BASE`, credentials). Publish: `php artisan vendor:publish --tag=boi-backend-integrations`. |
| **`Lga`** | `idsForInternalRegion()` for internal-region scoping. |
| **Reference policies** | `Boi\Backend\Policies\*` exist as **optional copies** — the package **does not** register them with `Gate` (see [Nova & authorization](nova-and-authorization.md)). |

## In each consuming application

| Area | Why per-app |
|------|-------------|
| **Laravel Nova resources** | Nova is tied to your **menus**, **permissions**, **field layout**, and **relations** to app models (`Application`, `LoanApplication`, …). Shipping Nova resources inside the package caused **authorization/resource resolution issues** (e.g. detail **404**) in real apps. |
| **`Gate::policy` registration** | Policies must be registered in the app’s **`AppServiceProvider`** (or `AuthServiceProvider`) so they align with your **User** model and **permission** packages (Spatie, custom roles, etc.). |
| **`config/database.php`** | Define the **`boi_api`** (or custom name) connection using **`BOI_DB_URL`** / `BOI_DB_*` (see [BOI API database](boi-api-database.md)). |
| **Migrations** | Tables for `bank_statements` (and sometimes `banks`) on the connection your app uses in dev/test; production often uses the **same schema as boi-api**. |
| **Env** | `BOI_API_URL`, `BOI_API_KEY`, `BOI_APP`, `BOI_DB_*`, optional `BOI_API_ELOQUENT_CONNECTION`. For direct third-party/Rubikon calls from the app, also set `BOI_USERNAME` / `BOI_PASSWORD` (and optional `BOI_PROD_*`, `RUBIKON_*`) as documented in `config/boi_integrations.php`. |

## Practical split

- **Reuse** domain logic, models, factories, seeders, and proxy from the package.
- **Implement** Nova, admin menus, and policy wiring **once per app**, pointing Nova `$model` at `Boi\Backend\Models\*` where needed.

# boi-backend documentation

Guides for teams integrating **`buzztech/boi-backend`** into Laravel apps (Glow, portals, internal tools).

| Doc | What it covers |
|-----|----------------|
| [**Package vs app**](package-vs-app.md) | What lives in the package vs what each app must own (Nova, policies, menus). |
| [**BOI API database**](boi-api-database.md) | `boi_api` connection, `BOI_DB_*`, `BOI_API_ELOQUENT_CONNECTION`, shared tables. |
| [**Shared models**](shared-models.md) | `Bank`, `BankStatement`, factories, seeders, migrations. |
| [**Nova & authorization**](nova-and-authorization.md) | Why Nova resources stay in the app; `Gate::policy`; avoiding Nova 404s. |
| [**Deployment**](deployment.md) | Order: DB / boi-api / package consumption / Glow; path vs published package. |

The **boi-api** HTTP service (EDOC, bank-statement routes, etc.) is documented in the **`boi-api`** repo. This package is the **Composer library** consumed by front-office apps.

For a quick proxy setup summary, see the root [**README.md**](../README.md).

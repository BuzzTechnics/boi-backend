# boi-backend package docs

> **Start here:** **[`../doc/README.md`](../doc/README.md)** — consuming-app guide (package vs app, `boi_api` DB, shared models, Nova & policies).

Integration HTTP API (EDOC, bank statements, files) is implemented in the **`boi-api`** Laravel app.

This package provides:

- **`BoiBackend::proxyRoute()`** — HTTP proxy to boi-api; enforces **`App\Models\Application`** ownership on `api/loan-applications/{id}/…`
- **Contracts** under `Boi\Backend\Contracts`
- **`PaystackBanks`** sync: `Boi\Backend\Support\PaystackBanks`

See the [boi-api README](../../boi-api/README.md) for deployment and environment variables.

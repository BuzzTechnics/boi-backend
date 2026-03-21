# Laravel Nova and authorization

## Nova resources: keep them in each app

**Do not** ship Laravel Nova resource classes inside **`boi-backend`**.

Reasons:

1. **Nova depends on the host app** — guards, users, permission packages, menu structure, and labels differ per product.
2. **`HasMany` / `BelongsTo` fields** reference **app Nova resources** (`App\Nova\Application`, …). Package resources under `Boi\Backend\Nova\*` sit outside that graph and can confuse Nova’s resource resolution and authorization.
3. **Observed issue:** In production, **Application** (or related) Nova **detail** pages could return **404** while index worked. Moving **`Bank`** / **`BankStatement`** Nova resources back to **`App\Nova\`** and keeping **`extends App\Nova\Resource`** aligned the resource stack and removed the problem.

### Recommended pattern

In the app:

- **`App\Nova\Bank`** with `public static $model = \Boi\Backend\Models\Bank::class;`
- **`App\Nova\BankStatement`** with `public static $model = \Boi\Backend\Models\BankStatement::class;`
- Register in **`NovaServiceProvider`** (`MenuItem::resource(...)`, etc.).
- On **`App\Nova\Application`** (or equivalent), use `HasMany::make(..., BankStatement::class)` with your **`App\Nova\BankStatement`** class.

## Policies: register in the app

The package **does not** call `Gate::policy()` in `BoiBackendServiceProvider` (by design).

Each app should register policies for package models it exposes in Nova or API:

```php
// App\Providers\AppServiceProvider::boot()
use App\Policies\BankPolicy;
use App\Policies\BankStatementPolicy;
use Boi\Backend\Models\Bank;
use Boi\Backend\Models\BankStatement;
use Illuminate\Support\Facades\Gate;

Gate::policy(Bank::class, BankPolicy::class);
Gate::policy(BankStatement::class, BankStatementPolicy::class);
```

Use **`App\Policies\*`** so you can tighten rules later without editing the package.

## Application / domain policies

Policies for **`App\Models\Application`** (or your loan model) are **100% app-owned**. Ensure methods like **`view`** and **`update`** accept the **model** as the second argument when Nova authorizes against an instance:

```php
public function view(User $user, Application $application): bool
```

Wrong or missing type hints can interact badly with Laravel’s `Gate` + Nova.

## Nova 404 vs 403

- **404** on a Nova detail page usually means **`ModelNotFoundException`** (wrong id, wrong resource URI, or query cannot find the row) — **not** “policy missing.”
- **403** is the typical response for **failed authorization**.

If detail 404 persists, check **`storage/logs/laravel.log`**, database connectivity for **related** `HasMany` models (e.g. `bank_statements` on `boi_api`), and that the URL is **`/nova/resources/{correct-uri-key}/{id}`**.

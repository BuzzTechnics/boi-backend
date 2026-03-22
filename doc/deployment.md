# Deployment order (boi-api, boi-backend, Glow)

This is the **recommended sequence** for dev/staging/production. Adjust names (branches, tags, hosts) to your infrastructure.

## 1. Shared database (schema owner: usually **boi-api**)

Tables such as **`banks`** and **`bank_statements`** live on the **boi-api database** (or whatever DB Glow’s `boi_api` connection points at).

- Run **migrations on that database** from the app that owns the schema — typically **`boi-api`**:

  ```bash
  php artisan migrate
  ```

- Glow’s **default** app DB (users, applications, …) is separate; only the **`boi_api`** connection must reach this shared DB.

**Order:** schema available **before** Glow needs to read banks / bank statements or before boi-api serves statement APIs.

---

## 2. **`boi-backend` package — do you “publish” first?**

### Local / monorepo dev (what you have now)

- Glow uses a **path repository** in `composer.json`:

  ```json
  { "type": "path", "url": "../boi-backend" }
  ```

- **No Packagist publish.** Clone `glow` and `boi-backend` as siblings (or same repo), then:

  ```bash
  cd glow && composer install
  ```

### CI or a server that only has Glow’s repo

Path `../boi-backend` **won’t exist**. Pick one:

| Approach | When to use |
|----------|-------------|
| **Private Git + VCS repo** in Glow’s `composer.json` | Most teams: tag `boi-backend`, `composer require buzztech/boi-backend:^x.y` from Git URL. |
| **Monorepo deploy** | Build image / checkout that includes both `glow/` and `boi-backend/` and keep path repo. |
| **Private Packagist / Satis** | Same as Git tags but served through Composer repo metadata. |

**Practical rule:** publish/version **`boi-backend` first** only when Glow **cannot** use a path repo. Otherwise, ship package changes **with** the Glow deploy (same commit or submodule).

---

## 3. Deploy **boi-api**

- Configure **`.env`** (DB, S3, EDOC, keys, etc. — see **boi-api** README).
- Run migrations (step 1), **config cache**, **route cache**, queues/scheduler if used.
- Expose a stable **HTTPS base URL** and a **server-to-server API key** that Glow will use.

Glow needs at least:

- `BOI_API_URL` — that base URL  
- `BOI_API_KEY` — must match what boi-api expects  

---

## 4. Deploy **Glow** (dev for testing)

1. **Install PHP deps** (with `boi-backend` resolvable — path or VCS as above):

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

2. **`.env`** (examples — match your real keys):

   - `APP_URL`, `APP_KEY`
   - `BOI_API_URL`, `BOI_API_KEY`, `BOI_APP` (slug, e.g. `glow`)
   - `BOI_DB_URL` or `BOI_DB_*` — **same logical DB** as boi-api for shared tables (or read replica if you use one)
   - `BOI_API_ELOQUENT_CONNECTION=boi_api` when not using the default connection (omit or empty in phpunit if you use SQLite for everything)

3. **App database** (Glow’s own):

   ```bash
   php artisan migrate
   ```

4. **Laravel**: `php artisan config:cache`, `route:cache`, `view:cache` (production).

5. **Nova / Horizon / Octane** as you already run them (Nova assets, queue workers, etc.).

6. **Smoke test:** open Nova, hit an application; confirm proxy calls work (browser network → your app → boi-api).

---

## Summary checklist

| Step | What |
|------|------|
| ① | Migrations applied on **boi-api DB** (banks, bank_statements, …). |
| ② | **`boi-backend`** available to Composer (path **or** tagged Git/Packagist). |
| ③ | **boi-api** deployed, URL + key known. |
| ④ | **Glow** deployed with `BOI_*` env + `composer install` + migrate **Glow** DB + caches. |

**Dev testing on one machine:** run boi-api + Glow (and DB) locally; keep path repo; point Glow `.env` at local boi-api URL (e.g. `http://localhost:8080`) and matching key — **no package publish required**.

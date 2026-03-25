# File uploads & view (package)

`buzztech/boi-backend` provides **`Boi\Backend\Http\Controllers\FileController`** and **`Boi\Backend\Services\FileService`**.

- Storage disk: optional **`BOI_FILES_DISK`** / **`config('boi_files.disk')`**. If unset, the package uses **`s3`** when `AWS_BUCKET` is set, or **`s3`** outside `APP_ENV=local` (so tests keep using `Storage::fake('s3')`), else **`public`** in local when the bucket is empty (dev without AWS). Configure **`filesystems.disks`** accordingly.
- **`POST /api/files/upload`** — multipart `file`, optional `folder`, optional `context` (e.g. `bank_statement` for larger limit). Returns JSON `path`, `url` (presigned when supported).
- **`GET /api/files/view?path=…`** — `exists` check then redirect to presigned/public URL.

Config is merged from **`config/boi_files.php`** (publish tag `boi-backend-files`). Env keys support `BOI_FILES_*` with fallbacks to legacy `FILES_*` where applicable.

Route registration (defaults in `config/boi_backend.php`; override in the host app’s `AppServiceProvider` if needed):

- **`register_routes`** — `/api/boi-api/{path}` proxy (default `true` for shells like Glow). **boi-api** sets `false` (it is the upstream).
- **`register_file_routes`** — `/api/files/*` (default `true`). **boi-api** sets `false` and registers `FileController` in `routes/api.php` with `auth.proxy`.

EDOC CSV keys such as **`edoc/statements/…`** still use the same **`s3`** disk on whichever app stores them.

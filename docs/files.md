# Application file uploads & view

Files use the **`s3`** disk (`AWS_BUCKET`, region, credentials in `config/filesystems.php`).

- **`POST /api/files/upload`** — store on **`s3`**; JSON includes `url` (5‑minute presigned URL when `temporaryUrl` exists, else `url()`).
- **`GET /api/files/view?path=…`** — `Storage::disk('s3')->exists($path)` then redirect to the same style of URL.

EDOC CSV keys such as **`edoc/statements/…`** use the **same** `s3` disk; see `doc/edoc-s3.md`.

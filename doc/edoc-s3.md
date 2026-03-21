# EDOC / S3 (bank statement CSV keys)

`bank_statements.bank_statement` often stores a **key** like `edoc/statements/{uuid}.csv` on the same **`s3`** disk as other uploads (`AWS_BUCKET` on `config/filesystems.php` → `s3`).

## Why a bare S3 URL can 403

Anonymous `GET` to `https://bucket.s3.region.amazonaws.com/key` fails if the bucket blocks public reads. The app issues **presigned** URLs with `Storage::disk('s3')->temporaryUrl($path, …)` (5‑minute TTL) or `/api/files/view?path=…` → redirect.

## Consuming apps (Glow, Nova)

Use one **`s3`** disk whose bucket/region/credentials match **boi-api** (where `TransferEdocFiles` writes). No separate EDOC disk is required in code.

## Environment

| Variable | Purpose |
|----------|---------|
| `AWS_*` on **`s3`** | Same bucket boi-api uses for `documents/` and `edoc/statements/`. |

Legacy `BOI_EDOC_FILESYSTEM_DISK` / `BOI_EDOC_S3_USE_SIGNED_URLS` / `BOI_EDOC_SIGNED_URL_TTL` remain in `config/boi_edoc.php` for published configs but are **not** read by upload/view code (always disk **`s3`**, 5‑minute presign in controllers/models).

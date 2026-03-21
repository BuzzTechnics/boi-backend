# EDOC integration

EDOC integration provides bank list, consent lifecycle, and transaction/CSV retrieval. File storage is S3.

## Endpoints

### GET /api/edoc/banks

Returns the list of banks supported by EDOC.

**Response (200):**
```json
{
  "success": true,
  "data": [
    { "bankId": 1, "name": "Access Bank", "bankCode": "044", "enabled": true, "bankInstructions": [] }
  ]
}
```

### POST /api/edoc/consent/initialize

Starts a consent flow.

**Body:**
- `email` (required), `firstName` (required), `lastName` (required)
- `statementDuration` (optional, default `"12"`)
- `redirectionUrl`, `referenceId`, `state`, `fundType`, `industrialSector` (optional)

**Response (200):**
```json
{
  "success": true,
  "data": { "data": { "consentId": "..." } }
}
```

### POST /api/edoc/consent/attach-account

Attaches a bank account to the consent.

**Body:**
- `consentId` (required), `bankId` (required), `accountNumber` (required, 10 digits)
- `monthType` (optional, default `"Period"`), `uploadType` (optional, default `"Digital"`)

**Response (200):**
```json
{ "success": true, "data": { ... } }
```

### POST /api/edoc/consent/transactions

Retrieves transactions and CSV URL; optionally updates a bank statement record if `statement_updater` is bound.

**Body:**
- `consentId` (required)
- `verificationCode` (optional, OTP)
- `bankStatementId` (optional) — if provided and updater is bound, the statement is updated with `consent_id`, `csv_url`, `bank_statement`, `statement_generated`, `edoc_status`

**Response (200):**
```json
{
  "success": true,
  "data": {
    "csvUrl": "https://...",
    "statement": { ... }
  }
}
```

On failure, when `bankStatementId` is provided and updater is bound, the statement’s `edoc_status` is set to `failed`.

### POST /api/edoc/manual-upload

Queues a job to process an already-uploaded bank statement file (e.g. PDF on S3).

**Body:**
- `filePath` (required) — S3 path to the file
- `bankStatementId` (required)
- `industrialSector` (optional)

**Response (200):**
```json
{
  "success": true,
  "message": "Bank statement queued for EDOC processing",
  "data": { ... }
}
```

Requires `statement_updater` and `statement_data_provider` to be bound; the job reads statement data via the provider and updates status via the updater. Files are read from and stored on the **s3** disk.

## Jobs

- **TransferEdocFiles** — Downloads CSV from EDOC and stores it on S3 at `{statements_path_prefix}/{consentId}.csv`.
- **UploadBankStatementToEdoc** — For manual upload: initializes consent, attaches account (uploadType `manual`), downloads file from S3, uploads to EDOC, then updates statement via `StatementUpdaterInterface`.

## Service

`Boi\Backend\Services\EdocService` provides static methods that call the EDOC API: `getBanks`, `initializeConsent`, `attachAccount`, `getTransactions`, `getCsvUrl`, `manualUploadBankStatement`, `decryptText`.

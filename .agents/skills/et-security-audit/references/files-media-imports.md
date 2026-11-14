# Files, Media, Imports, Exports, Logs

Use this reference for media libraries, generated documents, file uploads,
base64 imports, import/export jobs, integration payloads, logs, and external
URLs.

## Business Documents

- Sensitive/generated business documents use private storage plus signed,
  proxied, or temporary access.
- Permanent `getUrl()` is only for public assets.

## Base64 And File Imports

- Validate size before decode.
- Validate MIME/type, dimensions, and frame count before expensive conversion.
- Heavy conversions need bounded queue, time, and memory behavior.

## Media External IDs

- Media update/delete by external IDs must be scoped by owning model, model id,
  and collection.
- Do not use global `custom_properties` lookups for destructive media changes.
- Replacement/import flows clean up temporary or orphaned files after failed
  validation, failed conversion, or failed persistence.

## Logs

Prefer sanitized metadata. Exclude secrets, tokens, OTPs and password hashes.
Classify business/personal fields according to the target data policy and actual
log access/retention; do not assume every business value has identical sensitivity.

## External URLs

Classify URL use: redirects, downloads, server fetches and display have different
threats. Validate schemes and use destination restrictions where required by the
contract. Server fetches also need SSRF controls for resolved addresses and
redirects; do not treat a universal client-facing host allowlist as sufficient.

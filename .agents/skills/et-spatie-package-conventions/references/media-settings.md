# Media Library And Settings

Use this reference for `spatie/laravel-medialibrary` and
`spatie/laravel-settings` changes.

## Media Library

- Keep file behavior on the model through `HasMedia` and `InteractsWithMedia`.
- Reuse the target’s collection identifiers; an enum is optional.
- Validate base64/import images before decode/conversion: size, MIME/type,
  dimensions, and frame count.
- Generated business documents, invoices, acts, and non-public files use private
  storage with signed/proxied/temporary access.
- Add conversions only when the product actually needs them.
- In tests, fake `config('media-library.disk_name')` or the explicit private
  collection disk instead of touching real disks.
- Keep media replacement/update flows in the established write orchestration.
- Replacement/import flows clean up temporary or orphaned files after failed
  validation, failed conversion, or failed persistence.

## Laravel Settings

- Model settings as typed settings classes in the configured discovery paths.
- Inject settings through DI in tasks and services.
- Add settings migrations for defaults and schema evolution under
  the configured settings migration path.
- Do not assume `settings:discover` sees module-local settings: verify the
  current `config/settings.php` paths or make an explicit config change before
  relying on discovery/cache commands.
- After settings class/default changes, update discovery/cache with the relevant
  settings commands, then verify runtime behavior. Cache commands alone are not
  semantic proof.
- Under long-lived workers, do not keep mutable settings in static properties or
  singleton state. Verify behavior after settings cache refresh in a fresh
  request or worker context.
- Keep write paths for settings explicit and limited to admin or setup flows.
- Preserve current value types and units, especially money stored in minor
  units.

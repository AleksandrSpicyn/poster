# Laravel Data

Use this reference for `spatie/laravel-data` DTOs.

## Placement

- Put DTOs in the project's module DTO directory, admin DTO directory, or shared
  DTO directory.
- Base DTOs on the DTO parent used by the target project.
- Admin-only DTOs stay in the admin layer.
- Do not assume `data:cache-structures` covers custom module/admin/shared DTO
  paths: verify `config/data.php` `structure_caching.directories` before
  relying on the structure cache.

## Validation

- Prefer validation through Spatie or target-project attributes.
- Use `rules()` for wildcards, conditional/contextual rules, scoped exists, and
  cases attributes do not express cleanly.
- Keep route model/current actor/tenant/context constraints in DTO plus custom
  Rule when the constraint is validation-shaped.

## Controller And Response Boundaries

- Use controller DTO injection only where supported and adopted; verify the
  installed package’s validation/resolution behavior.
- Pass validated data through the existing application flow.
- Laravel Data supports output serialization; use DTOs or Resources according to
  the target response contract, including wrapping, casts and transformations.

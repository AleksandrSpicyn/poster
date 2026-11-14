# Laravel Permission

Use this reference for `spatie/laravel-permission`, admin roles, endpoint
permissions, and RBAC docs.

## Project Boundaries

- Keep permission integration at the target’s established authorization boundary.
- If custom `Role` or `Permission` models are used, verify their configuration
  in `config/permission.php` and compatibility with the installed package.
- Reuse existing role/permission identifiers or enums; do not require new enums.
- Assign roles/permissions to the actual authenticatable model with compatible
  guards; public and administrative actors may share or separate models.
- Preserve the target’s enum case and permission-string conventions.

## Routes And Cache

- Apply permissions through the target’s middleware, policies or gates; group
  routes where that matches a shared access boundary.
- Do not repeat the administrative authentication guard in route files when the
  admin route group already applies it.
- Update affected identifiers, seeders, behavior tests and documented errors.
  Verify cache invalidation for the installed version: package APIs may reset
  automatically, while direct writes/custom models can require explicit
  `PermissionRegistrar::forgetCachedPermissions()` or `permission:cache-reset`.
- Permission test setup grants roles and permissions through factories, states,
  or project helpers. Avoid raw queries or direct relation calls unless that API
  is the behavior under test.

## Failure Checks

- If a permission test unexpectedly passes, verify the user is not using the
  super-admin bypass role.
- If a route returns `401` instead of `403`, inspect guard authentication and
  middleware/exception ordering before endpoint permission logic.
- If new permissions do not apply, verify Spatie permission cache reset.

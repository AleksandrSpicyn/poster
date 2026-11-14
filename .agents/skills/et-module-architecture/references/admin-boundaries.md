# Admin Boundaries

These are concrete conventions for the recommended layered profile. They can
be adopted with either standard directories or module-local directories; they
are not claims that Laravel itself requires these layers. Preserve an explicitly
chosen alternative in an existing repository.

| Responsibility | Standard layout | Modular layout |
|---|---|---|
| Controller | `app/Http/Controllers` | `app/Modules/{Module}/Controllers` |
| Resource | `app/Http/Resources` | `app/Modules/{Module}/Resources` |
| Action/Task/Repository | `app/Actions`, `app/Tasks`, `app/Repositories` | Equivalent module-local directories |
| Admin projection | `app/Http/Controllers/Admin`, `app/Http/Resources/Admin` | `app/Admin/{Module}` or an explicitly chosen module-local admin subtree |
| Tests | `tests/Feature`, `tests/Unit` | Module/admin `Tests/Feature`, `Tests/Unit` |

Use this reference when changing code under the administrative subtree, admin routes, admin
auth, permissions, admin repositories, or admin tests.

## Placement

- Admin-only code lives in the selected administrative subtree.
- Models, migrations, factories, seeders, and reusable domain logic stay in
  the domain owner (module or standard domain directories) even when admin features use them.
- If code is shared by public API and admin, keep it in the domain owner (module or standard domain directories)
  or move it to the chosen shared infrastructure directory only when it is a reusable project primitive.
- Admin `Support` and `Services` are local admin workflow/presentation helpers;
  shared reusable logic belongs in the domain owner or shared infrastructure.
- Admin module names mirror domain module names. Do not create a new
  differently named admin module for the same domain just to group screens.

## Routes And Auth

- Admin API routes live in the selected administrative route file.
- Do not create new admin routes in a second legacy admin route file.
- Admin route names start with `admin.` and route prefixes are relative to the
  shared `/admin` prefix.
- Apply administrative authentication and access checks at an explicit route
  boundary. When inherited from a shared group/loader, route files add only the
  missing endpoint-specific checks. Do not assume such a loader/permission exists.
- Single-route endpoint-specific permission middleware is valid, and `group(...)` is
  preferred when one permission expresses a shared boundary for several routes.
- Only explicit login/bootstrap routes may opt out of base admin middleware.
  They need throttling, non-enumerating errors for missing/inactive admins, and
  tests that no token/session is issued on failure.
- Numeric admin route model parameters use `whereNumber(...)`; nested child
  resources use scoped bindings.
- Route model binding, scoped bindings, and DTO `exists` rules are not
  authorization. Admin Actions, Tasks, or Repositories must enforce the current
  actor/tenant/context scope before returning or mutating a model.

## Permissions

- Role and permission code belongs to the authorization owner: a dedicated
  module in the modular profile, or the selected policies/auth directories
  in the standard profile.
- Do not place project role/permission enums, package role/permission models,
  permission seeders, or permission middleware in the chosen shared infrastructure directory.
- This reference defines authorization boundaries; package role/permission
  mechanics follow package-specific guidance.
- Group routes with endpoint-specific permissions by the application's
  permission middleware abstraction.

## Admin Resources And Repositories

- In the adopted layered profile, admin Controllers call Actions, Actions call Tasks, and Tasks call
  Repositories. Admin Actions do not inject or call Repositories directly.
- Use an administrative Resource when the exposed contract differs; do not
  import a public projection and add privileged fields to it for convenience.
- When admin query behavior extends a domain Repository, inheritance is a
  supported convention. Reuse or compose domain behavior when an inherited
  class would expose the wrong contract; do not duplicate persistence blindly.
- Admin Tasks inject concrete admin/domain Repository classes directly; admin
  providers do not wire local repositories.
- User-controlled filtering/search/sorting/eager-loading uses the adopted
  Process/query mechanism, with input allowlists and preserved scope.
  Simple deterministic lookup ordering/limits may stay inline.
- Admin Resources may compose other admin/shared project Resources, but must
  not add admin-only fields to public API Resources.
- If a Resource emits a relation, the Action/Repository/Process must eager load
  it and the Resource must guard it with `whenLoaded()` or `relationLoaded()`.

## Admin Tests

- Define actor/helper defaults explicitly. If the application has base admin
  access, distinguish it from endpoint permissions; empty input must not
  accidentally create a bypass actor.
- A super-admin factory/helper should not be the default success actor unless
  the test covers bypass behavior.
- Admin route/controller feature tests exercise the HTTP route; do not call the
  Controller, Action, Task, or Repository directly to bypass the layer flow.
- Build setup with domain factories and states. Do not use raw query or relation
  hacks only to compensate for missing factory states.
- Routes with endpoint-specific permission middleware need a missing-permission
  `assertForbidden()` case.
- Routes without endpoint-specific permissions cover their actual base access
  boundary; do not invent an extra permission or assume a particular base role.
- Use one feature test file per admin route/controller and cover success,
  auth, endpoint-specific permission, binding `404`, validation, filters,
  sorting, pagination, partial update, and immutable fields when they apply.

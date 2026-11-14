# Resources And Responses

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

Use this reference when changing Resources, response shapes, nested payloads,
model accessors used by APIs, or eager loading for API/admin output.

## Resource Ownership

- New API Resources extend the application's base API Resource when one exists.
- Admin Resources live in the administrative Resource directory shown above; public API Resources
  live in the public Resource directory shown above.
- Administrative projections must not expose public or privileged fields
  accidentally. Share a Resource only for an intentionally identical contract.
- Public API Resources must not grow admin-only presentation fields.
- The recommended profile uses Resources for stable HTTP projections. Data
  packages can serialize output too; use that alternative only as an explicit
  serializer contract, not an accidental DTO dump.
- Shared project Resources from the chosen shared Resource directory are allowed only when the
  shape is truly shared and not an admin-specific projection.

## Controller Payloads

- Controllers must not build stable nested/resource-shaped contracts as raw
  arrays.
- Small scalar one-off payloads and empty command-style success responses may
  stay as arrays when they are not a nested public contract.
- Avoid accidental Resource-or-empty-array success shapes. If variants are
  intentional public behavior, document and test every reachable shape.
- If an endpoint uses a standard success helper, API documentation examples
  must include the actual success envelope used by that controller.

## Nested Resources

Create a dedicated nested Resource for meaningful nested objects, relation
payloads, collections/lists, media groups, geospatial structures, and reused response
shapes. Do not create Resources for every tiny inline scalar group.

Prefer `Resource::collection()` for ordinary lists. Create custom `*Collection`
classes only for custom `meta`, `links`, or envelope logic.
- For media groups, use explicit media Resources/conversions instead of exposing
  raw package media arrays from the controller. Media collection, conversion,
  and storage mechanics follow package-specific guidance.

## Eager Loading

- If a Resource emits a relation, the Action/Repository/Process owns eager
  loading.
- Eloquent relation fields in Resources use `whenLoaded()` or
  `relationLoaded()`, unless the Resource receives DTO data that is already
  prepared.
- Resource `toArray()` maps prepared fields and composes Resources. It should
  not perform hidden domain service/cache/database work.

## Models

- Models may have domain accessors.
- API-only presentation helpers on models are a smell; API shape belongs in
  Resources.
- Do not add request-scoped presentation state to models or static properties;
  Octane reuses the application across requests.

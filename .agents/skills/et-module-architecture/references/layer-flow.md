# Layer Flow

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

Use this reference when changing routes, controllers, DTOs, Actions, Tasks,
repositories, providers, processors, commands, or integrations.

## Routes

- Recommended new-code convention: explicit routes and single-action `__invoke`
  controllers, with access and binding visible at the route boundary.
- Resource controllers remain an explicit alternative. Do not let automatic
  route registration hide per-endpoint access/binding requirements.
- Route names use `snake_case`; collection names are plural, parameters are
  singular.
- Numeric route model parameters use `whereNumber(...)`.
- Nested child resources use scoped bindings.
- Route examples in apidoc must match real constraints: numbers for
  `whereNumber(...)`, parent-scoped IDs for nested routes, and enum backed
  values for polymorphic parameters.

## Controllers And Context

- Controllers stay thin: validated Form Request/DTO when body/query input exists, explicit
  route models/context attributes, one Action, Resource/JsonResponse return.
- Controllers do not call Tasks, Repositories, Services, or concurrent runners
  directly.
- Endpoint without body/query input does not need an empty DTO.
- Pass route models and contextual attributes through signatures instead of
  hiding lookup in Tasks or repositories.
- Route model binding and DTO `exists` rules are not authorization. Ownership
  in the current actor/tenant/context scope must be enforced in the Action,
  Task, or Repository according to that scenario's responsibility.

## Actions And Tasks

- Actions coordinate one business scenario and use `run()` as the public
  entrypoint. Additional helper methods are allowed, but they must not become
  alternate entrypoints such as `execute()`.
- Actions call Tasks, not Repositories. If query or persistence behavior is
  needed, add or reuse a Task and let that Task call the Repository.
- Regular Action-to-Action calls are forbidden. A local `*SubAction` is allowed
  only when it is part of one scenario and not a public reusable use case.
- Tasks perform one atomic operation and use `run()` as the public entrypoint.
  Additional helper methods are allowed, but they must not become alternate
  entrypoints such as `execute()`.
- Tasks may call concrete Repositories directly for query/persistence
  operations.
- Task-to-Task calls are legacy debt for old code, not patterns for new code.
- Reuse domain Tasks from admin Actions only when the Task is genuinely domain
  behavior. Do not clone a domain Task into the administrative subtree just to satisfy layers.

## Repositories And Processes

- Repositories query or persist through the existing base Repository if one
  exists. Otherwise a concrete typed class can use Eloquent directly.
- Tasks inject concrete Repository classes directly; do not add local repository
  wiring in providers.
- Use `Process` objects for user-controlled filtering, search, sorting,
  eager-loading, and parent scoping.
- Do not let pagination methods grow long `when(...)` chains when a Process can
  express the condition.
- File/media writes, export payloads, and generated documents stay in Tasks,
  not repositories.
- Interfaces are for real boundaries, multiple implementations, or container
  contracts. Do not add an interface for a single local class by default.

## Providers And Commands

- Providers register the bindings/listeners they own. If a centralized route
  loader exists, do not duplicate its work. Otherwise a module provider can use
  supported route/migration loading APIs, with middleware and prefixes explicit.
- Long-lived bindings must not capture request, config, container, actor,
  tenant, or context state in singleton constructors. Use scoped bindings or
  resolver closures for request-time state.
- Do not append request-specific data to static properties; Octane-style
  runtimes reuse application memory across requests.
- Console commands may use DTOs and Actions, but keep console flow separate from
  HTTP controller flow.
- Register events/subscribers in the module provider layer that owns the domain
  event; admin providers should not become cross-module wiring buckets.
- Run side effects, queue dispatch, and cache invalidation after commit when
  they depend on persisted data.

## Integrations

- Third-party integrations live under `app/Integrations` in the standard profile
  or `app/Modules/{Module}/Integrations` in the modular profile.
- Keep integrations behind contracts and keep raw client details out of
  controllers and Resources.

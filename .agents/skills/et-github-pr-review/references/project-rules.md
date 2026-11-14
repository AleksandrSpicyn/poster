# Detailed Review Rules

Use this inventory for standard and modular Laravel reviews. The layered
architecture and naming/style rules below are the recommended starter convention,
not claims that Laravel enforces them. Apply an adopted rule concretely; preserve
existing explicit alternatives. Both layouts retain the same review concerns.

## Architecture

- Keep the documented flow:
  `Route -> Controller -> DTO when needed -> Action -> Task -> Repository -> Resource -> JsonResponse`.
- Controllers stay thin: validated DTO for body/query input, one Action,
  Resource or JsonResponse.
- Controllers do not call Tasks, Repositories, or Services directly.
- Actions use `run()` as the public entrypoint and call Tasks, not
  Repositories. If query or persistence behavior is needed, add or reuse a Task.
- Actions do not call Actions except explicit local `*SubAction` patterns.
- Tasks use `run()` as the public entrypoint, may call Repositories, and do not
  call Tasks or Actions.
- Tasks inject concrete Repository classes directly; providers should not wire
  local repositories.
- Repositories extend the project's base Repository class when that convention
  exists.
- Processors implement the project's search/filter process contract when that
  convention exists.
- Query filtering/search/sorting/pagination belongs in Process classes when the
  condition is reusable or the repository already uses processors.
- File and media writes belong in Tasks, not Repositories.
- Third-party integrations live under `Integrations` behind contracts.
- Use custom module exceptions, not bare `\Exception`.

## Admin

- Admin routes live in the selected admin route file (standard `routes` or module-local `Routes`).
- Admin controllers are `__invoke` controllers.
- Admin repositories use the selected admin namespace. Inheritance from a
  domain Repository is a concrete supported convention; reuse/composition is
  valid when it better preserves the intended query contract.
- Admin resources live in the selected administrative Resource namespace.
- Admin code uses its administrative projection; share a public Resource only
  when the exposed contract is intentionally identical and safe.
- Role and permission code belongs in a dedicated authorization module, not in
  the shared infrastructure layer.
- Admin routes with endpoint-specific permissions use the project's standard
  permission middleware, policy, or gate.
- Admin endpoint API documentation includes a `403` insufficient-permissions response when
  the route has endpoint-specific permission middleware, or when the endpoint's
  documented runtime contract otherwise returns a stable `403` body.
- Admin endpoint tests cover missing endpoint-specific permission only when the
  route has endpoint-specific permission middleware. Lookup endpoints without
  endpoint-specific permission cover the actual base access rule when present.

## Package Conventions

- For package-backed DTOs, check validation, casts, defaults, and route or
  actor context at the DTO boundary; keep business side effects out of DTOs.
- For media/files, review collections, conversions, storage paths, URL exposure,
  and cleanup side effects together; writes stay in Tasks.
- For settings, review migrations, discovery/cache commands, defaults, and
  runtime fallback behavior; cache clears alone do not prove the contract.
- For roles and permissions, verify guard/scope, middleware or policy/gate,
  API documentation, and feature coverage together.

## Naming And Style

- Follow the adopted naming/style rules and repository instructions. When using
  ai-init, consult its naming and code-style companion guides if available.
- For style, PHPDoc, and comment findings, cite the concrete violated rule or
  failing tool output; do not paste broad style-guide summaries into review
  comments.
- Controllers use singular `StudlyCaps` plus `Controller`.
- Route collections are plural, route model parameters use the project Laravel
  binding style, and route names are `snake_case`.
- Tables are plural `snake_case`.
- Pivot tables use two singular model names in alphabetical order.
- Foreign keys are `model_id` by default; role/context names are allowed when
  they express the domain.
- Prefer meaningful `is_`/`has_` boolean names; if the adopted visibility flag
  is `active`, preserve it. No particular flag is required across applications.
- Instants/ranges use `_at`; business dates/times may use `_date` / `_time`.
- Enum case names are `UPPER_SNAKE_CASE`.
- Project enums use the shared enum helper/options trait when the project
  convention requires it.
- Project prose in comments, PHPDoc, tests, and docs uses the project target
  language; API documentation scenarios use it too unless the text is an
  external protocol/API term or literal.
- Recommended style keeps unused framework parameters named without `_`;
  report a violation only against an adopted rule.

## Database And Migrations

- Schema changes need migrations, indexes, foreign keys, rollback safety, and
  production-data compatibility reviewed together.
- Data migrations must preserve existing relations and values or explicitly
  document the safe fallback and deployment order.
- New query filters, ordering, uniqueness checks, and foreign-key lookups need
  supporting indexes when realistic data volume can make them hot paths.
- Factories and seeders should match the real schema and relation names; tests
  must not hide schema drift with ad hoc persistence shortcuts.

## Resources And Responses

- Non-trivial structured responses should go through dedicated Resources.
- Do not put API-only serialization helpers on Eloquent models.
- Split nested response objects into nested resource classes when the shape is
  meaningful or reused.
- Resource collections should be composed inside resources where possible.
- When response shape changes, compare the Resource, API documentation, and
  feature assertions together; do not infer contract safety from a green status
  check.
- Tests must assert changed fields and nested response shape, not only status,
  top-level keys, or counts.

## Imports Exports Files And Media

- Imports must validate file type, size, structure, authorization scope,
  duplicate handling, partial-failure behavior, and idempotency before any write.
- Exports and generated documents must enforce actor/tenant scope, avoid leaking
  hidden fields, and document the stable output contract when API clients consume
  the result.
- File/media writes need storage location, visibility, URL exposure, cleanup,
  retry, and rollback behavior reviewed with the Task or job that performs them.
- Tests should cover unauthorized access, invalid file shape, boundary sizes,
  failure cleanup, and the generated payload or file metadata that callers rely
  on.
- API documentation should describe uploaded inputs, generated outputs,
  downloadable responses, and failure statuses when those contracts are public.

## Tests

- Pest tests live in `tests/Feature|Unit` for the standard profile or the
  configured module/admin test directories for the modular profile.
- Recommended Pest style: one behavior per `it(...)`, target-language
  descriptions, function helpers without `$this`; preserve a different adopted
  style instead of rewriting unrelated tests.
- Prefer factories and states; use direct relation/query APIs only when they
  are the behavior under test or express setup more clearly.
- Relations use `for()`, `has()`, or `hasAttached()`.
- Do not use `attach`, `sync`, `associate`, query builders, or direct persistence
  shortcuts just to bypass missing factories/states. Direct relation/query APIs
  are acceptable only when that API is the behavior under test or no factory
  state expresses the setup cleanly.
- Feature tests use `route(...)`, not hardcoded paths.
- Every route/controller should have its own focused feature test file.
- Reject coverage-only smoke tests; each test must protect a real contract,
  regression risk, validation/authorization branch, response shape, or side effect.

## Security Scope

- If a PR touches auth, roles, tenant scope, media/files/imports, logs, cache,
  queues, or Octane, use `$et-security-audit` when available and load the matching reference before
  finalizing findings.
- Security findings need a realistic abuse path, affected actor/data, and code or
  runtime evidence. Do not label style, naming, or layering issues as security
  unless they create an access-control, data-exposure, integrity, or availability
  risk.
- For cache, tenant/context, and Octane/shared-runtime changes, check whether
  request-scoped state can leak across actors, tenants, requests, or workers.

## Queues Events Cache And Runtime

- Events and jobs that depend on committed database state need explicit
  after-commit behavior or a documented reason why dispatch before commit is
  safe.
- Queue jobs should carry stable identifiers and reload state inside `handle()`;
  do not rely on serialized request-scoped models, services, or context objects.
- Cache invalidation must cover every write path that changes the cached
  contract, including queued/event-driven writes and failed retries.
- Cache keys must include actor, tenant, locale, feature-flag, or request context
  only when that context is part of the contract; otherwise they risk leaking or
  fragmenting data.
- Runtime-state changes under Octane need reload/restart plus HTTP smoke
  evidence when they touch singletons, scoped bindings, static caches, config, or
  request-context resolvers.
- Broadcast or websocket changes need channel authorization, actor/tenant scope,
  event payload shape, queue/retry behavior, and frontend/runtime smoke checked
  without copying concrete channel or service names into generic guidance.

## AI And Skill Changes

- For AI/MCP/skill changes, inspect the actual client configuration and available
  AI workflow guidance; do not assume a particular documentation filename.
- Skills should keep trigger conditions in frontmatter, keep `SKILL.md` concise,
  link optional references explicitly, and avoid duplicating long reference
  content in the main body.
- Project skills should use `agents/openai.yaml` when neighboring project skills
  have it, with `display_name`, `short_description`, and `default_prompt` aligned
  to the `SKILL.md` trigger.
- Do not add absolute workstation paths or client-specific secrets to repo-owned
  AI/MCP configuration.
- AI-generated tests are not enough by origin; review whether they assert the
  real contract, failure branch, authorization branch, or side effect.

## Documentation Drift

- When a changed module has a local README or module docs, compare them with the
  actual routes, controllers, resources, events, commands, and public payloads.
  Treat module docs as review evidence to validate against code and tests, not
  as the source of truth over the implementation.
  Do not trust good global docs or neighboring modules as proof that module-local
  docs stayed current.

## Risk Lenses

Raise review attention for changes touching:

- Auth, roles, permissions, current actor, tenant, and context scope.
- Money, prices, discounts, rounding, quantities.
- Imports, exports, generated files, media, generated documents.
- Search indexing, vector/enrichment services, and pagination defaults.
- Migrations, backfills, rollback order, indexes, production data compatibility.
- Queues, events, after-commit behavior, cache invalidation.
- External integrations, retries, timeouts, raw payload logs.
- API resources, API documentation, public response contracts.
- Octane/shared runtime state, static mutable caches, singleton captures.
- AI prompts, generated code, MCP config, local tool wiring, and project skills.

# Test Style And Placement

Use this reference when creating or reviewing test files, imports, helpers,
groups, and assertion style.

## Placement and Style

- Read Pest configuration, autoloading, bootstrap and nearest tests to find
  discovery paths, fixtures, helper imports and database traits.
- Keep tests near their owner using the target's module or central test layout.
- Use focused behavior scenarios and the repository's chosen description language.
- Follow local usage of `it`/`test`, datasets, hooks, `$this`, imports and groups;
  these are conventions, not universal Pest restrictions.
- Reuse fixtures/helpers only when they clarify setup; do not create helper
  infrastructure for one trivial case. Follow local fixture-class placement.

## Assertions

- Use semantic HTTP assertions such as `assertOk()`, `assertCreated()`, and
  `assertUnprocessable()`.
- Prefer `expect(...)` style in isolated unit tests.
- Unit tests do not use Laravel HTTP helpers such as `route`, `actingAs`,
  `getJson`, or `postJson`.
- Use `route(...)` instead of hardcoded URLs in feature tests.
- Tests that touch cache, global state, or static runtime state must reset or
  fake that state explicitly and must not depend on execution order.

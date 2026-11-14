# Factory Setup

Use this reference when creating test data, fixtures, or admin actors.

## Factories And Relations

- Use factories and states for model setup.
- Express relationships with `for()`, `has()`, and `hasAttached()`.
- Avoid `attach`, `sync`, `associate`, `query`, `Builder`, and raw persistence
  shortcuts when they only compensate for missing factory states.
- Direct relation/query APIs are acceptable when that API is the behavior under
  test or a factory state would add more noise than clarity.
- If setup becomes larger than the scenario, check for an existing nearby state
  or helper first; if none exists, add a narrow factory state instead of a
  one-off relation/query shortcut.

## Fixtures

Use the target's fixture directories and autoloading conventions. Small local
fakes may be appropriate when allowed by the test style. Avoid duplicated
production algorithms in test fixtures.

## Admin Actors

- Reuse an existing admin-auth helper when available; otherwise authenticate the
  appropriate actor with the actual guard.
- Use the project's admin actor with permissions helper for normal admin
  endpoint coverage.
- Inspect helper defaults and actual middleware before assuming which base or
  endpoint permissions an actor receives.
- Super-admin helpers are reserved for bypass behavior tests.

# Pest Anti-Patterns

Use this reference when reviewing or writing tests that could become coverage
filler instead of behavior protection.

## Coverage Filler

Reject tests that exist only to increase coverage. A useful test protects a
real contract, regression risk, validation branch, authorization branch,
response shape, persistence change, queue/event/notification, file operation,
or integration boundary.

## Smoke-Only Assertions

Status-only and count-only assertions are not enough for endpoint behavior.
Prefer semantic status helpers plus one or more of:

- exact response fields or missing fields;
- validation error fields;
- permission outcome;
- database before/after state;
- queued/event/notification/file assertions;
- inclusion and exclusion for list filters/search.

For file/media flows, include rejected-path assertions: unauthorized or
out-of-scope actor, invalid file input, no persisted media record, and no stored
file when the request is rejected.

For file upload/import/export flows that fake storage, also cover the accepted
path: response contract, persisted record or side effect, and expected file
presence. Rejected paths assert the error, no new record or side effect, and no
stored file.

Do not validate API response contracts through `assertSee`, string search in
JSON, or apidoc-only smoke tests. Assert the status, exact JSON fields, missing
envelope fields, and relevant side effects directly.

## Internal Mocks And Unasserted Fakes

Feature tests for endpoints should not mock module Actions, Tasks, Repositories,
or internal Services just to exercise route wiring. Fake external boundaries
instead: HTTP clients, notifications, mail, storage, queues, events, buses,
external enrichment services, and third party SDKs.

Every fake must have positive or negative assertions on recipient, job, event,
path, payload, or request.

Cache, search-index, and broadcast fakes need observable assertions: expected
key invalidation, indexing request, broadcast payload, or an explicit assertion
that the side effect did not happen on the rejected path.

## Admin Permission Tests

- Follow actual route guards, policies and permission middleware. Test an
  authenticated actor missing the required permission and verify denial.
- If base administrative access exists, distinguish it from endpoint permission.
  Do not invent either requirement for routes that do not have it.
- Inspect test-helper defaults rather than assuming an empty permission list
  grants base access. Super-admin bypass should not mask normal permission checks.

## Validation And Domain Errors

`422` tests assert exact invalid fields with `assertJsonValidationErrors(...)`
or `assertInvalid(...)`. Domain `422` tests assert the domain error code or
scenario and missing write side effect when relevant.

Assert localized validation message text only when the localization contract is
under test. For ordinary endpoint tests, prefer fields, codes, or contract keys
in the project target language.

## Lists

List filter/search tests create matching and decoy records. Assert both
inclusion and exclusion, not only total count or first id.

Changed list contracts must cover declared pagination, sort, and filter
parameters.

## Unit Boundaries

Unit tests do not use HTTP helpers (`route`, `actingAs`, `getJson`, `postJson`).
DB-backed Action/Task/Job/command/listener tests are feature/integration tests
unless the unit test explicitly covers a rule, DTO, value object, or model
behavior.

## Documentation Examples

Examples in transferable docs may use placeholders, but they should stay
internally consistent: defined actor, factories, route shape, realistic payload,
and behavioral assertions.

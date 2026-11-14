# Messages, API Documentation, And Tests

Use this reference when changing message text, 422 examples, custom Rule
messages, or validation tests.

## Message Quality

- Every validation message shown to a client or admin manager must use the
  project target language.
- Avoid raw technical field names such as `entity_id`, `status_code`, or
  `entries.0.value` in final text.
- Avoid machine-like repetition. Prefer concise project target language wording over a
  sentence that repeats the same field label twice.
- Keep messages specific enough to fix input, but do not leak table names, enum
  classes, internal IDs, or relation names unless the API contract exposes them.
- Match the audience: public API messages for clients, admin messages for
  administrative panel managers.

## Nested Fields

- Nested array keys have readable attributes for the field that fails
  validation.
- Nested/wildcard aliases use stable keys such as `entries.*.value`, not
  per-index aliases such as `entries.0.value`.

## API Documentation And Tests

- When the documented validation contract changes, `@response 422` examples use
  real DTO or route fields and actual project target language messages.
- No placeholder fields such as `field`, `foo`, or `bar` are used in endpoint
  validation examples.
- No generic placeholder message is used when Laravel returns a more specific
  real message.
- Custom Rule messages have isolated tests when exact text matters.
- Feature tests assert status and error fields, not exact endpoint message text.

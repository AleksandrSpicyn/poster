# Artifact Names

Use these conventional examples for supporting classes and non-HTTP artifacts
when they match the target framework and local naming rules.

## Domain Artifacts

- Events are facts in past tense, such as `EntityPublished`.
- Listeners are action names, such as `SendEntityNotification` or
  `AssignExampleState`.
- Jobs use imperative action names, such as `SendEntityEmail` or
  `ProcessExample`.
- Artisan command classes use action names; command signatures use plural
  module/resource names plus an action, such as `entities:promote`.
- Policies use the resource name plus `Policy`.
- Validation rules use the rule concept name; a `Rule` suffix is optional when
  local convention uses it.

## Database Artifacts

- Migration filenames use timestamp plus action and table, such as
  `create_entities_table`.
- Seeder classes use the entity/resource name plus `Seeder`.
- Factory classes use the model name plus `Factory`.

## Tests And Configuration

- Test classes use `StudlyCaps` plus `Test`.
- Config lookup paths use dot notation; individual key segments follow local
  casing, commonly `snake_case`.
- Environment variables use `UPPER_SNAKE_CASE`.
- Translation keys use `snake_case`.

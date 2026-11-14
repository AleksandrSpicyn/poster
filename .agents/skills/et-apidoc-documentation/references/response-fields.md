# Successful Response Fields

Use this reference for Scribe `@responseField` annotations. It covers only
successful JSON responses. Error bodies remain complete `@response` examples
without response-field schemas.

## Required Inventory

Document every field the controller can emit in at least one successful JSON
shape:

- top-level envelope fields;
- parent objects and lists;
- every nested and leaf field;
- nested `::make`, `::collection`, and Resource values;
- paginator `links` and `meta` fields;
- controller-reachable conditional Resource fields.

Do not rely on Scribe to synthesize unnamed parent objects. Every parent needs
its own type and description.

## Discover The Runtime Schema

Trace the response in this order:

1. Read the route and controller return value and status.
2. Follow the Action, then its Tasks and Repositories, to collect load decisions.
3. Read the main Resource `toArray()` and its response wrapper.
4. Recursively read every nested Resource and collection.
5. Resolve `whenLoaded`, `whenNotNull`, `whenNull`, `mergeWhen`,
   `whenCounted`, `whenAggregated`, `whenPivotLoaded`, and equivalent helpers.
6. Add fields from `with()`, `additional()`, pagination, or the project's
   response envelope.

A Resource field belongs in the controller schema only when this runtime path
can emit it. A conditional field reachable in any successful path is included,
even when another successful path omits it.

## Annotation Contract and Examples

For Scribe 5.x, begin with the supported response-field syntax:

```php
/**
 * @responseField data object Created article.
 * @responseField data.id integer Article identifier.
 * @responseField data.title string Article title.
 * @responseField data.is_published boolean Publication flag.
 * @responseField data.author object Author loaded by this endpoint.
 * @responseField data.author.id integer Author identifier.
 */
```

Document every parent and leaf. Distinguish optional keys and nullable emitted
values in the schema. Verify supported required/nullable syntax for the installed
version; do not transplant custom modifiers from another application.

| Wire value | Schema concern |
|---|---|
| object | properties and allowed additional keys |
| object list | array with object items, including nested fields |
| primitive list | array with the actual item type |
| integer / number | actual JSON numeric type and supported bounds |
| nullable | null is allowed independently of whether the key is present |
| variable shape | reachable variants and condition-specific examples |

Use `Enum:` only for a verified closed value set, with examples compatible with
that set. A fixed message, boolean value, zero or null in one example is not an
enum. Do not rely on arbitrary prose to populate machine-readable schema.
Extended modifiers, typed-path syntax and custom date formats need a documented
parser/strategy contract and generated-output checks.

## Conditional And Variant Fields

- `whenLoaded`: inspect whether this controller loads the relation. Describe
  the load condition when the field is reachable; omit the field otherwise.
- `whenNotNull`: include the field when the value can be non-null and state the
  condition.
- `whenNull`: include the field when the null branch is reachable. Preserve
  its semantic base type/nullability when the contract includes other values;
  use the installed generator's supported representation rather than assuming
  a particular nullable-union grammar.
- `mergeWhen` and similar helpers: document every merged field from every real
  successful branch.
- `MissingValue`: treat it as an omitted key, not as `null`.

When successful scenarios have different shapes, `@response` examples stay
scenario-specific. `@responseField` annotations describe the union of their
fields. The description identifies the scenario or condition for a field that
is not present in every successful shape.

Verify that the generator or custom strategy applies successful field metadata
only to the intended `2xx` responses. Error schemas come from their own `@response` examples, so a
successful `message`, `success`, enum, or constraint cannot leak into an error
scenario with the same field path.

Add one complete `@response` for every reachable distinct set of fields or
runtime types. Different values inside the same JSON schema do not create a new
shape.

## Complete Response Example

```php
/**
 * @response 200 scenario="Article with author" {
 *   "data": {
 *     "id": 7,
 *     "title": "Portable example",
 *     "is_published": false,
 *     "author": {"id": 3, "name": "Author"}
 *   }
 * }
 */
```

For an endpoint that can omit `author`, add a separate complete scenario and
verify the exported optionality. If `author` may be null, describe that separately
from omission. Do not make it nullable merely because another endpoint omits it.

## Completion Check

- Compare every success-example key with the response-field inventory.
- Compare the inventory with the main and nested Resource call graph.
- Verify every path, parent container, type, enum value, and description.
- Verify every optional field has a real controller condition.
- Verify error, `204`, file, and stream responses have no response-field schema.

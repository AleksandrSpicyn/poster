---
name: et-apidoc-documentation
description: Use when creating, changing, reviewing, or auditing Scribe/apidoc controller docblocks, endpoint titles, descriptions, groups, subgroups, authentication notes, headers, URL/query/body params, responses, response fields, response files, documented status codes, or API error scenarios.
---

# et-apidoc-documentation

## Adapting to the Target Repository

Read the target repository's instructions, dependency manifests and lockfiles,
configuration, and nearby implementations before applying this skill. Discover
actual source/test paths, namespaces, commands, languages, guards, and contracts;
no particular directory layout or application API is required.

Use the concrete conventions and examples in this skill as a recommended
starter baseline. Distinguish that baseline from framework/package requirements
and from application-specific extensions. Preserve an explicitly different
existing convention; otherwise apply the documented rule rather than reducing
it to “follow local style”. Check installed versions before using package APIs.
Standard and modular profiles change file placement, not behavioral guarantees.

Read local documentation when it exists and is relevant; verify it against
current code. Other skills are optional companions when installed, not required
dependencies. Keep verification proportional to the changed behavior.

## Workflow

1. Inspect the installed Scribe/documentation package version, configuration,
   custom strategies and available generation command. Use the annotation grammar
   that this installation actually supports; local parser extensions are not
   portable Scribe APIs.
2. Read routes, middleware, validators, controller return values, serializers,
   exception rendering and relevant tests. Document reachable runtime behavior.
3. Describe purpose, authentication, headers and URL/query/body fields. Derive
   requiredness, bounds, enums and formats from validation and real behavior.
4. Use `references/responses.md` for response scenarios and statuses, and
   `references/response-fields.md` for nested successful JSON schemas.
5. Generate documentation with the target's supported command. Inspect rendered
   output and exported OpenAPI for types, nullability, enums, examples, defaults,
   constraints and response variants.
6. Use focused route tests when runtime behavior or documented examples change.
   A docblock substring check does not prove the response contract.

## Annotation Safety

- Never assume custom `required`, `deprecated`, `format`, `Enum:`, union syntax,
  wildcard paths or example-precedence rules are supported. Check the installed
  parser before emitting them; verify their generated schema representation.
- Describe actual wire date/time values. Do not label a non-RFC3339 timestamp
  with OpenAPI `date-time` merely because it represents an instant.
- Distinguish omitted keys from nullable values. Use known item types for lists
  and complete bodies for structural examples where supported.
- Exhaustive enums require a verified closed value set. A scenario-specific
  message, zero, empty string or null does not imply an enum.
- File examples may be interpreted as real filesystem paths. Use supported
  no-example handling or a safe existing fixture; never invent a path.
- Keep language, error-code notation, grouping and line width aligned with the
  target's documentation conventions. Do not require a particular heading,
  language, response envelope or local date-time parser extension.

## Companion Guides

When using the ai-init checkout, consult the relevant guides below. A standalone
skill installation may omit them; use available local guidance and the bundled
references instead. These guides describe both standard and modular layouts.

- [API documentation](../../docs/apidoc.md)
- [Architecture profiles](../../docs/architecture.md)

## Practical Examples

Read [references/examples.md](references/examples.md) when implementing or
reviewing this behavior. It contains concrete code, assumptions and pitfalls
for adaptation to standard or modular Laravel applications.

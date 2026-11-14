---
name: et-rest-naming-conventions
description: Use when naming or reviewing REST routes, controllers, models, tables, relations, DTOs, resources, permissions, artifacts, factories, or tests in standard or modular Laravel projects.
---

# REST Naming Conventions

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

## Overview

Use project naming rules instead of ad hoc Laravel habits. Read
the repository naming documentation; use this skill's references when a name affects both URI
design and code structure.

## References

- `references/http-and-layers.md` for routes, controllers, DTOs, resources,
  admin placement, permissions, and test names.
- `references/database-and-eloquent.md` for tables, pivots, foreign keys,
  booleans, dates, external identifiers, and relations.
- `references/artifact-names.md` for events, listeners, jobs, commands,
  migrations, seeders, factories, tests, and config/env/translation keys.

## Trigger Boundaries

- Trigger before creating a new persisted name or public contract.
- Trigger during review when a name differs from nearby module conventions.
- Check legacy names before renaming; old names may be accepted only because
  they are already part of a schema or external contract.
- Prefer renaming before merge when the inconsistent name is new.
- Do not apply naming cleanup to generated, vendor, externally provided, or
  package-owned code unless the user explicitly scopes it in.

## Must Check

- Verify route, migration, model, relation, permission, factory, and test names
  use one coherent vocabulary before merge.
- Prefer renaming new inconsistent names instead of carrying them forward.

## Companion Guides

When using the ai-init checkout, consult the relevant guides below. A standalone
skill installation may omit them; use available local guidance and the bundled
references instead. These guides describe both standard and modular layouts.

- [Naming conventions](../../docs/naming.md)

## Practical Examples

Read [references/examples.md](references/examples.md) when implementing or
reviewing this behavior. It contains concrete code, assumptions and pitfalls
for adaptation to standard or modular Laravel applications.

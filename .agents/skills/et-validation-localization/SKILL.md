---
name: et-validation-localization
description: Use when creating or changing project target language validation errors for public APIs or admin panels, including DTO validation, validation attributes/messages, custom validation rules, validation lang files, nested/wildcard attributes, or 422 API documentation responses.
---

# Validation Localization

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

Use this skill whenever validation rules or validation error output changes. The
source of truth is the repository validation documentation when it exists and is
inside the approved read scope.

The goal is a real user-facing contract: project target language messages,
clear field names, and examples that match the endpoint behavior.

## References

- `references/placement.md` for DTO vs validation language file placement,
  validation attributes, `rules()`, scoped exists, and import payloads.
- `references/messages-tests.md` for project target language message quality, 422 API
  documentation, nested/wildcard aliases, and test assertions.

## Anti-Triggers

- Do not turn domain/business exceptions into artificial `422` validation just
  to reuse DTO machinery.
- Do not edit externally provided or package-owned skills while applying project
  validation rules.

## Workflow

1. Read the placement and message/testing reference that matches the change.
2. Update DTO rules, validation language entries, API documentation, and tests
   only where the validation contract changes.
3. In endpoint feature tests, assert status and error fields, not exact text.
4. After PHP edits, use `$et-php-style-quality`; run the smallest relevant
   validation test set.

## Checks

- Touched validation output uses the project target language.
- API documentation examples use real DTO or route fields.
- Custom Rule message text has isolated coverage when exact wording matters.

## Companion Guides

When using the ai-init checkout, consult the relevant guides below. A standalone
skill installation may omit them; use available local guidance and the bundled
references instead. These guides describe both standard and modular layouts.

- [Validation and localization](../../docs/validation.md)

## Practical Examples

Read [references/examples.md](references/examples.md) when implementing or
reviewing this behavior. It contains concrete code, assumptions and pitfalls
for adaptation to standard or modular Laravel applications.

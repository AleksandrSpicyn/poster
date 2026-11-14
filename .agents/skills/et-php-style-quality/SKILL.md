---
name: et-php-style-quality
description: Use when editing or reviewing PHP style, typing, PHPDoc, enums, array contracts, Pint/Rector/PHPStan output, or quality gates in standard or modular Laravel projects.
---

# PHP Style Quality

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

Use local coding rules from the repository style documentation and AGENTS.md. Check local
command documentation when choosing verification commands. Prefer existing
local style over generic PSR-12 summaries.

## References

- `references/php-style-rules.md` for formatting, typing, PHPDoc, enum, array,
  comment, and false-positive checks.
- `references/quality-gates.md` for choosing Pint, Rector, PHPStan,
  targeted tests, or the repository quality gate.

## Trigger Boundaries

- Trigger on PHP files, migrations, factories, tests, config PHP, and docs that
  define PHP style rules.
- Trigger when a tool reports style/static-analysis issues, even if the fix is
  outside the line that failed.
- Do not make broad modernization changes only because this skill is active.
- Do not touch generated, vendor, externally provided, or package-owned code
  unless the user explicitly scopes it in.

## Must Check

- Apply only touched-scope fixes unless the user explicitly asks for broader
  cleanup.
- Keep verification proportional to the change and report what was not run.
- Do not present style/static gates as semantic proof for runtime behavior.

## Review Checklist

- Verify style with project rules, not personal taste.
- Verify typing and docblocks are coherent.
- Verify no helper or modernization introduced noise unrelated to the task.
- Run the relevant local verification before handing work back.

## Companion Guides

When using the ai-init checkout, consult the relevant guides below. A standalone
skill installation may omit them; use available local guidance and the bundled
references instead. These guides describe both standard and modular layouts.

- [PHP style and verification](../../docs/code-style.md)

## Practical Examples

Read [references/examples.md](references/examples.md) when implementing or
reviewing this behavior. It contains concrete code, assumptions and pitfalls
for adaptation to standard or modular Laravel applications.

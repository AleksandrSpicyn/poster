---
name: et-security-audit
description: Use when reviewing standard or modular Laravel projects for realistic security risks in repository-wide, branch-diff, module, endpoint, or finding-level work involving public API, admin authorization, tenant or context scope, file/media exposure, imports/exports, auth flows, logs, raw SQL, queues, cache, or Octane runtime state.
---

# Security Audit

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

Use this reusable skill for security work in standard or modular Laravel repositories.
It complements generic Laravel security guidance without editing externally
provided or package-owned skills.

Sources of truth:

- `AGENTS.md`
- repository architecture, admin/RBAC, testing, API documentation, validation,
  deployment, and Octane docs when they exist and are inside the approved read
  scope
- repository package docs only when the task explicitly scopes package behavior
  and the docs are inside the approved read scope
- scope-approved security-review artifacts when they are inside the approved
  read scope
- changed code, routes, middleware, DTOs, Actions, Tasks, Resources, config,
  migrations, factories, and tests

## References

- `references/tenant-scope.md` for current actor/tenant/context scope.
- `references/files-media-imports.md` for files, media, imports, exports, logs,
  generated documents, and external URLs.
- `references/auth-runtime.md` for auth flows, OTP/SMS, tokens, config,
  broadcast/channel auth, queues/cache, and Octane runtime state.
- `references/query-input.md` for raw SQL, dynamic filters/sorts, search
  parameters, and cache keys derived from user input.
- `references/reporting-errors.md` for severity calibration, false positives,
  blocked evidence, and report wording.

## High-Risk Surfaces

- Public API and admin authorization.
- Current actor, tenant, and context scope.
- Files, media, generated documents, imports/exports, logs, and payment links.
- Auth flows, tokens, OTP/SMS, token/session auth, and broadcast/channel
  boundaries.
- Raw SQL, dynamic filters/sorts, queues, cache, and Octane runtime state.

## Audit Workflow

1. Define scope: repository-wide, branch diff, module, endpoint, or finding.
2. Read scope-approved security-review artifacts only when they are inside the
   approved read scope, then re-validate every candidate against current code.
3. Map routes and middleware with the repository's route-list command or an
   equivalent Artisan route-list command with the relevant filter.
4. Trace each candidate from source to sink: request input, validation, auth,
   Action/Task, persistence, Resource/response, logs, queues, files, or cache.
5. Classify severity conservatively: Critical, High, Medium, Low,
   Informational.
6. For confirmed High/Medium issues, include a concrete realistic abuse path.
7. Recommend the narrowest Laravel-native fix and the test that covers it.

## Evidence Rules

- Do not invent vulnerabilities.
- Do not report stale findings from older audit docs without current evidence.
- Cite exact local file paths and line numbers.
- Distinguish confirmed issues from hardening opportunities.
- Prefer behavior-level impact over generic OWASP labels.
- Treat missing authorization, tenant scope, permanent document URLs, secret
  leakage, and raw payload logs as security issues only when a realistic abuse
  path exists.

## Anti-Triggers

- Do not use this skill for purely stylistic, naming, or performance-only work.
- Do not treat every missing validation rule as security; first prove a
  realistic abuse path such as unauthorized access, data leak, integrity break,
  or practical abuse.
- Do not edit externally provided or package-owned skills while applying project
  security rules.

## Report Format

For durable reports, save to a user-approved security-review artifact location
only when the user explicitly asks for a file.

Use this structure:

1. Summary with severity counts.
2. Methodology and commands actually checked.
3. Findings ordered by severity.
4. Evidence with exact paths and lines.
5. Realistic abuse path for confirmed High/Medium issues.
6. Recommended fix.
7. Verification or test suggestion.

## Review Checklist

- Pick the relevant reference file before deep review.
- Confirm middleware/guard/scope from routes to persistence and response.
- Confirm sensitive values do not leak through Resources, files, logs or cache.
- Confirm realistic tests exist for forbidden access, validation failures and
  non-leakage behavior.

## Companion Guides

When using the ai-init checkout, consult the relevant guides below. A standalone
skill installation may omit them; use available local guidance and the bundled
references instead. These guides describe both standard and modular layouts.

- [Authorization and scope](../../docs/authorization.md)
- [Runtime and delivery](../../docs/concurrency-and-delivery.md)

## Practical Examples

Read [references/examples.md](references/examples.md) when implementing or
reviewing this behavior. It contains concrete code, assumptions and pitfalls
for adaptation to standard or modular Laravel applications.

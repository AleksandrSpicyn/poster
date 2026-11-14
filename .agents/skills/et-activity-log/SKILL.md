---
name: et-activity-log
description: Implement, debug, review, or test Laravel audit/activity logging, including attribution, parent subjects, relation changes, bulk writes, no-op suppression, sensitive fields, and transaction behavior.
---

# et-activity-log

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

1. Identify the installed audit/activity package, version, configuration, model
   traits/observers, and any application wrappers. Do not assume ordinary model
   writes are automatically logged.
2. Trace the mutation and all side effects. Determine whether it fires model
   events or bypasses them through bulk SQL, relation sync, imports, or a package.
3. Determine the subject: changed entity, parent aggregate, or relation. Preserve
   actor, source, correlation metadata, transaction and tenant boundaries.
4. Reuse existing logging hooks. Avoid duplicate records when events already
   log the mutation. For event-bypassing writes, use a verified package or local
   bulk API, or explicitly capture normalized before/after state.
5. For composite upserts, scope snapshots and affected rows by the complete
   business key. Never infer scope from its first column.
6. Preserve domain/package methods that own validation, caches, events and file
   cleanup. Do not replace them with raw persistence merely to simplify logging.
7. Apply explicit system attribution only where the existing operation boundary
   does not already provide it. Ensure context is cleared between jobs/requests.

## Guardrails

- Do not assume application-specific builders, context classes, traits, sync
  methods, or exclusion markers exist. Inspect signatures before using wrappers.
- Log actual changes, suppress no-ops, and define deletion/sync semantics.
- Exclude secrets and sensitive values; use safe change markers where needed.
- Do not add read logging unless it is explicitly part of the audit requirement.
- Confirm rollback behavior and whether the log shares the mutation transaction
  or uses an intentionally separate durable channel.
- Parent aggregation must retain enough child/relation identity to explain the
  change without leaking fields the actor cannot access.

## Verification

Use focused integration coverage for the changed write path. Establish an
activity baseline after fixture setup. Verify subject, event, normalized diff,
actor/source, no-op suppression, tenant scope and rollback behavior where
relevant. Avoid duplicating package infrastructure tests.

## Companion Guides

When using the ai-init checkout, consult the relevant guides below. A standalone
skill installation may omit them; use available local guidance and the bundled
references instead. These guides describe both standard and modular layouts.

- [Activity logging](../../docs/activity-log.md)

## Practical Examples

Read [references/examples.md](references/examples.md) when implementing or
reviewing this behavior. It contains concrete code, assumptions and pitfalls
for adaptation to standard or modular Laravel applications.

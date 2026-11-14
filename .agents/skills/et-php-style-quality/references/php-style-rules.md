# PHP Style Rules

Use this as a ready-to-adopt style baseline for either Laravel layout. These
are conventions with concrete defaults, not universal PHP requirements. Existing
formatter rules take precedence for a repository that has chosen a different
style; do not turn that caveat into an excuse to omit the detailed rules.

## Formatting Basics

- Use short arrays `[]` and single quotes by default.
- Use strict comparisons and `&&`/`||`.
- Put multiline boolean operators at the start of the continued line.
- Add a blank line before `return`, `break`, `continue`, `throw`, `try`,
  conditionals, loops, and `switch`.
- Keep method chains multiline and readable.
- Use trailing commas in multiline arrays, parameters, and `match`.
- Sort imports consistently with local formatter rules.

## Typing And PHPDoc

- Add explicit argument, property, and return types.
- Use `?Type` in signatures; use `Type|null` in PHPDoc.
- Avoid `mixed` unless a parent, framework, external contract, or backward
  compatibility requires it.
- Use PHPDoc for array shapes, generics, non-obvious iterables/callables,
  meaningful propagated exceptions at public/domain boundaries, and parent
  compatibility.
- Do not add PHPDoc that only repeats an obvious signature.
- Keep PHPDoc tags ordered as `@param`, `@return`, `@throws`.

## Enums

- Name enum cases in `UPPER_SNAKE_CASE`.
- Backed enum values may follow domain/API strings such as `main_category`.
- If the project has a shared enum helper/trait for labels, values, or options, every project enum uses the local pattern consistently.

## Arrays And Contracts

- Avoid large anonymous arrays for domain contracts, response shapes, bulk
  inserts, and nested payloads.
- Stable contracts should become DTO/Data/Resource/value objects.
- Named arrays are acceptable as local non-contract plumbing.

## Flow And Comments

- Prefer collection pipelines for simple transformations.
- Prefer plain loops for side effects, early exits, or stateful algorithms.
- Do not store request/runtime state in mutable static properties or long-lived
  singleton-like objects. Prefer request-scoped dependencies, explicit
  arguments, or local value objects; cache/global state needs a clear lifecycle
  and test reset.
- Add inline comments only for non-obvious domain invariants, workarounds,
  external protocols, or risk context.

## False Positive Checks

- Do not prefix intentionally unused framework callback parameters with `_`.
- Do not change backed enum values when they are external contracts.
- Treat raw SQL/query fragments as behavior and data-boundary risks: prefer
  query-builder bindings or named abstractions, and do not reclassify a
  style-only issue as security-sensitive without a concrete data-flow risk.
- Do not replace readable loops with collections when the loop has side effects
  or early returns.
- Do not add broad Rector/Pint churn unrelated to the task.

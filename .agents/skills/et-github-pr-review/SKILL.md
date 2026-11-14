---
name: et-github-pr-review
description: Use when reviewing standard or modular Laravel GitHub PRs or current-branch diffs, including requested changes, pre-merge checks, review comments, architecture/naming/testing/API-doc rules, resources and response contracts, regressions, N+1, data migrations, Octane/runtime, AI/tooling, security-sensitive code, duplication, hardcoded values, style, language, or quality-gate results. Do not use for generic GitHub issue triage without code review.
---

# GitHub PR Review

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

Review pull requests with a code-review mindset. Findings come first: bugs,
regressions, unsafe changes, broken contracts, architecture violations, missing
tests, style violations, maintainability issues, and documentation drift.

Discover local review rules from repository instructions, relevant documentation,
CI configuration, dependency versions and current implementation. Do not require
any particular documentation filename or task runner.

## References

Load only the reference needed for the current review pass:

- `references/project-rules.md` for architecture, admin, naming, resources,
  tests, style, and risk lenses.
- `references/workflow-and-commands.md` for the evidence pack, review passes,
  current repository command inventory, repository quality gate, and posting
  rules.
- `references/comment-style.md` for project target language GitHub comment wording and review
  action choices.
- the repository concurrency documentation when the PR touches
  `Octane::concurrently`, a project concurrent runner, Swoole task workers,
  long-lived runtime state, or request-time aggregation.

## Required Workflow

Use `references/workflow-and-commands.md` as the workflow source of truth:
collect the evidence pack, read the submitted patch before fixers, run the
required review passes, apply the repository quality gate when substantive, and
keep GitHub-facing comments concise and free of process metadata.

## Non-Negotiable Checks

- Do not approve code that you could not verify.
- Do not treat green CI or a green repository quality gate as proof of semantic correctness.
- Do not treat AI-generated code, docs, tests, or review comments as accepted
  until they are checked against real project files and verification output.
- Report missing tests when a behavior, validation branch, authorization branch,
  response shape, data migration, or bug fix changed.
- Reject coverage-only smoke tests that assert only status/counts without the
  real contract or side effect.
- Evaluate layer dependencies against the architecture actually adopted by the
  repository. Do not require Actions, Tasks, repository inheritance, particular
  entrypoints or admin directories when those patterns are absent.
- Reject blind concurrency refactors. A concurrent path needs independence proof,
  task-worker/runtime checks, behavior coverage, and measured benefit against the
  realistic sequential path.

## Parallel Review

Use subagents only when the user explicitly asks for them. Keep all GitHub
posting centralized in the main agent; subagents may return evidence and
candidate findings, but they must not post directly.

## Companion Guides

When using the ai-init checkout, consult the relevant guides below. A standalone
skill installation may omit them; use available local guidance and the bundled
references instead. These guides describe both standard and modular layouts.

- [General development rules](../../docs/general.md)
- [Architecture profiles](../../docs/architecture.md)
- [Testing](../../docs/testing.md)

## Practical Examples

Read [references/examples.md](references/examples.md) when implementing or
reviewing this behavior. It contains concrete code, assumptions and pitfalls
for adaptation to standard or modular Laravel applications.

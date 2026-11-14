# Review Workflow And Commands

## Evidence Pack

Collect this before conclusions:

- PR metadata: number, title, base/head refs, head SHA, author intent, linked task,
  changed file list.
- Raw submitted patch from `gh pr diff <pr> --patch` before fixers.
- Existing comments, requested changes, CI/check status, and unresolved review
  threads when thread state matters.
- Local context around changed lines: routes, controllers, DTOs, Actions, Tasks,
  Resources, migrations, factories, tests, API documentation, config,
  queues/events.
- Verification output: exact command names, pass/fail status, and whether tools
  modified files.
- Diff anchors: latest head SHA, changed path, side, and line.

Green CI does not clear architecture, data, contract, performance, or test-quality
risks.

## Required Passes

1. Evidence and scope.
2. Behavior and regressions.
3. Architecture and boundaries.
4. Tests and verification.
5. API contract and docs.
6. Resource design.
7. Database and data integrity.
8. Security and permissions.
9. Performance, Octane, and runtime.
10. AI/tooling guidance when prompts, MCP config, or skills changed.
11. Maintainability.
12. Style and PHPDoc.
13. Project target language and docs.
14. Waste, unrelated rewrites, dead code, and broad formatting churn.

## Commands

```bash
gh pr view <pr> --json number,title,headRefName,baseRefName,headRefOid
gh pr view <pr> --json number,title,body,author,headRefName,baseRefName,headRefOid,commits,files,reviewDecision,statusCheckRollup
gh pr view <pr> --comments
gh pr view <pr> --json reviews,comments
gh pr diff <pr> --name-only
gh pr diff <pr> --patch
<quality-gate-command>
```

Focused searches:

```bash
gh pr diff <pr> --patch > /tmp/pr.patch
git diff --name-only
git diff --check
rg -n "success\\(\\[" <module-paths> <shared-paths>
rg -n "function get[A-Z].*Array\\(" <module-paths> <shared-paths>
rg -n "\\benv\\(|\\$request->all\\(|DB::(select|statement|raw)|::all\\(\\)" <module-paths> <shared-paths>
rg -n "//|/\\*|\\*" <module-paths> <shared-paths> docs -g "*.php" -g "*.md"
```

## Handling Repository Quality Gate

- Run relevant documented checks for substantive review, using an isolated
  environment when available. Do not execute unknown scripts without reading
  their effects.
- Run it after reading the submitted patch, not before.
- If it fails because of branch changes, map the failure to the closest changed
  line or changed file and report it as blocking.
- If it cannot run because of environment limits, say that in the final response
  and do not approve the PR.
- After the repository quality gate, check `git diff --name-only`. If fixers changed PR
  files, treat the submitted auto-fixable code as a review finding.
- A green repository quality gate is not proof that shared helpers, API contracts, data
  migrations, search behavior, or runtime semantics are correct.
- DB-backed targeted test files can collide when run in parallel. When that
  happens, rerun them sequentially before relying on the result.

## Posting

- Post only when the user explicitly asks for GitHub comments/review submission.
  Otherwise return draft findings to the user.
- Prefer inline comments for concrete changed lines.
- Do not guess diff anchors; use a project target language review body with `path:line`
  when unsure.
- Re-check the latest head SHA before posting inline comments.
- Re-check unresolved thread state before duplicating an existing review comment.
- One issue per comment.
- Keep the project target language review body short after inline comments.
- Keep review comments focused on evidence and impact. Include verification
  results or environment limitations when useful to the author; follow the
  repository review template instead of imposing a personal reporting format.

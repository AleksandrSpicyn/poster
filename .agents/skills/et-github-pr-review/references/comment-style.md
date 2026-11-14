# Comment Style

## Rules

- Write GitHub comments in the project target language.
- Findings first, ordered by severity.
- Keep each comment short: problem, impact, expected fix.
- One comment covers one issue.
- Reference the project rule only when it helps the author fix the issue.
- Add a tiny code example only when it removes ambiguity.
- Avoid praise, filler, generic nitpicks, and long theory.
- Include minor findings when they are real project-rule, style, simplicity,
  duplication, resource-design, language, or documentation issues.
- For AI/tooling changes, name the concrete reproducibility or reviewability
  problem instead of arguing that AI was used.
- For Octane/concurrency changes, state the independence, runtime, or benchmark
  gap that makes the refactor unsafe.

## Comment Example Shapes

Write examples in the project target language and keep the shape:
problem -> impact -> expected fix.

- A dependency violates the adopted architecture; restore the documented boundary.
- Behavior changed without a focused validation/authorization/side-effect test.
- API documentation describes an old response contract.
- Test checks only status/counts instead of the protected contract.
- Resource can introduce N+1; eager load or use `whenLoaded()`.
- Application code reads `env()` instead of `config(...)`.
- Filter/order path lacks a supporting index.
- Access path lacks current actor or tenant-scope denial coverage.
- Skill frontmatter misses a trigger, so the skill may not activate.
- Octane concurrent path lacks sequential comparison and runtime smoke.

## Review Actions

- `gh pr review <pr> --request-changes -b "..."` for blocking findings or a
  red repository quality gate caused by the PR.
- `gh pr review <pr> --comment -b "..."` for non-blocking findings.
- `gh pr review <pr> --approve -b "<project target language approval body>"` only when
  no material findings remain and the repository quality gate passed.
- `gh pr comment <pr> --body "..."` for a general timeline note when a review
  action is not appropriate.

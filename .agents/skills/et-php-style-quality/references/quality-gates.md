# Verification Commands

Discover commands from the repository's scripts, task runner, CI and installed
tools. Do not assume Docker, Make targets, a PHPStan level or a named pipeline.

Typical tools include Pint, Rector, PHPStan/Larastan and Pest/PHPUnit. Verify
installed versions and supported flags before execution. Prefer the project's
wrapper when it supplies required container or environment configuration.

Read the submitted diff before running fixers. In a review, prefer check modes;
if a tool writes files, preserve existing work and report the resulting changes.
Run focused behavior coverage and relevant static checks for changed contracts.
Broaden checks for cross-cutting changes or when required by repository rules.
Docs/skill-only changes normally need content, reference and metadata validation.

Do not run colliding database tests in parallel against a shared database.
Report exact checks and limitations. Formatting/static success does not prove
runtime semantics, CI status, deployment or production behavior.

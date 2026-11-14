# Reporting And Error Handling

Use this reference when a suspected security issue has incomplete evidence,
unclear severity, or overlaps with hardening.

## Severity Calibration

- Critical: unauthenticated or broadly reachable compromise, secret exposure, or
  destructive cross-tenant access with realistic exploitation.
- High: authenticated cross-tenant access, admin authorization bypass, permanent
  sensitive document exposure, token/OTP compromise, or unsafe raw SQL with a
  reachable input path.
- Medium: scoped information leak, missing abuse control with practical cost,
  unsafe import/log/cache behavior with constrained reach.
- Low/Informational: hardening, defense-in-depth, or unclear impact after the
  code path is traced.

## When Evidence Is Blocked

- State the exact missing evidence: route registration, middleware, config,
  data shape, log sample, generated URL, job payload, or test fixture.
- Do not fill gaps with assumptions from older reports.
- Recommend the smallest probe that would confirm or reject the finding.

## False-Positive Filters

- A DTO `exists` rule proves shape only; it is not authorization.
- A middleware name is not enough; confirm the route group actually applies it.
- A Resource field is not a leak unless the endpoint is reachable by the wrong
  actor or tenant.
- A raw query is not exploitable when every dynamic part is mapped through a
  fixed allowlist.

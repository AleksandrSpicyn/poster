# Auth And Runtime

Use this reference for email verification, activation, ownership claims,
password reset, OTP/SMS, tokens, config safety, queues/cache, and Octane.

## Verification And Tokens

- Email verification, activation, and ownership-claim flows use expiring signed
  URLs or random one-time tokens.
- Deterministic hashes such as `sha1(email)` are forbidden for ownership proof.
- OTPs and equivalent secrets must not be persisted in database notifications
  or logs. Store masked metadata only when audit records are needed.

## OTP And SMS

- Public OTP/SMS endpoints need effective rate limits and abuse controls before
  writes or external calls. Inspect the actual middleware and service limits;
  do not assume a particular named limiter or intent-token implementation.
- Test abuse controls and missing side effects for blocked attempts.

## Defaults And Config

- Imports/seeders must not create real users/admins with shared known passwords.
- Security-affecting config fails closed outside `local`/`testing`.

## Broadcast And Channels

- Private/presence channel authorization rechecks the current actor and
  tenant/context scope before subscription.
- Broadcast payloads expose only fields already authorized for the subscriber;
  do not include internal identifiers, tokens, secrets, or full model snapshots.

## Runtime State

- Check queue jobs and after-commit side effects for permission/scope leakage.
- Queued work stores minimal actor, tenant/context, and target identifiers; it
  reloads current state and rechecks authorization/scope in `handle()` instead
  of relying on request/auth state captured at dispatch time.
- Jobs and events avoid carrying full request payloads, tokens, secrets, or
  response bodies; persist sanitized metadata or stable identifiers instead.
- Cache keys must not mix actors, tenants, contexts, or request-scoped values.
- Under Octane, avoid mutable static caches and singletons that capture request,
  actor, tenant/context, config repository, or container state.

# Tenant Scope

Use this reference for authenticated reads/writes involving current actor,
tenant/context scope, admin access, or ownership checks.

## Hard Rules

- Authenticated read and write endpoints must prove the target entity belongs to
  the current actor/tenant/context scope before returning or mutating data.
- Current tenant/context changes require out-of-scope forbidden tests.
- Route model binding alone is not tenant authorization.
- DTO `exists` validation alone is not tenant authorization.
- Route models and contextual attributes should be passed explicitly through
  controller/action signatures.

## Admin Scope

- Inspect admin route groups and registration to verify authentication and
  authorization. Do not assume inherited middleware or a base permission exists.
- Add endpoint permissions where required by the actual access model.
- Login/bootstrap opt-outs require throttling and explicit access check before
  token issue.

## Test Expectations

- Include successful in-scope access.
- Include out-of-scope forbidden access.
- For writes, assert state is unchanged after forbidden/invalid attempts.

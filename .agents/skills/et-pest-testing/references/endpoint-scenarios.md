# Endpoint Scenario Coverage

Use this reference when adding or reviewing feature tests for HTTP routes.

## Organization

Keep endpoint scenarios focused. Follow local file grouping; no universal
one-file-per-route rule is required.

## Required Scenario Scan

For each route, check which scenarios apply:

- unauthenticated access;
- authenticated success path;
- missing base administrative access when the application defines it;
- missing endpoint-specific permission when endpoint-specific permission
  middleware exists;
- not found or outside-current-scope behavior;
- validation errors with exact invalid fields;
- exact Resource or Resource collection contract, including top-level envelope,
  required resource fields, and missing `meta`/`links` when they are not part of
  the endpoint contract;
- pagination, every declared filter, search, and all allowed sort fields for
  list endpoints;
- partial update behavior and fields that must remain unchanged;
- before/after database state and side effects for write endpoints;
- file upload/import/export endpoints need accepted-path storage/database
  assertions plus negative cases for wrong actor/scope, missing file, invalid
  type/size, and unchanged storage/database state when rejected.

## Scoped Endpoints

When an endpoint is limited by current actor/tenant/context scope, cover both
an in-scope success case and an out-of-scope forbidden/not-found case. For
forbidden writes, also assert that database state and side effects did not
change.

## False Positive Checks

- Lookup routes with only base admin access permission do not need invented
  endpoint-specific permission tests.
- A route without pagination, filters, sorting, or partial updates should not
  get placeholder tests for those concerns.
- A domain `422` is useful only when it checks the domain error scenario and
  missing write side effect where relevant.

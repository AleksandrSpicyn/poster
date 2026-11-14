# Query Input Security

Use this reference when a change touches raw SQL, dynamic filters, sorting,
search parameters, or cache keys derived from request input.

## Query Boundaries

- User-controlled filter, search, sort, include, eager-load, and pagination input
  must be allowlisted before it reaches a query builder, repository, Process, or
  search-engine query.
- Never concatenate raw request values into SQL, `orderByRaw`, `whereRaw`,
  `selectRaw`, search filter strings, or engine-specific filter/sort syntax.
- Prefer enum/value-object maps or explicit arrays from public input to internal
  column names. The public field name does not have to be the database column.
- Route model binding and DTO `exists` are not authorization. Query scope must
  still enforce current actor/tenant/context ownership where relevant.

## Sorting And Filtering

- Dynamic sort fields map to a fixed allowlist of columns or search fields.
- Dynamic sort directions accept only `asc` / `desc` after normalization.
- Optional filters must not remove tenant, active, region, permission, or
  visibility constraints.
- Search parameters that cross into an external search engine stay in the search
  module and should not be copied as raw arrays through controllers or resources.

## Cache Keys

- Cache keys derived from request input must include the tenant/context
  scope when the result is scoped.
- Do not cache user-specific or region-specific query results under global keys.
- Avoid raw payload dumps in cache keys; normalize to stable scalar values.

## Tests

- Add matching and decoy records for every new filter/sort/search branch.
- For tenant-scoped queries, include out-of-scope records and assert they do not
  appear or change.
- For dynamic sort/filter allowlists, test at least one invalid field and one
  invalid direction.

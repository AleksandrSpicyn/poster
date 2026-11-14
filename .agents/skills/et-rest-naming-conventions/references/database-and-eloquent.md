# Database and Relation Naming

- Laravel defaults use plural snake_case tables, model_id foreign keys and
  alphabetical singular names for pure pivot tables. Follow verified local
  conventions where intentionally different.
- Association entities with their own state/business identity may use normal
  domain table names; do not force pure-pivot naming onto them.
- Role/context foreign keys should express domain meaning, such as author_id.
- Choose consistent boolean names from the target schema; do not impose an
  `active`, `is_active` or `has_*` standard from another application.
- Distinguish instants from business dates/times; conventional names are *_at,
  *_date and *_time when they match the local contract.
- Use understandable provider/external-ID names consistent with existing
  integrations. Preserve externally consumed names unless migration is scoped.
- Singular relations return one related entity; plural relations return many.
  Prefer domain meaning where raw model names would be ambiguous.
- New names should be consistent before merge. Persisted renames require a
  schema migration and compatibility/deployment plan.

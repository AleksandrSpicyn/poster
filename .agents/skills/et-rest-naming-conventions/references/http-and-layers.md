# HTTP and Class Naming

- Prefer plural resource nouns for collections and coherent item parameters.
  Match route-binding names and casing to actual framework/project conventions.
- Discover route-name and URI-segment conventions before adding names. Do not
  rename existing public paths or names as incidental cleanup.
- Controllers, DTOs, Resources and permission identifiers follow established
  suffixes and namespaces. Do not require singular controllers, single-action
  controllers, a particular admin tree or permission-string grammar universally.
- Keep administrative projections distinguishable where their authorization or
  exposed fields differ from public projections.
- Use one vocabulary across routes, validation, classes, permissions and tests.
  Avoid mixing suffixes for the same kind of contract without a local reason.
- Test names describe protected behavior and follow configured discovery rules.

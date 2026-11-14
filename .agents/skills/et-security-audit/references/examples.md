# Security Review Examples

## Source-to-Sink Finding

Bad query in a hypothetical "my articles" endpoint:

```php-fragment
Article::query()->findOrFail($id);
```

This is a vulnerability only if an unauthorized actor can reach and receive or
mutate the result without a later policy/scope check. Trace routes, middleware,
query, policy and serializer before assigning severity.

Possible scoped query when the contract is owner-only:

```php-fragment
Article::query()
    ->where('user_id', $actor->getKey())
    ->findOrFail($id);
```

A policy on a bound model is another valid design. Do not impose both mechanically.
For denied writes, verify unchanged persisted values and absent file/dispatch/
external effects. For lists, include matching and foreign decoy records.

## Dynamic SQL Input

```php
namespace App\Support;

use Illuminate\Validation\ValidationException;

final class ArticleSort
{
    public static function column(string $field): string
    {
        $columns = ['title' => 'title', 'created' => 'created_at'];

        if (! array_key_exists($field, $columns)) {
            throw ValidationException::withMessages(['sort' => 'Недопустимое поле сортировки.']);
        }

        return $columns[$field];
    }
}
```

The caller also validates direction (`asc`/`desc`) and preserves base scope.
Bound values do not make arbitrary user-selected column names safe.

## Finding Format

```text
High — owner scope bypass in article download
Evidence: exact route/middleware, path:line, query and returned file path
Actor: authenticated ordinary user
Abuse: use another user's document ID to receive its private content
Fix: enforce the same ownership/policy before generating the download
Verification: permitted owner case; foreign actor denied; no download side effect
```

This is a report shape, not a claim about any repository. Distinguish a confirmed
abuse path from hardening, missing evidence or a merely stylistic concern.

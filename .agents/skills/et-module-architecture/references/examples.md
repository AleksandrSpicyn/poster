# Layered Architecture Examples

Recommended profile: `Controller → Action → Task → Repository`, public `run()`
entrypoints for Actions/Tasks, explicit route names, and Resources for stable
API projections. This is an application convention applicable to either layout.

| Class | Standard profile | Modular profile |
|---|---|---|
| StoreArticleAction | `App\Actions` | `App\Modules\Publishing\Actions` |
| CreateArticleTask | `App\Tasks` | `App\Modules\Publishing\Tasks` |
| ArticleRepository | `App\Repositories` | `App\Modules\Publishing\Repositories` |
| ArticleResource | `App\Http\Resources` | `App\Modules\Publishing\Resources` |

## Concrete Dependency Boundary

Assume `StoreArticleData` is a validated DTO and ArticleRepository implements
`createFor(User $actor, StoreArticleData $data): Article`:

```php
namespace App\Tasks;

use App\Dto\StoreArticleData;
use App\Models\Article;
use App\Models\User;
use App\Repositories\ArticleRepository;

final readonly class CreateArticleTask
{
    public function __construct(private ArticleRepository $articles) {}

    public function run(User $actor, StoreArticleData $data): Article
    {
        return $this->articles->createFor($actor, $data);
    }
}
```

The Action owns the scenario/transaction, calls this Task and returns domain
data; the Controller returns the Resource. Neither Controller nor Action reaches
around the Task to call the Repository in this profile. Local helper methods are
allowed; Task-to-Task orchestration is not the default design.

## Prepared Resource Data

```php-fragment
$articles = Article::query()
    ->where('user_id', $actor->getKey())
    ->with('author')
    ->get();
```

The query owner prepares relations. Resource code maps fields and composes
`AuthorResource`, using `whenLoaded('author')`; it must not call `load()` itself.
Keep public/admin projections separate when their exposed fields differ.

Do not import a nonexistent base Repository or `throughProcessors()` helper.
Reuse such infrastructure only if available; a custom process contract must be
shown/defined, not assumed to be a Laravel feature.

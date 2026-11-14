# Naming Examples

| Concern | Preferred example | Avoid |
|---|---|---|
| Collection | `/articles` | Action-shaped collection URI |
| Binding | `{article}` with `Article $article` | Unexplained `{id}` plus manual hidden lookup |
| Route name | `articles.store` | Unrelated verb vocabulary |
| Table / key | `articles`, `user_id` | Ambiguous abbreviations |
| Pure pivot | `article_tag` | Alias that hides the two related models |
| Relation | `author()` / `tags()` | Plural name for one model |
| Action / Task | `StoreArticleAction` / `CreateArticleTask` | Two names for the same responsibility |
| Event | `ArticlePublished` | Imperative name for a completed fact |

These are recommended concrete conventions, not a migration order for existing
public names. Association entities with their own state need not be pure pivots.

```php
use App\Http\Controllers\ArticleShowController;
use Illuminate\Support\Facades\Route;

Route::get('/articles/{article}', ArticleShowController::class)
    ->whereNumber('article')
    ->name('articles.show');
```

Assume the item controller exists. In a modular layout change its import to
`App\Modules\Publishing\Controllers\ArticleShowController`; preserve the public
contract. For UUID keys change the constraint accordingly.

Use `is_`/`has_` for new boolean concepts as a baseline. A locally adopted
visibility flag `active` remains valid; do not invent a mandatory cross-project
visibility name. Name instant/date/time columns according to their actual meaning.

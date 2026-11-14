# Pest Examples With Behavioral Assertions

Assumptions: Laravel/Pest Feature bootstrap, isolated database, User/Article
factories, `Article::author()` relation, and `articles.index` listing only the
current user's articles. These are example application contracts.

Standard path: `tests/Feature/ArticleIndexTest.php`. Modular path:
`app/Modules/Publishing/Tests/Feature/ArticleIndexTest.php`, with module imports.

```php
use App\Models\Article;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('возвращает публикации только текущего пользователя', function (): void {
    $actor = User::factory()->create();
    $other = User::factory()->create();
    $own = Article::factory()->for($actor, 'author')->create();
    $foreign = Article::factory()->for($other, 'author')->create();

    $response = actingAs($actor)->getJson(route('articles.index'));

    $response->assertOk()->assertJsonPath('data.0.id', $own->id);
    expect($response->json('data.*.id'))
        ->toBe([$own->id])
        ->not->toContain($foreign->id);
});
```

A count assertion alone would pass if the endpoint returned one wrong article.
A super-admin actor could bypass the very boundary this test intends to prove.

## Validation Must Prevent Mutation

Assume `articles.store` requires title and is_published:

```php
use App\Models\Article;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;

it('не сохраняет публикацию без заголовка', function (): void {
    $actor = User::factory()->create();

    actingAs($actor)->postJson(route('articles.store'), [
        'is_published' => false,
    ])->assertUnprocessable()->assertJsonValidationErrors('title');

    assertDatabaseCount(Article::class, 0);
});
```

For a queued/file/external effect, assert that it also did not happen. Use the
installed fake at that boundary; do not mock the internal Action to hide writes.
Recommended Pest style: separate `it` per behavior, target-language descriptions,
explicit function imports, no `$this` in tests/hooks, named routes and factory
relations. An existing adopted style can differ without invalidating the test.

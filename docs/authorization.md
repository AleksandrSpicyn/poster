# Авторизация и scope

## Доступ как отдельный контракт

Аутентификация определяет actor, авторизация — допустимое действие и данные.
Проверки формы запроса, route binding, `exists` и знание ID не дают права
читать или менять модель. Свойство `is_admin` или название middleware тоже
не доказывает, что нужная граница реально применяется.

Применяйте существующие policies, gates, permission middleware и scoped queries.
Правила одинаковы для обоих [архитектурных профилей](architecture.md); места
регистрации и namespace определяются выбранной структурой и версией Laravel.

## Actor, guard и tenant

- Найдите фактическую actor-модель, guard и источник текущего tenant/context.
- Проверьте route groups и регистрацию: общая auth-защита может наследоваться,
  но нельзя предполагать наличие base permission или общего route loader.
- Проверяйте принадлежность target и nested child нужному actor/tenant/parent.
- Не позволяйте input фильтрам, sorting/include или cache keys снять scope.
- Проверяйте и получаемые поля, и write target; Resource может раскрывать
  административные данные даже после успешного доступа к самой записи.

Публичный и административный интерфейсы могут иметь одну либо разные actor-модели
и guards. Одна роль, несколько ролей, enum permissions и синтаксис их значений —
решения приложения, а не универсальные требования.

## Другие границы

Выдача token/session допускается только после всех необходимых проверок,
включая активность и право входа. Login/OTP/reset/bootstrap требуют ограничений
злоупотребления и безопасных ошибок без ненужного раскрытия существования actor.

Files, exports, generated documents и signed URLs сохраняют ту же access boundary.
Подпись URL не заменяет корректно выбранные срок действия и аудиторию.
Private/presence channels проверяют actor и scope, payload не раскрывает лишние поля.

Queued jobs не должны зависеть от request-auth state после dispatch. Передавайте
необходимые идентификаторы и определите политику прав во время выполнения:
обычно состояние и разрешения проверяют заново; намеренное выполнение по
зафиксированному полномочию требует явного контрактного решения.

Под persistent workers не храните текущего actor/tenant в долгоживущем mutable
singleton/static state. Cache identity включает те контексты, от которых
зависит доступ и результат.

## Проверка

Тестируйте минимально разрешённого actor, отсутствие auth, отсутствие права,
чужой scope и границу вложенной сущности по применимости. Для отказавшей записи
проверяйте неизменность БД, файлов и external effects. Super-admin bypass
тестируется отдельно. Статусы `403` или скрывающий `404` выбирает контракт;
документация должна соответствовать runtime.

Дополнительно: [testing.md](testing.md), [apidoc.md](apidoc.md),
[concurrency-and-delivery.md](concurrency-and-delivery.md).

## Пример policy для Article

`app/Policies/ArticlePolicy.php` либо `Publishing/Policies/ArticlePolicy.php`:

```php
namespace App\Policies;

use App\Models\Article;
use App\Models\User;

final class ArticlePolicy
{
    public function view(User $actor, Article $article): bool
    {
        return $article->user_id === $actor->getKey();
    }

    public function update(User $actor, Article $article): bool
    {
        return $article->user_id === $actor->getKey();
    }
}
```

Если тип ключа модели отличается, приведите обе стороны к согласованному типу,
а не используйте нестрогое сравнение для маскировки несоответствия.
Модульная policy может потребовать явной регистрации:

```php-fragment
Gate::policy(
    \App\Modules\Publishing\Models\Article::class,
    \App\Modules\Publishing\Policies\ArticlePolicy::class,
);
```

Наличие класса policy не доказывает вызов. На границе изменения нужен, например,
`Gate::forUser($actor)->authorize('update', $article)` до записи или эквивалент
через Form Request / middleware. После этого проверяется HTTP-поведение.

### Ошибка scope и исправление

Плохо: `Article::findOrFail($id)` в списке «мои публикации» без дальнейшей
проверки actor. Для list query нужен фильтр владельца:

```php-fragment
Article::query()
    ->where('user_id', $actor->getKey())
    ->with('author')
    ->get();
```

Для item endpoint допустимы policy после binding или scoped lookup согласно
контракту `403`/`404`. Не считайте случайный `404` от несовпавшего route constraint
доказательством корректной авторизации.

### Тест отказа на запись

Отдельный update endpoint предполагает route `articles.update` и policy выше;
он не добавляется этим документом к минимальному store/index примеру:

```php
use App\Models\Article;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

it('не изменяет публикацию другого пользователя', function (): void {
    $actor = User::factory()->create();
    $owner = User::factory()->create();
    $article = Article::factory()->for($owner, 'author')->create(['title' => 'До']);

    actingAs($actor)->patchJson(route('articles.update', $article), [
        'title' => 'После',
    ])->assertForbidden();

    assertDatabaseHas(Article::class, [
        'id' => $article->id,
        'user_id' => $owner->id,
        'title' => 'До',
    ]);
});
```

Для API, скрывающего чужие записи, ожидается `assertNotFound()`. Выбор статуса
не отменяет проверку неизменности и отсутствия dispatch/file/external effects.

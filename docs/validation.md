# Валидация и локализованные ошибки

## Размещение в двух профилях

В стандартном Laravel Form Requests обычно находятся в `app/Http/Requests`,
custom rules — в `app/Rules`. В модульном профиле соответствующие каталоги
располагаются внутри `app/Modules/{Module}`. DTO используются, если это принятый
механизм и пакет поддерживает нужную валидацию; DTO не является обязательным
условием ни одного профиля.

Найдите фактические locale и language path. Общие сообщения и повторяющиеся
aliases держите в принятом language-файле, например `lang/{locale}/validation.php`.
Одноразовый alias или сообщение может оставаться у Form Request/DTO validator.
Не создавайте второе хранилище переводов и не меняйте локаль по примеру документа.

## Правила входных данных

- Проверяйте тип, форму, обязательность, границы, вложенность и допустимые значения.
- Различайте missing, `null`, пустое значение и default; условия должны
  соответствовать partial update и контракту клиента.
- Простые правила выражайте стандартным механизмом validator. PHP-атрибуты
  уместны только при поддержке установленным DTO-пакетом и локальными правилами.
- Contextual `exists` и uniqueness ограничивайте нужным tenant/parent scope.
  Эти проверки не заменяют policy и защиту от конкурентных изменений в БД.
- Для правил, работающих на missing/null input, проверьте implicit-поведение
  и взаимодействие с `nullable`, `sometimes` и условиями запуска.
- Не приводите внешний ввод к удобному типу до проверки, если преобразование
  скрывает ошибку. Допустимая нормализация должна быть явной частью контракта.
- Валидация формы запроса и бизнес-инвариант — разные ответственности;
  бизнес-инвариант может требовать повторной проверки внутри транзакции.

## Понятные сообщения

Используйте поддерживаемую продуктом локаль и понятные названия полей. Сообщение
объясняет, как исправить ввод, без table names, PHP-классов и внутренних
идентификаторов. Учитывайте аудиторию публичного API или административного UI.

Для массива используйте устойчивые wildcard aliases наподобие `items.*.quantity`,
если это поддерживается validator. Не создавайте вручную alias для каждого
индекса. Общий перевод правила и alias обычно предпочтительнее дублирования
полного текста в каждом validator.

## Ошибки, документация и тесты

Статус и envelope определяются фактическим обработчиком. Не превращайте любой
доменный отказ в validation `422`; сохраняйте отдельный код/статус, когда так
устроен контракт. Примеры в [apidoc.md](apidoc.md) используют настоящие поля
и реальные локализованные сообщения, а не выдуманный общий ответ.

HTTP-тест обычно проверяет статус, поля errors и отсутствие запрещённых эффектов.
Точную формулировку проверяйте отдельно, когда она является контрактом, например
для custom rule или локализации. Покройте существенные missing/null/invalid и
scope-сценарии; правила размещения — в [testing.md](testing.md).

## Пример вложенной валидации и aliases

Отдельный endpoint пакетного создания; это не дополнительные поля одиночного
`articles.store`. Стандартный путь — `app/Http/Requests/StoreArticleBatchRequest.php`,
модульный — `Publishing/Requests/StoreArticleBatchRequest.php`.

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreArticleBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.title' => ['required', 'string', 'max:200'],
            'items.*.is_published' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'items' => 'публикации',
            'items.*.title' => 'заголовок публикации',
            'items.*.is_published' => 'признак публикации',
        ];
    }
}
```

Фрагмент `lang/ru/validation.php`, если выбран русский язык:

```php
return [
    'required' => 'Заполните поле «:attribute».',
    'max' => [
        'string' => 'Поле «:attribute» не должно превышать :max символов.',
        'array' => 'Поле «:attribute» не должно содержать больше :max элементов.',
    ],
    'attributes' => [
        'title' => 'заголовок',
    ],
];
```

Это фрагмент перевода: объедините его с существующим файлом, не удаляйте
остальные типы `max`, translations и общие attributes.

### Scoped existence и авторизация

```php-fragment
Rule::exists('categories', 'id')->where('user_id', $this->user()->getAuthIdentifier());
```

Фрагмент предполагает таблицу categories и проверяет допустимый foreign ID.
Policy всё равно должна разрешать действие, а constraints/транзакция — защищать
инвариант от гонки. Не заменяйте этим проверку прав на изменение самой Article.

### Dataset для границ

Для `articles.store` из сквозного примера:

```php
use App\Models\Article;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;

it('отклоняет некорректный заголовок', function (mixed $title): void {
    $actor = User::factory()->create();

    actingAs($actor)->postJson(route('articles.store'), [
        'title' => $title,
        'is_published' => false,
    ])->assertUnprocessable()->assertJsonValidationErrors('title');

    assertDatabaseCount(Article::class, 0);
})->with([
    'null' => [null],
    'пустая строка' => [''],
    'превышение длины' => [str_repeat('a', 201)],
]);
```

Точный текст перевода проверяется отдельным localization/custom-rule case,
если он является предметом изменения; endpoint case сохраняет устойчивые
assertions на поля и отсутствие записи.

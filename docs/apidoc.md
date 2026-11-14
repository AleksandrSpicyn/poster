# Документация API

## 1. Назначение

Документация описывает действующий HTTP-контракт: вход, доступ, успешные формы,
ошибки и ограничения. Исходными данными служат route, middleware, validator,
операция, serializer и exception handler. Пример соседнего endpoint не является
доказательством одинакового поведения.

Правила применимы к стандартному и модульному профилям из
[architecture.md](architecture.md). Ни каталог контроллера, ни наличие DTO не
меняют требований к контракту.

## 2. Обнаружение endpoint в двух профилях

| Источник | Стандартный Laravel | Модульный Laravel |
|---|---|---|
| Routes | `routes/*.php` и bootstrap | `{Module}/Routes/*.php` и действующий loader |
| Controller | `app/Http/Controllers` | `app/Modules/{Module}/Controllers` |
| Validator | `app/Http/Requests` либо принятый DTO/validator | `{Module}/Requests` или `{Module}/Dto` |
| Resource | `app/Http/Resources` | `{Module}/Resources` |
| Feature-тест | `tests/Feature` | `{Module}/Tests/Feature` |

В сокращённых путях `{Module}` означает корень доменного модуля.
Проверьте фактическую регистрацию маршрутов, prefixes, middleware и выборку
маршрутов генератором. Не добавляйте второй scanner только из-за модульного пути.
Для административного API учитывайте выбранную admin-структуру и реальную auth-схему.

## 3. Генератор и версия

Перед изменением аннотаций определите установленный пакет, версию, конфигурацию,
custom strategies и способ генерации. Этот документ использует терминологию
Scribe, но общие требования применимы и к другим инструментам.

Scribe поддерживает docblocks и PHP-атрибуты; конкретный синтаксис сверяйте с
[официальной справкой аннотаций](https://scribe.knuckles.wtf/laravel/reference/annotations)
для установленной версии. Выберите принятую форму и избегайте противоречащих
друг другу источников одного параметра.

Не переносите собственную грамматику приложения как стандарт Scribe.
Дополнительные `required`, `deprecated`, `format`, nullable/union types,
`Enum:`, wildcard-пути и правила приоритета примеров требуют проверки поддержки
в соответствующем месте аннотации и в сгенерированном OpenAPI.

## 4. Описание endpoint и входных данных

Для каждого документируемого endpoint укажите назначение, группу и при
необходимости подгруппу. Язык и словарь выбирает проект.

Опишите фактическую аутентификацию: session/cookie, bearer, OAuth или другую
схему. Не требуйте bearer для административного API, использующего session.
Укажите необходимые headers, path/query/body параметры и content type.

Для каждого входного поля проверьте:

- точное имя, место передачи, тип и структуру
- обязательность, возможность пропуска и `null`
- границы, форматы, допустимые значения, default — если они реально применяются
- scope идентификаторов, ограничения binding и nested ownership
- корректный пример без секретов или настоящих клиентских данных

Не выводите машинные ограничения только из человеческой фразы. Сопоставьте их
с правилами validation и проверьте представление в schema. Фактический timestamp
нельзя объявлять RFC 3339 `date-time`, если его wire-format другой.
Для файлов укажите размер, тип и multipart-поля. Inline file example может
трактоваться как путь к файлу: используйте поддерживаемое исключение примера
или безопасный fixture, а не произвольную строку.

## 5. Успешные ответы

Документируйте фактические статус, headers, envelope и тело. Не добавляйте
`data`, `success`, `message`, `links`, `meta` только потому, что они есть в примере.
`201` и `204` описываются только при соответствующем runtime-поведении;
ответ без тела не получает выдуманный пустой JSON.

Для каждой значимо отличающейся формы приведите полный сценарий ответа.
Одни значения при одинаковой схеме не требуют отдельных сценариев.
Пример формата Scribe ниже иллюстрирует условный контракт, не задавая его всем API:

```text
@response 200 scenario="Элемент найден" {"data":{"id":7,"title":"Пример"}}
```

Инвентаризируйте верхние, вложенные и условные поля всех достижимых форм:
типы wire-значений, контейнеры, элементы списков, пагинацию, enum и условия.
Отсутствующий ключ и ключ со значением `null` — разные контракты. Нельзя объявлять
поле обязательным во всех сценариях, если оно появляется только при загрузке связи.

Следуйте реальному пути загрузки и сериализации, независимо от количества слоёв.
Для `whenLoaded`, `mergeWhen`, pivot/count fields и аналогов проверяйте,
может ли именно этот endpoint достичь соответствующей ветки.

Используйте поддерживаемые `@responseField` или schema overrides. Не считайте,
что пример гарантирует полную схему или что генератор автоматически выведет все
родительские объекты, ограничения и nullable-варианты. Если выбранный инструмент
не выражает variant/union, используйте его механизм явного описания schema.

## 6. Ошибки и доступ

Опишите достижимые authentication, authorization, binding, validation и domain
ошибки. Один статус может иметь разные реальные тела — разделяйте сценарии.
Не создавайте искусственный `403`, `409` или `422` ради заполнения списка.

Application error code описывается только если возвращается. Его реестр и форма
блока в docblock — соглашение приложения, а не требование пакета. Не включайте
debug stack trace и внутренние сведения из non-production ответов.

Validation-пример использует настоящее поле и фактическое сообщение нужной
локали. Доменный отказ не превращается во validation error только ради `422`.
Unmatched route не является ответом контроллера; общий routing error описывается
на уровне API, если входит в контракт.

Схемы успешных ответов не должны ошибочно накладываться на error bodies.
Проверьте это в экспорте генератора, особенно при custom strategies.

## 7. Файлы, streams и генерация примеров

Файл или stream имеет отдельный контракт: статус, content type, disposition,
доступ и формат содержимого. JSON-обёртка ему не назначается автоматически.
Response files хранятся по согласованным путям и не содержат приватных данных.

Response calls способны выполнять запись и внешние обращения. Проверьте,
какие методы и маршруты они вызывают, используемую БД, actor и настройки
интеграций. Используйте изолированное окружение или явные примеры. Не предполагайте,
что у всех проектов генерация разрешена только для GET или безопасна по умолчанию.

## 8. Проверка результата

1. Сверьте вход, доступ, response variants и ошибки с кодом и тестами.
2. Выполните документированную команду генерации для установленной версии.
3. Проверьте наличие всех ожидаемых маршрутов и отсутствие закрытых служебных API.
4. Откройте HTML и экспорт OpenAPI: типы, item schema, required/nullable,
   enum/default/example, security, статусы, files и variant responses.
5. При изменении контракта выполните соответствующие route-level тесты из
   [testing.md](testing.md). Поиск подстрок в docblock не доказывает HTTP-поведение.

Правила переносов и длины строк выбираются проектом с учётом parser; не
разрывайте аннотацию способом, который меняет её смысл. Не редактируйте generated
output вместо его источника, если генерация является принятым workflow.

## 9. Полный docblock создания ресурса

Пример продолжает `articles.store` из [architecture.md](architecture.md).
Показывает стандартную грамматику Scribe 5.x: входной `user_id` запрещён,
`author` присутствует в этом endpoint благодаря явной загрузке связи.
Успешный JSON не получает выдуманные поля `success` и `message`.

```php
/**
 * Создать публикацию
 *
 * Сохраняет публикацию текущего пользователя и возвращает её с автором.
 * Назначить владельца через тело запроса нельзя.
 *
 * @group Публикации
 * @authenticated
 * @header Accept application/json
 * @header Content-Type application/json
 * @bodyParam title string required Заголовок, не более 200 символов. Example: Переносимый пример
 * @bodyParam is_published boolean required Признак публикации. Example: false
 * @response 201 scenario="Публикация создана" {
 *   "data": {
 *     "id": 7,
 *     "title": "Переносимый пример",
 *     "is_published": false,
 *     "author": {"id": 3, "name": "Автор"}
 *   }
 * }
 * @responseField data object Созданная публикация.
 * @responseField data.id integer Идентификатор публикации.
 * @responseField data.title string Заголовок.
 * @responseField data.is_published boolean Признак публикации.
 * @responseField data.author object Автор публикации.
 * @responseField data.author.id integer Идентификатор автора.
 * @responseField data.author.name string Имя автора.
 */
```

Authentication в сквозном примере зависит от guard приложения. Для bearer API
дополнительно укажите `@header Authorization Bearer {ACCESS_TOKEN}` и настройте
auth scheme генератора; для session API опишите cookie/CSRF по его контракту.
`@header` имеет собственную грамматику; не вставляйте туда автоматически
`string required`, взятые из `@bodyParam`.

Ошибки `401`/`422` добавляются с фактическим телом и локалью. Например, только
если exception handler действительно возвращает следующий формат:

```php
/**
 * @response 422 scenario="Не указан заголовок" {
 *   "message": "Укажите заголовок.",
 *   "errors": {"title": ["Укажите заголовок."]}
 * }
 * @response 401 scenario="Требуется вход" {"message":"Unauthenticated."}
 */
```

Это условный пример error handler, не обещание стандартного перевода Laravel.
Тексты и status проверяются через реальные запросы целевого приложения.

## 10. Query, вложенные массивы, файлы и response files

Ниже отдельный пример bulk endpoint, не дополнительный input `articles.store`:

```php
/**
 * @urlParam article integer required Идентификатор публикации. Example: 7
 * @queryParam page integer Номер страницы. Example: 1
 * @queryParam per_page integer Размер страницы, от 1 до 100. Example: 20
 * @bodyParam items object[] required Элементы пакетной операции.
 * @bodyParam items[].title string required Заголовок элемента. Example: Пример
 * @bodyParam items[].status string required Статус. Enum: draft, published. Example: draft
 * @bodyParam attachment file Вложение PDF до 5 МБ. No-example
 * @responseFile 200 scenario="Пакет обработан" docs/responses/articles-bulk.json
 */
```

Response-file путь здесь обозначает файл, который должен быть создан в целевом
приложении из его реального ответа. Это не ссылка на существующий asset ai-init.
Не копируйте такой тег без файла. `No-example` не является заменой validation
файла; validation MIME/size и access проверяются кодом и тестами.

Примеры `Enum:`/`Example:` должны согласовываться. Закрытый enum не выводится
из одного `message`, `true`, `0` или `null` в response scenario.

### JSON schema: что не потерять

| Значение | Что зафиксировать |
|---|---|
| `[]` | Список, тип items если он известен; не объект |
| `{}` | Объект, известные свойства или разрешённые дополнительные ключи |
| `null` | Возможность null отдельно от отсутствия ключа |
| `"12.50"` | Строка, даже если по смыслу деньги |
| `12.5` | JSON number; не обещать точную decimal arithmetic |
| Условный `author` | Когда ключ присутствует и какой тип имеет значение |
| Paginated data | Настоящие `data`, `links`, `meta`, а не сокращённая догадка |

Сначала соберите union всех достижимых полей, затем сопоставьте варианты
конкретным response scenarios. Условное поле `whenLoaded` не входит в schema
endpoint, который никогда не загружает эту связь. `whenNull` и `MissingValue`
нельзя считать одинаковым результатом.

## 11. Расширенная грамматика: отдельный переносимый контракт

Если генератор приложения поддерживает schema modifiers через custom strategy,
следующий профиль можно сохранить как явно выбранное расширение. Таблица
описывает требуемую семантику расширения, а не встроенные гарантии Scribe.
Без реализованного parser/strategy используйте поддерживаемые attributes,
validator extraction или явную OpenAPI schema.

```text
@<tag> <path> <type> [required] [deprecated] [modifiers...] <description>
    [Enum: <values>] [Example: <value> | No-example]
```

| Modifier | Тип | Результат в schema |
|---|---|---|
| `min`, `max` | integer/number | minimum/maximum |
| `min`, `max` | string | minLength/maxLength |
| `min`, `max` | array | minItems/maxItems |
| `gt`, `gte`, `lt`, `lte` | integer/number | exclusive/inclusive bounds по версии OpenAPI |
| `pattern` | string | pattern |
| `format` | подходящий scalar | format |
| `uniqueItems` | array | uniqueItems |
| `default` | совместимый scalar | default |

Для такого расширения заранее задайте правила:

1. Modifiers идут до описания; неизвестные, повторные или несовместимые значения
   вызывают ошибку генерации, а не молча исчезают.
2. Typed arrays сохраняют item type; container example не кодируется строкой.
3. Nullable и optional различаются; поддержку `type|null` проверяйте отдельно.
4. Explicit example имеет явный приоритет; default/example проходят проверку
   типа и enum. Не генерируйте случайные response values вместо сценария.
5. Даты `Y-m-d H:i:s` получают реальный pattern; их нельзя объявлять RFC 3339.
6. Success schema metadata не накладывается на error response того же пути поля.

Пример только для установленного расширения:

```text
@queryParam per_page integer min=1 max=100 default=20 Размер страницы. Example: 20
@bodyParam displayed_at string|null format="Y-m-d H:i:s" Время показа.
```

Таким образом полезная таблица ограничений остаётся в руководстве, но не
заставляет новый проект зависеть от чужого parser implementation.

## 12. Проверка docblock против HTTP-контракта

```php
use App\Models\User;

use function Pest\Laravel\actingAs;

it('возвращает документированную форму созданной публикации', function (): void {
    $actor = User::factory()->create();

    actingAs($actor)->postJson(route('articles.store'), [
        'title' => 'Документированный пример',
        'is_published' => true,
    ])->assertCreated()
        ->assertJsonPath('data.title', 'Документированный пример')
        ->assertJsonPath('data.is_published', true)
        ->assertJsonPath('data.author.id', $actor->id)
        ->assertJsonMissingPath('data.author.email');
});
```

Это иллюстрация тех же assertions, а не требование дублировать уже существующий
ArticleStoreTest. Дополнительно проверяются generated schema и response examples;
Feature-тест сам по себе не подтверждает корректность работы документационного parser.

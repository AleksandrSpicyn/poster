# Стиль PHP и проверки качества

## Выбор соглашений

Один стиль применяется ко всему затронутому коду независимо от стандартного или
модульного размещения. Исходные правила — конфигурация formatter, ограничения
PHP, static analysis и локальные инструкции. Примеры из другого репозитория
не устанавливают порядок импортов, кавычки и пустые строки автоматически.

Для нового проекта выберите поддерживаемый стандарт и закрепите его инструментом.
Для существующего проекта следуйте его настройкам; не обновляйте зависимости
и не расширяйте scope форматирования ради принятия этого документа.

## Типы и контракты

- Добавляйте понятные native types там, где они совместимы с framework и parent API.
- Nullable, union/intersection types и `mixed` выбираются по реальным значениям,
  доступному PHP и внешнему контракту, а не для подавления анализа.
- PHPDoc дополняет сигнатуру: array shapes, generics, callable/iterable types,
  существенные исключения и неочевидные ограничения.
- Не повторяйте очевидную сигнатуру бессодержательным PHPDoc.
- Стабильные большие payload могут потребовать DTO/Resource/value object;
  локальному массиву из нескольких значений отдельный класс нужен не всегда.
- Backed values enum и сериализованные имена сохраняют внешний контракт.

## Импорты

Имена классов импортируются через `use`, в том числе для PHPDoc. Полные namespace
в сигнатурах, выражениях и аннотациях не используются. Rector автоматически
добавляет импорты; Pint сортирует их и удаляет неиспользуемые.

## Читаемость

Называйте переменные по смыслу. Делите сложную логику по ответственности,
избегая one-line wrappers без пользы. Циклы подходят для эффектов и раннего
выхода, collections — для понятных преобразований; механическое преобразование
одного в другое не является улучшением.

Комментарии объясняют инвариант, внешнее ограничение или workaround.
Важные условия делайте явными. Предпочитайте сравнения с понятной семантикой
и избегайте неявного coercion, когда он скрывает ошибку.

Enum case style, unused-parameter naming, nullable notation, tag order,
кавычки и переносы закрепляются локально. Не считайте индивидуальные настройки
Pint или команды предпочтениями всех PHP-проектов.

## Проверки

Найдите formatter, static analyzer и test runner из manifests, CI и task scripts.
Pint, PHPStan/Larastan и Rector — возможные инструменты, а не обязательный набор.
Проверьте версии и flags. Используйте существующий wrapper, если он включает
контейнер или нужную среду.

Перед fixer прочитайте diff и сохраните чужие изменения. На code review предпочтителен
check mode. После автоправок просмотрите итог: нельзя скрывать изменение поведения
за форматированием. Modernization через Rector требует того же контроля.

Проверки запускаются по риску и правилам проекта. Для документов без PHP-изменений
обычно достаточно проверки содержимого и ссылок. Статический успех не заменяет
[тесты поведения](testing.md), CI или runtime verification.

## Рекомендуемый готовый стиль

Для нового проекта этот набор можно принять без привязки к приложению:

- короткие массивы `[]`, одинарные кавычки без интерполяции
- строгие сравнения `===`/`!==`, логические `&&`/`||`
- boolean operators в начале продолженной строки
- фигурные скобки у всех control structures
- trailing comma у многострочных arrays/parameters/match
- blank line перед return/throw/continue/break и самостоятельными control blocks
- явные типы параметров, свойств и результата; constructor property promotion
- `?Type` для простого nullable в сигнатуре, `Type|null` в PHPDoc
- enum cases в `UPPER_SNAKE_CASE`, backed values по контракту
- PHPDoc tags в порядке `@param`, `@return`, `@throws`
- без ведущего `_` у неиспользуемых framework callback parameters

Закрепите эти настройки formatter и static analysis. Существующий formatter
может выбирать другие переносы: не объявляйте форматирование дефектом без
принятого правила, но и не удаляйте сами правила из starter-набора.

### Типизированный контракт вместо неясного массива

```php
namespace App\Dto;

final readonly class ArticleFilter
{
    public function __construct(
        public ?bool $published,
        public int $perPage = 20,
    ) {}
}
```

### Условие, early exit и array shape

```php
namespace App\Support;

final class PublicationSummary
{
    /** @return array{title: string, is_published: bool} */
    public static function make(string $title, bool $published): array
    {
        if ($title === '') {
            return ['title' => 'Без заголовка', 'is_published' => false];
        }

        return ['title' => $title, 'is_published' => $published];
    }
}
```

### Enum без выдуманного helper trait

```php
namespace App\Enums;

enum PublicationStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Черновик',
            self::PUBLISHED => 'Опубликовано',
        };
    }
}
```

Если у приложения есть shared enum helper, используйте его вместо копирования
метода в каждом enum. Не импортируйте отсутствующий trait из другого проекта.
Статус здесь — отдельный вариант модели; он не добавляется автоматически к
сквозному Article с boolean `is_published`.

# Журналирование изменений

## Контракт журнала

Определите, какие изменения журналируются, кому доступен журнал, срок хранения
и гарантии сохранения. Audit trail изменений и технический application log —
разные инструменты. Чтение или неуспешный доступ журналируется только при
соответствующем требовании, а не автоматически рядом с каждой операцией.

Правила подходят обоим [архитектурным профилям](architecture.md). Конкретные
traits, observers, builders, context wrappers и relation APIs определяются
установленным пакетом и реализацией приложения. Их наличие нельзя предполагать.

## Покрытие записи

Сначала проследите mutation: model events, relation/pivot change, bulk update,
upsert, import, queued job, file replacement или прямой SQL. Проверьте, какой
механизм уже пишет запись. Не добавляйте ручной дубль к автоматическому событию.

Bulk SQL и sync могут иметь другую событийную семантику. Если используемый путь
обходит журнал, выберите поддерживаемый механизм или явное получение
нормализованных before/after данных. Сохраняйте доменные/package методы и их
validation, events, cache и cleanup effects.

Для composite upsert охватите все компоненты business key и фактически затронутые
строки. Scope первого столбца недостаточен. Большие snapshots должны быть
ограничены нужными записями и согласованы с транзакцией/конкурентными writes.

## Содержание записи

- Субъект изменения и при необходимости parent aggregate, child/relation identity
- Тип события и существенный нормализованный diff
- Actor, источник операции, tenant/context и correlation ID по контракту
- Время и безопасные metadata, объясняющие причину

Подавляйте no-op записи, если журнал отражает изменения состояния. Различайте
отсутствующее значение, `null`, delete и detach. У родительского агрегирования
должна оставаться понятна изменённая дочерняя сущность.

Пароли, токены, OTP и другие секреты не попадают в diff. Для чувствительного
изменения можно использовать безопасный marker без значения. Остальные данные
классифицируются по политике доступа и хранения; журнал сам нуждается в scope.

## Транзакции и контекст

Установите, записывается ли audit в одной транзакции с mutation или отдельным
durable channel. В первом случае rollback убирает обе записи; отдельный канал
должен отличать попытку от подтверждённого изменения. Не утверждайте commit,
если операция впоследствии откатилась.

Системные операции получают явную атрибуцию, если её не обеспечивает внешняя
граница. Не создавайте повторные context wrappers и не оставляйте actor/source
в static/singleton state между запросами или jobs. Для retries определите
идемпотентность, чтобы одинаковая обработка не выглядела новым изменением.

## Проверка

В [integration-тесте](testing.md) установите baseline после фабрик. Проверьте
изменение, subject, actor/source, diff, no-op, отказ/rollback и важный bulk/relation
путь. Не дублируйте общий пакетный набор тестов в каждом модуле.

## Выбор write path: прикладная таблица

| Операция | Проверить | Как действовать |
|---|---|---|
| `create/save/update/delete` модели | Подключены ли events/trait/observer | Использовать автоматическую запись, не дублировать |
| Pivot/child change | Кто subject: child или aggregate | Сохранить relation/child identity и нужный diff |
| Many-to-many sync | Есть ли штатный logging hook | Сохранить package sync и сравнить normalized state |
| Bulk update / upsert | Обход событий и полный unique key | Scoped snapshot или существующий bulk audit API |
| Media replacement | Побочные эффекты библиотеки | Оставить library orchestration и audit итог |
| No-op | Изменились ли значимые значения | Не создавать запись изменения без diff |
| Техническая модель | Должна ли исключаться везде | Исключить явно, не маскировать бизнес-операции |

### Package API и собственный audit API — разные примеры

Пример ручного бизнес-события для Spatie Activitylog v5, когда это событие
не журналируется автоматически как обычный model update:

```php-fragment
activity()
    ->performedOn($article)
    ->causedBy($actor)
    ->withProperties(['source' => 'console', 'correlation_id' => $correlationId])
    ->event('publication_exported')
    ->log('Выполнен экспорт публикации');
```

Это metadata бизнес-события, не model attribute diff. В v5 пользовательские
`properties` отделены от `attribute_changes`; не переносите внутреннюю схему
другой версии. См. [Activitylog v5 logging](https://spatie.be/docs/laravel-activitylog/v5/basic-usage/logging-activity).

Если приложение имеет свой audit abstraction, его можно описать без ссылки на
чужие классы, задав контракт явно:

```php
namespace App\Contracts;

use Illuminate\Database\Eloquent\Model;

interface AuditWriter
{
    /**
     * @param array<string, scalar|null> $before
     * @param array<string, scalar|null> $after
     */
    public function record(
        Model $subject,
        Model $actor,
        array $before,
        array $after,
        string $source,
    ): void;
}
```

Это проектируемый interface, не существующий Laravel/Spatie API. Реализация
определяет schema, transaction behavior, normalization, no-op и sensitive fields.
Не создавайте этот wrapper, если интеграция уже имеет подходящий API.

### Snapshot без нарушения пакетного write path

```php-fragment
$before = $article->tags()->orderBy('tags.id')->pluck('tags.id')->all();
$article->tags()->sync($validatedTagIds);
$after = $article->tags()->orderBy('tags.id')->pluck('tags.id')->all();
```

Фрагмент предполагает отношение `tags` и разрешённые ID. Отсортированный набор
сравнивается по контракту relation, а не по случайному порядку SQL. Audit пишется
только если path ещё не покрыт автоматическим механизмом; в транзакции snapshot
должен быть согласован с конкурирующими изменениями. Сам `sync()` не обещает
универсального журналирования.

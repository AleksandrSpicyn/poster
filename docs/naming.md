# Соглашения по именованию

## Общий словарь

Используйте один доменный термин в маршрутах, классах, данных, правах и тестах.
До нового имени проверьте соседние артефакты, внешние контракты и ограничения
фреймворка. Новое имя проще согласовать до публикации, чем мигрировать позже.

В стандартном профиле классы группируются по назначению, в модульном — сначала
по домену; см. [architecture.md](architecture.md). Изменение профиля не должно
случайно менять URI, таблицы, permission values и сериализованные имена классов.

## HTTP и классы

- Для collection URI предпочтительны существительные во множественном числе.
  Стиль route names и сегментов определяется приложением.
- Route parameter согласуется с binding и аргументом контроллера; не меняйте
  имя параметра, не проверив route generation и consumers.
- Классы используют понятный доменный термин и принятый suffix: Controller,
  Request, Resource, Policy, Rule, Data/Dto, Action и другие реальные роли.
- Resource controller и single-action controller оба допустимы; способ
  именования должен соответствовать выбранному способу маршрутизации.
- DTO suffix и namespace должны быть последовательными, без смешения разных
  обозначений одного контракта внутри одной области.

## Таблицы, колонки и отношения

Типичные Laravel-имена: plural snake_case для таблиц, `model_id` для foreign key,
алфавитный порядок singular имён для чистой pivot-таблицы. Явная конфигурация
модели позволяет другие схемы; сохраняйте принятые договорённости.

Association entity с собственным состоянием и бизнес-идентичностью может иметь
обычную доменную таблицу. Не применяйте к ней автоматически правило pure pivot.
Ролевой foreign key, например `author_id`, должен отражать смысл отношения.

Выберите последовательные boolean names. `active`, `is_active` и другие формы
не являются универсальным обязательным стандартом. Различайте момент времени,
бизнес-дату и время дня; `*_at`, `*_date`, `*_time` полезны, когда соответствуют
семантике и локальной схеме.

Singular relation возвращает одну сущность, plural — набор. External identifiers
должны быть различимы по назначению/провайдеру и совместимы с действующими imports.

## События и supporting artifacts

Событие обычно называет произошедший факт, job/listener — выполняемое действие.
Policies, factories и seeders именуются по владельцу. Command signature отражает
действие и ресурс, сохраняя принятые CLI names. Test name объясняет поведение,
filename соответствует discovery и локальной организации.

Config paths используют dot notation; segments и translation keys следуют
локальному casing. Переменные окружения обычно используют UPPER_SNAKE_CASE.
Enum cases и permission-string grammar определяет команда, а значения публичных
контрактов нельзя менять только ради единообразия.

## Безопасное переименование

Проверьте миграции, indexes, route names, bindings, полиморфные типы,
queue/serialized payload, cache keys, интеграции, тесты и документацию.
Для persisted/public contracts нужен план совместимости, а не только rename IDE.

## Готовые примеры имён

| Артефакт | Пример | Почему |
|---|---|---|
| Collection URI | `/articles` | Ресурс во множественном числе |
| Route binding | `/articles/{article}` | Согласован с `Article $article` |
| Route name | `articles.store` | Ресурс и действие |
| Составной route name | `article_comments.index` | Единый snake_case vocabulary |
| Controller | `ArticleStoreController` | Один HTTP-сценарий |
| DTO | `StoreArticleData` | Вход конкретного use case |
| Action / Task | `StoreArticleAction` / `CreateArticleTask` | Бизнес-сценарий / атомарная запись |
| Table / foreign key | `articles` / `user_id` | Framework-friendly имена |
| Pure pivot | `article_tag` | Два singular имени в алфавитном порядке |
| Relation | `author()` / `tags()` | Один объект / множество |
| Event / listener | `ArticlePublished` / `SendPublicationNotice` | Факт / действие |
| Command signature | `articles:archive` | Ресурс и операция |
| Test | `ArticleStoreTest` | Быстрый поиск HTTP-сценария |

REST action names — это соглашение; route names не вычисляются автоматически
из class name. В обоих архитектурных профилях публичное имя можно сохранить.

```php
use App\Http\Controllers\ArticleShowController;
use Illuminate\Support\Facades\Route;

Route::get('/articles/{article}', ArticleShowController::class)
    ->whereNumber('article')
    ->name('articles.show');
```

Это отдельный пример item route: `ArticleShowController` должен существовать
в целевом приложении. Для UUID заменяется constraint, а не смысл имени параметра.
В модульном профиле меняется import на модульный Controller, не URI.

Плохо: `getArticlesData()` у контроллера, который одновременно валидирует,
сохраняет и сериализует; `relatedEntities()` для понятного отношения `author`;
`group_members` для чистой pivot без собственной бизнес-семантики.
Association entity `memberships` с состоянием — уже другой случай и не обязана
следовать pure-pivot шаблону.

# Poster — инструкции агентам

## Контекст

Poster — Laravel + Vue/Inertia: публичный профиль, кабинет, генерация коротких
видео (prompt → image/video/audio → FFmpeg), публикация и уведомления.
Обзор и запуск: [README.md](README.md).

## Правила

- Применяется стандартный Laravel-профиль: `app/`, `database/`, `routes/`, `tests/`.
  Существующие Actions координируют сценарии, Tasks выполняют отдельные операции.
  Сохраняйте `run()`; не добавляйте слои и интерфейсы без реальной ответственности.
- Перед работой проверьте Git status/diff. Сохраняйте чужие изменения.
  Commit, push, публикация, merge и deploy требуют явного поручения.
- Читайте [docs/general.md](docs/general.md), [docs/architecture.md](docs/architecture.md),
  [docs/code-style.md](docs/code-style.md) и документы затронутой области.
- Для навигации по зависимостям используйте локальный граф Graphify в `graphify-out/`
  (`graph.json`, `graph.html`, `GRAPH_REPORT.md`); создание — `make graph`, обновление —
  `make graph-update`. Команды и ограничения: [docs/project-graph.md](docs/project-graph.md).
  Граф помогает искать код; DI, routes, events/jobs и Eloquent проверяйте по исходникам и тестам.
- Скиллы ai-init находятся в `.agents/skills/et-*/SKILL.md`; источник и commit
  записаны в `docs/ai-init-source.json`. Читайте применимый скилл и его references.
- Pest: отдельный Feature-файл для каждого HTTP method + named route; Unit-файл
  для одного класса. Без `$this`, Laravel/Pest helpers импортируются через `use function`.
  Общие helpers — только при повторном использовании в нескольких файлах;
  локальные helpers остаются рядом со сценариями. Все запросы используют `route()`.
  Подготовку/fakes, вызов и проверки разделяйте пустыми строками; цепочки из
  нескольких методов записывайте с новым методом на отдельной строке.
- Pest: короткие русские `test()`, функциональные проверки через реальные routes,
  factories/relations, внешние API через fakes. Не подменяйте внутренние слои,
  когда проверяете полный сценарий. Проверяйте payload, persistence и эффекты.
- PHP: native types, точные generics/array shapes, early return, collection-first
  для преобразований, без ведущего `_` у callback parameters. Сохраняйте контракты.
- HTTP-сценарий: Controller → Action → Task. Контроллер не вызывает Task напрямую,
  не валидирует inline и не меняет модели/сессии. Laravel Data DTO валидирует ввод
  и передаётся в Action; Action координирует сценарий, Task выполняет атомарную операцию. Persistence
  остаётся в Tasks с Eloquent; отдельный Repository-слой здесь не введён.
- Повторяющиеся наборы значений оформляйте enum; все PHP enum обязаны использовать
  `App\Traits\EnumTrait`. В DTO используйте enum-типы, на границах — backed values.
- Валидация DTO преимущественно в атрибутах Laravel Data; `rules()` — только для
  ограничений, которые нельзя выразить штатными атрибутами без custom Rule.
- Контроллеры содержат один `__invoke`; общие URI-сегменты выносите в `Route::prefix()`.
  Текущий пользователь — `#[CurrentUser]`, выбор guard — `#[Auth]`, конфигурация —
  `#[Config]`; зависимости передавайте через DI там, где метод вызывает контейнер.
- Внутри групп `use` нет пустых строк; между imports классов и функций — пустая строка. После `declare`,
  `namespace` и всего блока imports оставляйте пустую строку.
- Имена классов импортируйте через `use`, включая PHPDoc; не пишите полные namespace в коде.
- Не подавляйте PHPStan blanket ignores/baseline для достижения максимального уровня.
- В тестах запрещены реальные платные API, публикация и уведомления. Не используйте
  рабочую БД; тестовая конфигурация из `phpunit.xml` изолирована.
- Docker Compose проекта изолирован от B2B. Не останавливайте чужие контейнеры,
  не удаляйте volumes. Не переносите секреты и инфраструктурные адреса B2B.
- Проверки и ограничения сообщайте по факту: локальные тесты не доказывают CI/deploy.

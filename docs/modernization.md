# Модернизация Poster

## Исходное состояние

Дата начала: 2026-11-14. Laravel 12, PHP ^8.2, Vue 3/Inertia 2/Vite 6,
PHPUnit 11. До начала работ: main ahead 6; staged удаления двух документов
`docs/superpowers/` принадлежат исходному состоянию и сохранены.

## Этапы и критерии готовности

1. ai-init: документация и десять скиллов установлены, источник закреплён;
   README и AGENTS описывают действующий проект
2. Docker: отдельные PostgreSQL/Redis, PHP/FFmpeg/Node/Nginx, сборка и HTTP-проверка
3. Поведение: auth/settings/pages, списки, генераторы, webhooks, сборка,
   jobs/events/listeners, публикация, команды; success/failure и побочные эффекты
4. Зависимости: последние стабильные совместимые версии, проверка конфликтов,
   upgrade guide, обновлённые lockfiles и frontend build
5. Инструменты: Horizon, Scribe/MCP, Boost, Pest, Rector, Larastan;
   запуск каждого инструмента
6. Boost: установка инструкций/скиллов/MCP для проекта
7. Pest: все прикладные тесты в Pest, без бессодержательных example-тестов
8. PHPStan: level max, без baseline и подавления реальных ошибок
9. Рефакторинг: подтверждённые сценарии, понятные типы/границы,
   защита от повторов и ошибок, отсутствие лишних абстракций
10. APIdoc: реальные входы/ответы всех контроллеров, генерация OpenAPI,
    проверка MCP tools initialize/list/search/get

## Ограничения проверки

TikTok/VK пока заглушки. Внешние API в автоматических проверках подменяются
на границе интеграции: реальные генерация/публикация требуют ключей и отдельной
проверки. Локальный результат не означает deployment/CI/production.

## Выполнено

- Установлены ai-init docs и 10 project-local skills из закреплённого commit
- Созданы README.md и AGENTS.md, сохранён стандартный профиль приложения
- Добавлены изолированный Compose и команды Make; проверен compose config

- Docker image собран; PHP-FPM работает от appuser, FFmpeg доступен
- Compose app/web/db/redis запущен, healthchecks БД и Redis успешны
- Frontend до обновления собран; GET / через Nginx вернул HTTP 200
- Тестовая среда: удалён env_file из Compose, чтобы `.env` не перекрывал phpunit
  через process environment; 43 Pest-сценария прошли на SQLite in-memory

Остальные этапы находятся в работе; итоги проверок дополняются по мере выполнения.

### Тестовая база до обновления Laravel

69 сценариев / 301 assertions на Laravel 12, Pest 4: auth/settings/homepage,
фильтры/пагинация, Suno callbacks, генерация и fallback, polling/jobs/listeners,
Telegram payload, Instagram success/failure, реальная сборка FFmpeg с проверкой
видео- и аудиопотока через ffprobe. Исходные PHPUnit-тесты переведены на Pest.
Дополнительные edge cases и команды проверяются далее; это не заявление о
100% покрытии строк. HTTP stray requests запрещены, БД — SQLite in-memory.

В первом тестовом коммите проверка Instagram HTTP callback обращалась к полю
чужого запроса; исправлено фильтром URL, полный повторный прогон: 69 passed.

### Обновление зависимостей

Laravel 13.42.0, Inertia Laravel 3.7 / Vue 3.11, OpenAI Laravel 0.22,
Telegram 8; все прямые Composer-зависимости обновлены. Pest 5.3.5 ограничивает
PHPUnit версией 13.4.5 (explicit conflict >13.4.5), поэтому 13.4.7 недоступен.
Установлены также зависимости инструментов следующего этапа.

Frontend обновлён до актуальных стабильных совместимых версий. TypeScript 6.0.3
выбран вместо 7: typescript-eslint 8.72.2 требует <6.1. Deprecated lucide-vue-next
заменён поддерживаемым @lucide/vue. Удалены ручные platform-specific optional deps.
Inertia 3: pages config, data-inertia, shared props typing, request-scoped Ziggy
через useRoute и ZiggyVue для SSR.

Проверки: 69 passed / 291 assertions, Vue typecheck, client + SSR build,
SSR server и реальный SSR HTML главной через Nginx HTTP 200. Composer audit: 0.
npm audit: 7 moderate в dev toolchain typescript-eslint/minimatch; предложенное
npm исправление откатывает typescript-eslint. Без force/отката рабочей toolchain.
Источники: Laravel 13 upgrade guide, Inertia v3 upgrade guide, Composer/NPM metadata.

### Инструменты

Horizon 5.51: Redis, supervisor timeout 3660 < retry_after 3720, snapshot каждые
5 минут; запуск/status/snapshot проверены, временный процесс завершён. Production
gate закрыт до явной настройки операторов. Worker profile включается вручную.
Scribe 5.11 генерирует 25 endpoints; response calls отключены, UI требует auth,
Try It Out отключён. ProfileUpdateRequest безопасен при извлечении правил без actor.
Scribe MCP 1.x через VCS: stdio local transport, initialize/tools list/search/get
проверены против реального артефакта `storage/app/private/scribe/openapi.yaml`.
Rector 2.8.4 и PHPStan 2.3.5/Larastan 3.15 доступны; max-анализ запущен,
исходно 342 ошибки. Достижение нуля — отдельный этап, пока не выполнен.

### Boost

Boost 2.10.4 установлен штатной `boost:install --guidelines --skills --mcp`.
Сохранены project instructions через `.ai/guidelines/project.blade.php`; добавлены
6 штатных скиллов, boost.json и project-local `.codex/config.toml`. Оба MCP
запускаются внутри Docker с PHP/extension/config проекта. Boost initialize/tools
list, search-docs и get-absolute-url проверены. Новые skills доступны клиенту
после обновления контекста/следующего хода.

### Pest-стиль

Все прикладные тесты переведены на Pest; русские сценарии, semantic assertions,
Pest\Laravel imports, проверка Inertia components вместо status-only проверок
в затронутых сценариях. Большой независимый эталон главной вынесен в fixture.
Итог: 69 passed / 334 assertions.

### APIdoc и проверка MCP

Описаны все 24 HTTP-операции прикладных контроллеров: session/CSRF, Inertia,
входные поля, пагинация/сортировка, redirects и достижимые ошибки. 12 полных
примеров Inertia получены реальными запросами в изолированной SQLite.
OpenAPI extension фиксирует cookie security и ограничения query-полей.
ResponseCalls удалена из списка strategies: пустой only в Scribe НЕ означает
отключение. Тест генерации запрещает внешние вызовы и проверяет отсутствие HTTP.
Проверены summaries/responses всех операций, query bounds/enums, MCP search/get
с реальным артефактом и ошибка отсутствующего артефакта. 71 passed / 357 assertions.
Схема OpenAPI проверена структурно; отдельный внешний schema validator не установлен.

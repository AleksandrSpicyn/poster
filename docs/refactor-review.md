# Рефакторинг Poster — на ревью

Рефакторинг подготовлен поверх `3291c6e`; пользователь подтвердил commit и push. Исходные staged-удаления двух документов
`docs/superpowers/` сохранены и не относятся к этому пакету.
Untracked `docs/images/*.webp` также не включены в патч рефакторинга.

## Существенные изменения

- PHPStan `max` для app/bootstrap/public/config/database/routes/tests: без baseline,
  ignoreErrors и исключения проблемных прикладных файлов
- Точные типы Eloquent relations/factories, DTO collections и external payload;
  удалены несуществующие IdeHelper mixins и пустые базовые Actions/Tasks/generators
- `MakeShortAction::run()` показывает весь сценарий; PromptGenerated получает
  текст идеи; `VideoBuiled` переименован в `VideoBuilt` во всех потребителях
- User реализует MustVerifyEmail: verified middleware теперь действительно
  закрывает dashboard/posts/prompts неподтверждённому пользователю; регистрация
  отправляет штатное письмо проверки; это намеренное изменение поведения
- DTO списков получают только validated input. Недокументированный orderBy
  игнорируется, page проверяется, null perPage использует default 10,
  порядок стабилизирован по id. Удалённый канал возвращается как null
- Повторный аудио callback и polling готового видео не повторяют события.
  Сборка защищена общей cache lock и проверкой generated после refresh;
  повторные jobs не создают ещё один файл и публикацию в обычном успешном сценарии
- FFmpeg timeout 3540 < SaveVideoJob 3600 < Horizon 3660 < Redis retry_after 3720
- Внешние ответы проверяются до сохранения идентификаторов; заданы connect/response
  timeouts; логи ошибок генераторов/публикации не сохраняют raw responses/trace
- YouTube Client передаётся явно, файл закрывается в finally; OAuth команды
  возвращают exit code и не печатают access/refresh tokens
- Instagram permalink читается через GET согласно
  [Meta Media reference](https://developers.facebook.com/docs/instagram-platform/reference/instagram-media/v21.0/)
- Команды и уведомления обрабатывают записи лениво; UserSeeder читает config,
  требует учётные данные и сохраняет существующий пароль при повторном запуске
- Google service cleanup настроен в правильном разделе Composer extra; сохраняется
  используемый YouTube API. make init создаёт ключ только при пустом APP_KEY
- OpenAPI получает уникальные operationId по методу и пути; уточнены cookie auth,
  pagination bounds, boolean generated и ошибки проверки email

## Импорты и подсказки IDE

- Полные имена классов перенесены в `use`, включая PHPDoc и bootstrap/providers.php;
  правило закреплено в AGENTS, Boost guideline и Rector
- Уточнены schema properties моделей, checked exceptions и типы правил валидации;
  `make ide-helper` обновляет игнорируемый Laravel helper для IDE
- Readonly classes, типизированные constants, явные Eloquent builders и единые
  настройки Pint устраняют замечания PhpStorm без отключения inspections
- Frontend использует установленный npm `ziggy-js` вместо alias на PHP vendor;
  локальный PhpStorm индексирует PHP-исходники Ziggy через include path `src`
- PhpStorm проверил 213 PHP-файлов app/bootstrap/public/config/database/routes/tests:
  после повторных проверок изменённых файлов и двух timeout-файлов предупреждений нет
- Вне этой области остались две contextual weak warnings IDE: `rector.php`
  сообщает о возможном InvalidConfigurationException конфигуратора, хотя
  официальный CLI Rector проходит без ошибок; SSR предлагает удалить `Page<SharedData>`, но
  `vue-tsc` подтверждает, что без него нарушается контракт shared props.
  Инспекции не отключались, корректные типы и API конфигуратора B2B сохранены

## HTTP и DI после замечаний ревью

- Все 24 прикладных контроллера содержат только `__invoke`; многометодные
  auth/settings разделены по операциям. URI, route names и APIdoc сохранены
- Поток записи: Controller → Action → Task → Eloquent. Контроллер получает
  валидированный ввод и actor, вызывает Action, формирует HTTP-ответ.
  Inline validation, запись моделей и управление сессиями вынесены из контроллеров
- Login/throttling, регистрация, пароли, профиль, email verification и logout
  находятся в Actions; атомарные операции над пользователем — в Tasks.
  Lists вызывают Actions, проверка статуса Suno callback находится в SaveAudioAction.
  Запись prompt и готового медиа вынесена из генерационных Actions в Tasks;
  результат запуска видео возвращается через типизированный VideoGenerationData
- `#[CurrentUser]` передаёт actor в контроллер и правила профиля;
  `#[Auth('web')]` выбирает guard внутри сценариев; `#[RouteParameter]` получает reset token
- `#[Config]` передаёт конфигурацию генераторам, middleware, сидерам и OpenAPI;
  `#[Storage('public')]` передаёт диск сборщику; `#[Bind]` описывает реализации
  generator interfaces. Пустой AppServiceProvider удалён вместе с регистрацией
- PublisherFactory получает Container и создаёт publisher с runtime-моделями и DI;
  SavePost и Google Client инъецируются явно. VideoBuiltListener получает factory
  через constructor, polling job — generator implementations через `handle()`
- Маршруты сгруппированы по register/login/password/verification/settings/webhook prefixes
- Rector и Pint взяты из B2B. Пути адаптированы к Poster, сохранён auto-import,
  добавлены strict types и отсутствие пустых строк между imports. Удалены три
  устаревших skip-правила, которых установленный Rector больше не регистрирует.
  Остальные наборы и действующие исключения B2B сохранены. DI API сверены с
  [Laravel 13 container documentation](https://github.com/laravel/docs/blob/13.x/container.md)

## Покрытие поведения

| Область | Проверки |
|---|---|
| Auth/settings | Вход/выход, throttle, регистрация и отказы, reset token, смена/подтверждение пароля, профиль/удаление |
| Проверка email | Подпись/hash, повторное письмо, запрет dashboard/lists без подтверждения |
| Страницы | Независимый эталон главной, auth props, policy, terms, Inertia components |
| Списки | Фильтры с decoy записями, pagination/sort, invalid input, orderBy, null default, удалённый канал |
| Генерация | OpenAI SDK fake, Suno/Hailuo/Runway HTTP fake, fallback, malformed responses, оба провайдера недоступны |
| Media/queue | Реальный FFmpeg + ffprobe audio/video streams, незавершённые inputs, polling/release/fail, lock, повторная сборка |
| Публикация | YouTube SDK с Guzzle MockHandler и реальным файлом; Instagram container/publish/permalink и отказы; TikTok/VK заглушки |
| Команды/seed | OAuth success/failure, команды генерации/сборки, idempotent seed и отсутствие credentials |
| Webhooks/notifications | Suno success/ignore/invalid/repeat, UKassa log, адресаты и текст Telegram |
| Документация | Scribe без HTTP, schemas/security, MCP search/get и missing artifact; реальный stdio initialize/tools/call |

Это матрица сценариев; процент line/branch coverage не измерялся. Платные API,
реальная публикация и реальные Telegram-сообщения в проверках не выполнялись.

## Границы

TikTok/VK остаются существующими заглушками. Публичные webhooks по текущему
контракту не имеют подписи, а списки общие для всех подтверждённых пользователей:
в схеме нет владельца проекта/tenant. Эти продуктовые контракты не заменены
выдуманной моделью доступа. Lock не обеспечивает exactly-once для удалённых API
при падении после внешней публикации. Полный outbox/remote idempotency — отдельная
задача с контрактами провайдеров. CI, deploy и production не выполнялись.

npm audit сообщает 7 moderate в dev-зависимостях typescript-eslint/minimatch.
Используется последняя совместимая toolchain; принудительные overrides не внесены.

## Итоговая проверка

- `make pipe`: Pint, Rector dry-run, PHPStan max, 113 Pest tests / 540 assertions,
  Scribe generation и production frontend build — успешно
- `npm run typecheck`, client + SSR build — успешно; SSR server и HTML проверены
- OpenAPI 3.0.3 прошёл внешний openapi-spec-validator; 24 операции, уникальные IDs
- Scribe MCP initialize/tools/list/search/get повторно проверены через stdio
- Horizon start/status/snapshot проверены, временный процесс завершён
- Браузер: главная и форма входа отображаются без ошибок консоли
- `composer validate --strict`, `composer audit`, `docker compose config`,
  `git diff --check` — успешно
- Проверена инициализация APP_KEY на пустом env и сохранение ключа при повторе

Подтверждены локальные проверки. Полные платные сценарии внешних провайдеров
проверялись с fake/MockHandler; это не live-публикация. Миграции существующей
рабочей БД и deployment не запускались; Compose использует отдельную локальную БД.

# Архитектура Laravel-приложения

## Архитектура Poster

Poster использует стандартные каталоги Laravel 13, Actions со входом `run()` и
Tasks для отдельных операций и конкретные репозитории для запросов и persistence.
Основной поток: Controller → Action → Task → Repository. Примеры модульного профиля и Article ниже — общие рекомендации,
а не описание текущих классов Poster.

| Область | Владелец и граница ответственности |
|---|---|
| HTTP | `routes/` задают доступ; `app/Http/Controllers` получают Laravel Data DTO, вызывают Action и формируют Inertia/redirect-ответ |
| Ввод и проекции | `app/Data` валидирует HTTP-ввод и содержит внутренние DTO; `app/Http/Resources` задаёт поля списков, без credentials интеграций |
| Сценарии | `app/Actions` связывает шаги генерации, сборки, входа и настроек |
| Операции | `app/Tasks` готовит запросы к генераторам, проверяет доменные ограничения и собирает медиа |
| Хранение | `app/Repositories` выполняет выборки, eager loading, блокировки и запись через Eloquent; возвращает модели, коллекции и DTO |
| Интеграции | `app/Classes/Generators` адаптирует протоколы генерации; `app/Classes/Publishers` публикует готовое медиа |
| Фон и события | `app/Events`, `app/Listeners`, `app/Jobs` продолжают конвейер при готовности внешних результатов |
| Данные | `Project` объединяет `Prompt` и общие `Channel` через `channel_project`; `Post` хранит результат публикации, `Chat` — получателя Telegram |
| Интерфейс | `resources/js/pages` отображает Inertia props; layouts/components формируют кабинет, composables управляют клиентским состоянием |
| Инфраструктура | `bootstrap/app.php`, providers и `config/` связывают HTTP, DI, доступ к операционным панелям, queues и интеграции |

### Запросы и внутренние контракты

`EloquentRepository<TModel>` объединяет типизированные операции хранения.
Конкретные репозитории задают scopes, подготовленные связи, пагинацию и запись
связанных графов. Query Builder не передаётся в Task. Транзакция сценария
удерживает блокировки; чтения claim сохраняют порядок Project → Series/Run →
Prompt/Request/Operation. `PublicationClaimData`, `EpisodeScriptClaimData` и
`ShotOperationClaimData` передают подготовленный контекст без набора скрытых запросов.

Workspace, параметры генерации, зафиксированные персонажи и план монтажа используют
DTO. Массивы остаются на границах внешнего JSON/SDK, Eloquent и исторических
snapshots: формат уже сохранённых историй, manifest и hash inputs сохраняется.
`PrepareShotGenerationInputSubAction` последовательно проверяет видеопараметры,
персонажей и источник продолжения перед формированием snapshot. `EpisodeAssemblyPlanData`
проверяет сохранённые клипы до запуска FFmpeg.

Формы эпизода используют `useEpisodeScreenplay`, `useEpisodeMontage` и
`useEpisodeSources`. Watchers сохраняют локальный черновик при realtime-обновлении
и отмечают устаревшую версию; изменение текущего сценария сбрасывает выбор дублей.
`ProjectSeriesFields` и `ShotScriptFields` отвечают за свои поля, а страницы —
за композицию и навигацию.

### Точки входа и данные кабинета

`HomeController → HomepageData → Welcome.vue` публикует утверждённый контент профиля.
Аутентификация и настройки проходят через DTO и Actions; хеширование пароля
выполняет cast модели User. Middleware `auth` и `verified` защищают dashboard,
projects, prompts и posts; настройки профиля доступны под `auth`, включая повторное
подтверждение email после его смены. `OperationsPolicy` отдельно ограничивает
Logs и Queues подтверждёнными адресами из конфигурации.

`DashboardController → GetDashboardAction → GetDashboard` читает общие счётчики
и ограниченные последние выборки с заранее загруженными связями. Обзор не
запускает генерацию или публикацию.

Раздел `Projects` содержит тип `short` / `series`. Для сериала та же форма
содержит паспорт, общий сюжет, число эпизодов и автоматизацию, а страница проекта —
персонажей и эпизоды. `Series` остаётся техническим владельцем story-контекста
и старых ID; второй сериал в проекте запрещён под блокировкой проекта.
`Projects/*Controller → *ProjectAction → Projects/*` сохраняет
границы HTTP, сценария и persistence; `ProjectData` задаёт правила ввода.
Страница проекта показывает счётчики, каналы без credentials и постраничную
историю только этого проекта. `DeleteProject` в транзакции блокирует строку
проекта и разрешает удаление только без prompts, channels и series. Включение
автоматизации ставит первый выпуск в очередь; обычное сохранение уже включённых
настроек не создаёт новый выпуск.

Раздел `Channels` доступен по gate `manageChannels` владельцу и операторам.
Controller → Action → Task → ChannelRepository управляет настройками,
подключением OAuth и удалением; `ChannelOAuth` адаптирует протоколы Google и
Instagram Business Login. Одноразовое состояние в сессии связано с оператором,
каналом, сроком и fingerprint настроек; callback повторно проверяет fingerprint
под блокировкой перед сохранением. Секреты и токены скрыты из props и старого
ввода при ошибке. Новый Google токен включает права загрузки и проверки владельца;
Instagram получает долгоживущий токен, срок виден в форме.

Каналы общие: выбор `ProjectData.channel_ids` синхронизирует pivot под блокировкой
проекта до первого автоматического запуска. Отсутствие поля сохраняет связи,
пустой массив снимает все. Миграция сохраняет прежние IDs/credentials/Post и
переносит `channels.project_id` в pivot. Старый nullable FK оставлен для
совместимости factory/seed; runtime принадлежность определяет только pivot.
`Channel.is_active` и актуальное подключение проверяются перед публикацией
именно для проекта исходного Prompt или сборки; другой активный проект не
разрешает отправку. Удаление канала с проектами, Post или PublicationRequest
запрещено; отключение сохраняет историю. Обновление Google токена не может
восстановить уже отключённое или изменённое подключение.

Списки `Projects`, `Posts` и `Prompts` выполняют фильтрацию/сортировку/пагинацию на сервере.
Tasks заранее загружают `channel` либо `project`/`media` для Resources.
`Prompt.stage()` вычисляет этап по сохранённым ID/URL и признаку сборки;
`PromptResource` передаёт его как `stage` и `stage_label`. Ожидание результата
не означает подтверждённую работу worker или установленный отказ провайдера.
Проекты и каналы сейчас не принадлежат User: списки общие для допущенных
пользователей, пользовательского tenant scope в модели данных нет.
`HandleInertiaRequests` задаёт shared props, `app.ts` разрешает имя страницы в Vue,
`ssr.ts` служит отдельной SSR-точкой. `HandleAppearance`, Blade и `useAppearance`
согласуют тему через cookie/localStorage; sidebar хранит состояние в cookie.

`Project.is_active` по умолчанию включён, включая существующие проекты.
Выключение через настройки под блокировкой проекта останавливает его активные
`ProductionRun`, сохраняя историю и настройки автоматизации сериала. Новые
запуски пропускаются, а queued claims повторно проверяют флаг перед внешним
эффектом. Уже отправленные результаты можно получить и сохранить; повторное
включение разрешает новые выпуски без возобновления остановленного намерения.

### Конвейер генерации и публикации

`app:produce-content` создаёт `ProductionRun` по типу проекта; `app:make-short`
делегирует ему. Обычный проект выполняет прежний конвейер через
`MakeShortSubAction` (`MakeShortAction` остаётся совместимым ручным входом):

1. `CreateBasePrompt` добавляет последние 50 идей проекта к запросу
   `PromptGenerator/Codex`, который вызывает Agent API Laravel AI с `provider: codex`.
   `CreatePrompt` сохраняет новую идею. `CreateImage → ImageGenerator/Codex`
   вызывает Laravel AI `Image` с тем же провайдером. Общий `CodexProvider`
   находится в `app/Classes/Generators/Codex`; отдельные `CodexTextGateway` и
   `CodexImageGateway` переводят соответствующие контракты Laravel AI в CLI-запросы.
   `CodexProcess` отвечает за запуск закреплённого CLI, авторизацию ChatGPT,
   lock, таймаут, чтение JSON и очистку временного каталога. `CodexReferenceFiles`
   проверяет и копирует локальные референсы с ограничениями размера и формата.
   `CodexProcess` не знает правил
   генерации идей или первого кадра. API-ключи исключаются из окружения,
   shell tools отключены; общий lock сериализует использование одного `CODEX_HOME`
   для текста и изображений. Текст запускается в `read-only`, изображения — в
   `workspace-write` со встроенным image tool. Текстовая модель задаётся `CODEX_MODEL`.
   Проверенный вертикальный PNG сохраняется на публичный диск, его URL становится
   `image_url`. При ошибке подписки или CLI генерация останавливается без API fallback.
   Идея задаёт визуальный хук, цель, препятствие, нарастание действия и развязку;
   длительность сценария и видео берётся из `GeneralSettings`. Первый кадр
   показывает незавершённую проблему, а звуковые промпты запрашивают ритмичную
   инструментальную музыку с развитием и акцентом на развязке, без речи.
2. `CreateVideo → VideoGeneratorFactory` разрешает драйвер из регистрации
   `services.video.drivers`; основной и резерв задаются конфигурацией. Все
   адаптеры реализуют один `VideoGenerator`: запуск, нормализованный результат,
   источник звука и лимит опроса. Неопределённый результат запуска выражается
   `VideoGenerationUncertainException` и не вызывает резервную платную задачу.
   `VideoGenerationData.provider` сохраняется в `Prompt.video_provider` вместе
   с task ID. Boolean в старых payload переводится через `VideoProvider::fromLegacy`.
3. `VideoGenerating → VideoGeneratingListener → CheckVideoCreation` опрашивает
   сохранённого провайдера. Listener берёт лимит попыток из контракта драйвера;
   `null` и `VideoGenerationPendingException` откладывают job на 60 секунд.
   Адаптер сам переводит ошибки внешнего API в общие состояния. Успех возвращает
   `VideoResultData`: `videoUrl` и необязательный `audioUrl`; Task сохраняет их
   и отправляется `VideoGenerated`. Пустой audio URL не затирает готовый Suno.
4. `CreateAudio` использует `VideoAudioSource`, возвращённый фактически выбранным
   драйвером: `EMBEDDED` не запускает отдельное аудио, `SEPARATE` запускает Suno
   и сохраняет `audio_id`. Seedance возвращает ссылку на один файл в обоих полях
   результата: видеопоток и аудиопоток берутся из него. Независимый
   `POST webhook.suno → SunoController → SaveAudioAction → SaveAudio` принимает
   стадию `complete`, берёт первый трек и находит Prompt по task ID. Новый
   `audio_url` вызывает `AudioGenerated`; тот же URL повторно событие не отправляет.
5. `VideoGeneratedListener` принимает и `VideoGenerated`, и `AudioGenerated`,
   ставя `SaveVideoJob`. `SaveVideoAction` под cache lock перечитывает Prompt:
   сборка требует `video_url`, `audio_url` и `generated=false` для любого драйвера.
   Lock живёт 3720 секунд, timeout job — 3600 секунд. `BuildVideo` одним способом
   сопоставляет видеопоток первого источника и аудиопоток второго в FFmpeg;
   `SaveGeneratedVideo` прикрепляет файл через Media Library и ставит `generated=true`.
6. `VideoBuilt → VideoBuiltListener → PublisherFactory` отправляет готовый ролик
   каналам проекта. YouTube загружает локальный файл порциями и сохраняет Post.
   Instagram создаёт контейнер, а через пять минут `PublishInstagramJob`
   выполняет `media_publish` и сохраняет Post. Новые jobs хранят только канал,
   источник и ID контейнера, а Publisher создаётся через DI в worker. Старые
   сериализованные Publisher payload восстанавливаются через тот же контекст. TikTok и VK пока возвращают `false`.
7. `SavePost → PostCreated → PostCreatedListener` ставит Telegram-уведомления
   всем Chat. Слушатель и сами notifications выполняются в очереди.

`generated` означает готовый ролик, а не успешную публикацию во всех каналах.
Блокировка сборки и проверка повторного audio URL не дают общей гарантии
exactly-once для внешних публикаций. Резервный провайдер выбирается при ошибке
запуска, а не при последующей ошибке polling. `app:save-video` повторно проверяет
незавершённые сборки через тот же Action. Автозапуск новых выпусков в
`routes/console.php` закрыт `production.schedule_enabled=false`. Снимки метрик
Horizon и восстановление уже начатых операций выполняются независимо от него.
События связываются с listeners через обнаружение
Laravel; актуальную регистрацию показывает `php artisan event:list`.

### Конвейер сериалов

`StartProductionAction → CreateProductionRun → AdvanceProductionJob` сохраняет
намерение выпуска, actor и этап. Один активный или требующий внимания выпуск
блокирует следующий; повторный request key возвращает прежний результат.
`ProduceSeriesSubAction` направляет сохранённый этап в отдельный SubAction:
подготовку персонажей и эпизода, сценарий, медиа, монтаж, подтверждение истории
или публикацию. Каждый этап координирует только свои Tasks. До медиа проверяются суммарная длительность и ограничения модели
для всех планов. Лимит эпизодов учитывается при каждом запуске. Очередь
и `app:reconcile-productions` продолжают сохранённый этап.

`InspectEpisodeOutcome` извлекает хронологические кадры готового файла, проверяет
checksum и передаёт кадры text gateway Codex. Наблюдаемые события сохраняются
отдельно от планируемого сценария и становятся continuity принятой сборки.
Цель и ожидаемый финал передаются отдельно как критерии оценки, а не как факты.
Инструкции анализа требуют отмечать отсутствие читаемого развития и развязки,
а дефекты реквизита и непоказанную следующую реакцию не превращать в open threads.
Проверка учитывает замысел сцены: запланированное превращение не считается
дефектом предмета, а диалог не оценивается как бессюжетность по неподвижным кадрам.
Аудиособытия этим анализом не подтверждаются; ограничение указывается в review notes.
Непригодный монтаж, смена источника, отказ или unknown оставляет `needs_attention`,
сохраняя результаты. Выключение автоматизации сразу останавливает активный выпуск.
Автоматические `GenerationOperation` и `PublicationRequest` сохраняют
`production_run_id`; claim перед внешней отправкой проверяет состояние выпуска.
Остановка блокирует ещё не начатую генерацию и публикацию готового контейнера,
включая задержавшееся задание. Уже отправленный внешний вызов может завершиться:
его receipt и файл сохраняются, но отменённый дубль не принимается.

`Series → Character/CharacterRevision → Episode/EpisodeRevision → Scene → Shot`
описывает паспорт, выбранную внешность и утверждённый сценарий. Старый `Prompt`
с nullable `shot_id` хранит конкретный дубль; standalone-записи продолжают работать
без этой связи. Обычные списки и счётчики prompts исключают приватные дубли.

`BuildEpisodeContext` собирает ограниченный контекст из паспорта, текущих
утверждённых персонажей и не более 50 предыдущих принятых эпизодов своего сериала.
Для проверки повторов история включает название и цель сценария именно принятой
сборки в `previous_screenplay`, отдельно от фактических `confirmed_events`.
Старые события отбрасываются до лимита 64 КБ, точный финал источника сохраняется.
Снимок и hash закрепляются за версией сценария и дублем. Ожидаемый финал сценария
не заменяет подтверждённые события принятого видео.

`RequestEpisodeScriptAction` сохраняет намерение и отправляет job; worker перед
Codex повторно проверяет автора, версии и контекст. Структурированный ответ
валидируется тем же DTO, что ручной сценарий. Поздний ответ не затирает правки.
Занятый общий Codex lock откладывает намерение, не создавая новую попытку.

`RequestShotGenerationAction` фиксирует входы и `GenerationOperation` для
`image → video → download`. Claims, операции и следующие шаги сохраняются
транзакционно; cache lock ограничивает параллельную обработку. Модели публикуют
capabilities, а request DTO фиксирует provider/model/duration/audio. Продолжение
сценария не поручается видеомодели: общий `story_outline` остаётся у сценариста,
визуальные промпты получают цель текущей сцены и ожидаемый финал только для её
последнего плана. Стиль и ограничения паспорта сохраняются. Продолжение
сцены использует извлечённый исходный кадр; смена ракурса передаёт его генератору
кадра вместе с приватными референсами персонажей. Snapshot хранит конкретные
media ID и checksums. Для отправки видео кадр кодируется из приватного файла.

`EpisodeAssembly` фиксирует порядок дублей, trims, mute и checksums в manifest.
Очередь `render` нормализует фрагменты FFmpeg, собирает MP4, сохраняет финальный
кадр и хвост. Немой режим заменяет входящий звук тишиной, включая неожиданный звук
провайдера. `AcceptEpisodeAction` под блокировкой продвигает историю и помечает
последующие эпизоды `requires_review`, сохраняя прежние версии и медиа.

Дубли не вызывают legacy listeners автопубликации. `PublicationRequest` отдельно
связывает принятую сборку, канал и metadata; `Post` сохраняет ссылки на сборку
и request. Instagram container ID сохраняется до финального publish. Unknown
исход не вызывает новую отправку: оператор проверяет существующий receipt.
Неизвестный дубль можно явно закрыть как `abandoned` с записью решения; это не
отменяет внешний запрос. Поздний worker не запускает следующую стадию.

Новые маршруты требуют `manageSeries` для подтверждённого владельца или
оператора очередей. Приватные previews сохраняют эту границу; экспорт принятого
видео провайдеру доступен по временной подписи. Jobs повторно проверяют права
перед внешним запуском. Reconciliation восстанавливает безопасные локальные
шаги и чтение известных remote ID, сохраняя неизвестные отправки для оператора.

Текущее состояние и границы живой приёмки — в
[плане реализации](series-implementation-plan.md#17-текущее-состояние-реализации).

### Живые обновления кабинета

`WorkspaceUpdated` отправляется после commit в очередь `broadcasts` с отдельным
supervisor. Событие содержит только сигнал изменения, без сценария, приватных
ссылок или credentials. Общий приватный канал доступен подтверждённым пользователям;
`workspace.series` требует `manageSeries`. Model observer и явные сигналы после
условных bulk updates покрывают смену статусов. Reverb доступен браузеру через
`/app/` на том же origin.

Echo инициирует ограниченный Inertia reload текущих props с объединением частых
событий; формы, ошибки и scroll сохраняются. После переподключения или возврата
во вкладку данные перечитываются. При потере сокета работает обновление раз в 30 секунд.

### Навигация и проверки

Локальный [граф Graphify](project-graph.md) находится в `graphify-out/` и
обновляется через `make graph-update`. Его связи помогают найти код, но
динамические маршруты, DI и события проверяются по исходникам и тестам.
Основной Pest-прогон использует отдельную MySQL-базу `poster_test`, быстрый
дополнительный прогон — SQLite in-memory; внешние границы закрыты fakes.
Проверки лежат в
`tests/Feature` по HTTP-точкам, CLI, jobs, listeners и адаптерам.
Docker Compose изолирует Poster от остальных проектов; workers запускаются
отдельным профилем. Детали доставки и runtime — в [deployment.md](deployment.md).

## Область применения

Документ поддерживает два профиля организации кода: стандартный Laravel и
модульный Laravel. Профиль выбирается для приложения и фиксируется в его
инструкциях. Существующие пути, пространства имён и правила регистрации нужно
проверить до изменений; эти примеры не требуют переноса работающего приложения.

Расположение файлов и количество слоёв — разные решения. Модульная структура
сама по себе не требует репозиториев, Tasks или собственного базового класса.
Дополнительные слои вводятся для понятной ответственности и повторного
использования, а не ради одинакового количества каталогов.

## 1. Стандартный профиль

Классы одного назначения располагаются в общих каталогах приложения. Домен или
функциональная область может уточняться подкаталогом, но полное дерево слоёв
для каждой области не дублируется.

```text
app/
  Http/
    Controllers/
    Middleware/
    Requests/
    Resources/
  Models/
  Policies/
  Providers/
  Events/
  Listeners/
  Jobs/
  Notifications/
  Console/Commands/
database/
  factories/
  migrations/
  seeders/
routes/
  web.php
  api.php
tests/
  Feature/
  Unit/
```

Это схема размещения, а не список обязательных каталогов. Состав начального
приложения и регистрация маршрутов зависят от версии и установленных компонентов;
например, API-маршруты могут потребовать отдельного включения.

Типичный путь HTTP-запроса:

```text
Route → Controller → валидация / авторизация → операция → Resource / Response
```

Операцией может быть простой Eloquent-вызов или существующий сервис/use case.
Сложный бизнес-сценарий выносится из контроллера. Если приняты Actions, DTO или
Repositories, их размещают, например, в `app/Actions`, `app/Dto` и
`app/Repositories`; это дополнительные каталоги приложения, не требования Laravel.

## 2. Модульный профиль

Каждый модуль владеет своей функциональностью и повторяет необходимые каталоги
слоёв. Ниже приведён рекомендуемый вариант; названия корня и каталогов могут
отличаться в существующем приложении.

```text
app/
  Modules/
    {Module}/
      Controllers/
      Requests/             # Form Requests, если используются
      Dto/                  # DTO, если используются
      Actions/              # при выбранной модели use cases
      Tasks/                # при выбранном атомарном слое
      Repositories/         # при наличии слоя persistence
      Processors/           # при наличии обработки query-условий
      Models/
      Policies/
      Resources/
      Routes/
        api.php
        web.php
      Database/
        Factories/
        Migrations/
        Seeders/
      Providers/
      Events/
      Listeners/
      Jobs/
      Notifications/
      Exceptions/
      Integrations/
      Tests/
        Feature/
        Unit/
        Fixtures/
  Admin/                    # отдельная проекция, если нужна
    {Module}/
      Controllers/
      Requests/
      Resources/
      Routes/
      Tests/
  Support/                  # переиспользуемая инфраструктура, если нужна
```

Не нужно создавать пустые каталоги или копировать весь список в каждый модуль.
Общий каталог может называться `Shared`, `Support` или иначе. Размещайте в нём
реально общие примитивы; бизнес-правила остаются у владельца домена.

Для явно выбранной схемы Action–Task–Repository поток выглядит так:

```text
Route → Controller → Action → Task → Repository
Результат операции → Controller → Resource → Response
```

Валидированный Form Request или DTO поступает на границе HTTP. Action
координирует один бизнес-сценарий, Task выполняет атомарную операцию, Repository
отвечает за запросы и сохранение. При выборе этого варианта контроллер не
обходит Action, Action обращается к Repository через Task, а Tasks не вызывают
Actions и не превращаются во второй слой оркестрации. Между use cases избегают
циклов; общие шаги извлекают в подходящую операцию или явно локальный sub-action.

Имена entrypoint-методов и правила наследования закрепляются локально; `run()`
можно выбрать соглашением, но Laravel не требует такого метода или base class.
В проекте без Task-слоя допустим путь Controller → Action → Eloquent/Repository.
Не добавляйте недостающие слои механически.

## 3. Соответствие путей

| Ответственность | Стандартный профиль | Модульный профиль |
|---|---|---|
| Контроллер | `app/Http/Controllers` | `app/Modules/{Module}/Controllers` |
| Form Request | `app/Http/Requests` | `app/Modules/{Module}/Requests` |
| Модель | `app/Models` | `app/Modules/{Module}/Models` |
| Resource | `app/Http/Resources` | `app/Modules/{Module}/Resources` |
| Миграция | `database/migrations` | `app/Modules/{Module}/Database/Migrations` |
| Фабрика | `database/factories` | `app/Modules/{Module}/Database/Factories` |
| Feature-тест | `tests/Feature` | `app/Modules/{Module}/Tests/Feature` |
| Unit-тест | `tests/Unit` | `app/Modules/{Module}/Tests/Unit` |

Таблица задаёт примеры профилей, а не гарантии автоматического обнаружения.

## 4. Регистрация и обнаружение

Стандартный профиль использует bootstrap и конфигурацию установленной версии
Laravel. Модульному профилю дополнительно нужно проверить:

- PSR-4/autoload для классов, фабрик, сидеров и тестовых fixtures
- загрузку маршрутов и отсутствие повторной регистрации
- обнаружение миграций, включая работу в тестовой среде
- соответствие моделей фабрикам вне стандартного namespace
- регистрацию providers, policies, events/listeners и console-команд
- PHPUnit suites, Pest paths, базовый TestCase и DB traits для модульных тестов
- пути сканирования DTO, настроек и API-документации, если пакеты их используют

Сначала используйте существующий loader/provider. Не добавляйте параллельный
механизм регистрации и не считайте каталог зарегистрированным только по его имени.

## 5. Общие границы ответственности

- Контроллер переводит HTTP-ввод в вызов операции и формирует HTTP-ответ.
  Валидация применяется через принятый Form Request, DTO или validator.
- Авторизация проверяет действие и actor/tenant scope. Route binding и `exists`
  не заменяют эту проверку; детали — в [authorization.md](authorization.md).
- Resource/serializer определяет ответ, работает с подготовленными данными и
  не скрывает запись, внешние вызовы или дополнительные запросы.
- Связи для ответа загружаются заранее. Условная сериализация должна совпадать
  с реальными условиями загрузки и доступа.
- Domain exceptions выражают ожидаемые ошибки сценария; HTTP-статус и envelope
  назначаются существующим exception handler. Не скрывайте ошибки общим catch.
- Интерфейсы полезны на внешних границах, для нескольких реализаций или реального
  DI-контракта; единственный локальный класс не требует интерфейса по умолчанию.
- Транзакции защищают согласованность записи. Зависимые внешние эффекты выполняют
  после commit с учётом повторов; см. [concurrency-and-delivery.md](concurrency-and-delivery.md).

## 6. Административные сценарии

В стандартном профиле административные контроллеры и Resources можно выделить
в подкаталоги `app/Http/Controllers/Admin` и `app/Http/Resources/Admin`.
В модульном профиле допустим отдельный `app/Admin/{Module}` или административная
часть внутри модуля. Выберите один вариант для новых файлов.

Доменные модели, миграции и общие бизнес-операции сохраняют одного владельца.
Административный интерфейс не требует их дублирования. Разделяйте serializers,
когда права или поля отличаются; общая безопасная проекция может переиспользоваться.
Отдельные user-модели, guards, наследование admin repositories и `__invoke`
контроллеры — локальные решения, а не обязательные свойства административного API.

## 7. Проверка изменений

Проверьте регистрацию маршрута, валидацию, policy/scope, поток операции,
транзакции, сериализацию и реальные тесты в выбранном профиле. Изменение пути
требует проверки namespace, DI, autoload, discovery и всех потребителей.
Не совмещайте добавление функциональности с незаказанным переходом между профилями.

Связанные правила: [testing.md](testing.md), [apidoc.md](apidoc.md),
[naming.md](naming.md), [package-integration.md](package-integration.md).

Справочник стандартных каталогов: [Laravel Directory Structure](https://laravel.com/docs/13.x/structure).
Для другой версии используйте её документацию; модульный профиль выше —
соглашение приложения, а не встроенный стандарт фреймворка.

## 8. Конкретные соглашения для новых сценариев

Этот набор можно принять как готовые правила команды для обоих профилей.
Он описывает архитектуру приложения, а не обязательные ограничения Laravel.
При адаптации существующего проекта отличия фиксируются явно, вместо замены
правил формулировкой «как-нибудь по местным соглашениям».

| Слой | Отвечает за | Не должен делать |
|---|---|---|
| Controller | HTTP-ввод, один Action, Resource/Response | Вызывать Task/Repository, собирать доменную логику |
| Form Request / DTO | Валидацию и подготовку входного контракта | Сохранять модели, запускать внешние эффекты |
| Action | Один бизнес-сценарий, транзакцию, координацию Tasks | Обходить Task ради Repository, вызывать другой use case |
| Task | Одну операцию, вызов Repository/интеграции | Вызывать Actions или строить цепочки других Tasks |
| Repository | Query и persistence | Формировать HTTP-ответ, генерировать файлы, отправлять mail |
| Processor | Переиспользуемое ограничение/преобразование query | Снимать actor/tenant scope, выполнять внешние эффекты |
| Resource | Сериализацию подготовленных данных | Запрашивать сервис/БД, изменять состояние |

У Actions и Tasks публичная точка входа — `run()`. Вспомогательные методы
допустимы; альтернативные entrypoints `execute()`/`handle()` для этих классов
не вводятся. Job/listener продолжает использовать entrypoint своего framework API.
Локальный `*SubAction` допустим как часть одного сценария; общий повторяемый шаг
не должен превращаться в неявный вызов одного бизнес-сценария из другого.

Конкретный Repository инжектируется в Task напрямую. Не создавайте интерфейс и
provider binding для единственной локальной реализации. Если приложение уже
имеет базовые Action/Task/Repository и Process contract, используйте их; новые
пустые base classes ради этих примеров не требуются.

Для нового API рекомендуются явные routes и `__invoke` controllers. Resource
controller остаётся допустимым осознанным вариантом; его permission/binding
контракт должен быть не менее явным. Числовой ID ограничивается `whereNumber`,
UUID — подходящим UUID constraint, nested child — scoped binding и policy.

## 9. Сквозной пример: публикации

Пример использует вымышленный ресурс Article. Это связный учебный контракт,
не код какого-либо существующего продукта:

- User создаёт собственные Article с `title` и `is_published`
- `user_id` назначается сервером, не принимается из input
- `POST articles.store` возвращает `201` и `data` с созданной публикацией
- `GET articles.index` возвращает только публикации текущего User, без пагинации
- `author` загружается операцией и сериализуется отдельным Resource
- authentication в примере — стандартный guard middleware `auth`; для API token
  приложения замените его на реально установленный guard

Фрагменты рассчитаны на современный Laravel и PHP 8.2+. Один именованный класс
хранится в одном файле; подписи ниже задают его размещение. Поведение зависит
также от стандартной модели User, auth-конфигурации и включённых API routes.

### 9.1. Route и Form Request: стандартный профиль

`routes/api.php`; `articles.*` — route names независимо от внешнего `/api` prefix:

```php
use App\Http\Controllers\ArticleIndexController;
use App\Http\Controllers\ArticleStoreController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('articles')->name('articles.')->group(function (): void {
    Route::get('/', ArticleIndexController::class)->name('index');
    Route::post('/', ArticleStoreController::class)->name('store');
});
```

`app/Http/Requests/StoreArticleRequest.php`:

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'is_published' => ['required', 'boolean'],
            'user_id' => ['prohibited'],
        ];
    }
}
```

`app/Dto/StoreArticleData.php` — обычный PHP DTO, без зависимости от Data-пакета:

```php
namespace App\Dto;

final readonly class StoreArticleData
{
    public function __construct(
        public string $title,
        public bool $isPublished,
    ) {}
}
```

Вариант Spatie Data с validation attributes приведён в
[package-integration.md](package-integration.md). Не смешивайте два независимых
источника правил в одном endpoint без причины.

### 9.2. Controller → Action → Task → Repository

`app/Http/Controllers/ArticleStoreController.php`:

```php
namespace App\Http\Controllers;

use App\Actions\StoreArticleAction;
use App\Dto\StoreArticleData;
use App\Http\Requests\StoreArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final class ArticleStoreController
{
    public function __invoke(
        StoreArticleRequest $request,
        StoreArticleAction $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $data = new StoreArticleData(
            title: $request->validated('title'),
            isPublished: $request->boolean('is_published'),
        );
        $article = $action->run($actor, $data);

        return (new ArticleResource($article))->response()->setStatusCode(201);
    }
}
```

`app/Actions/StoreArticleAction.php`:

```php
namespace App\Actions;

use App\Dto\StoreArticleData;
use App\Models\Article;
use App\Models\User;
use App\Tasks\CreateArticleTask;

final readonly class StoreArticleAction
{
    public function __construct(private CreateArticleTask $createArticle) {}

    public function run(User $actor, StoreArticleData $data): Article
    {
        return $this->createArticle->run($actor, $data);
    }
}
```

`app/Tasks/CreateArticleTask.php`:

```php
namespace App\Tasks;

use App\Dto\StoreArticleData;
use App\Models\Article;
use App\Models\User;
use App\Repositories\ArticleRepository;

final readonly class CreateArticleTask
{
    public function __construct(private ArticleRepository $articles) {}

    public function run(User $actor, StoreArticleData $data): Article
    {
        return $this->articles->createFor($actor, $data);
    }
}
```

`app/Repositories/ArticleRepository.php`:

```php
namespace App\Repositories;

use App\Dto\StoreArticleData;
use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class ArticleRepository
{
    public function createFor(User $actor, StoreArticleData $data): Article
    {
        $article = new Article;
        $article->title = $data->title;
        $article->is_published = $data->isPublished;
        $article->user_id = $actor->getKey();
        $article->save();

        return $article->load('author');
    }

    /** @return Collection<int, Article> */
    public function listFor(User $actor): Collection
    {
        return Article::query()
            ->where('user_id', $actor->getKey())
            ->with('author')
            ->orderBy('id')
            ->get();
    }
}
```

Пример намеренно показывает все границы принятого layered-профиля, даже когда
операция короткая. В компактном приложении без Task/Repository-слоёв не нужно
создавать их только ради одной записи: переносимое правило — согласованная
архитектура, а не обязательное количество классов.

### 9.3. Список без обхода слоёв

`app/Tasks/ListArticlesTask.php` и `app/Actions/ListArticlesAction.php`:

```php
namespace App\Tasks;

use App\Models\Article;
use App\Models\User;
use App\Repositories\ArticleRepository;
use Illuminate\Database\Eloquent\Collection;

final readonly class ListArticlesTask
{
    public function __construct(private ArticleRepository $articles) {}

    /** @return Collection<int, Article> */
    public function run(User $actor): Collection
    {
        return $this->articles->listFor($actor);
    }
}
```

```php
namespace App\Actions;

use App\Models\Article;
use App\Models\User;
use App\Tasks\ListArticlesTask;
use Illuminate\Database\Eloquent\Collection;

final readonly class ListArticlesAction
{
    public function __construct(private ListArticlesTask $listArticles) {}

    /** @return Collection<int, Article> */
    public function run(User $actor): Collection
    {
        return $this->listArticles->run($actor);
    }
}
```

`app/Http/Controllers/ArticleIndexController.php`:

```php
namespace App\Http\Controllers;

use App\Actions\ListArticlesAction;
use App\Http\Resources\ArticleResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ArticleIndexController
{
    public function __invoke(Request $request, ListArticlesAction $action): AnonymousResourceCollection
    {
        /** @var User $actor */
        $actor = $request->user();

        return ArticleResource::collection($action->run($actor));
    }
}
```

### 9.4. Model и вложенные Resources

`app/Models/Article.php`:

```php
namespace App\Models;

use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'is_published' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
```

`app/Http/Resources/AuthorResource.php`:

```php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AuthorResource extends JsonResource
{
    /** @return array{id: int, name: string} */
    public function toArray(Request $request): array
    {
        return ['id' => (int) $this->id, 'name' => $this->name];
    }
}
```

`app/Http/Resources/ArticleResource.php`:

```php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ArticleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'title' => $this->title,
            'is_published' => $this->is_published,
            'author' => new AuthorResource($this->whenLoaded('author')),
        ];
    }
}
```

Здесь `mixed` описывает контейнер сериализации с условным MissingValue, а не
отменяет типы доменного контракта. Resource не вызывает `load()` и не ищет автора
самостоятельно. Query-owner заранее загружает связь; `whenLoaded` сохраняет
правильную семантику пропуска ключа для других consumers этого Resource.

## 10. Тот же сценарий в модульной структуре

Логика примера остаётся той же. Меняются файлы, namespace, imports и discovery:

| В стандартном примере | В модульном примере |
|---|---|
| `App\Http\Controllers\ArticleStoreController` | `App\Modules\Publishing\Controllers\ArticleStoreController` |
| `App\Http\Requests\StoreArticleRequest` | `App\Modules\Publishing\Requests\StoreArticleRequest` |
| `App\Http\Resources\ArticleResource` | `App\Modules\Publishing\Resources\ArticleResource` |
| `App\Dto\StoreArticleData` | `App\Modules\Publishing\Dto\StoreArticleData` |
| `App\Actions\StoreArticleAction` | `App\Modules\Publishing\Actions\StoreArticleAction` |
| `App\Tasks\CreateArticleTask` | `App\Modules\Publishing\Tasks\CreateArticleTask` |
| `App\Repositories\ArticleRepository` | `App\Modules\Publishing\Repositories\ArticleRepository` |
| `App\Models\Article` | `App\Modules\Publishing\Models\Article` |
| `Database\Factories\ArticleFactory` | `App\Modules\Publishing\Database\Factories\ArticleFactory` |

`User` может оставаться `App\Models\User` или принадлежать собственному identity
модулю. Не создавайте нового User в каждом бизнес-модуле.

Пример собственного provider без зависимости от module-пакета:

```php
namespace App\Modules\Publishing\Providers;

use Illuminate\Support\ServiceProvider;

final class PublishingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
```

При отсутствии общего loader зарегистрируйте provider принятым способом
установленной версии Laravel. Для Laravel с `bootstrap/providers.php` это запись
класса в возвращаемом массиве. Если loader уже загружает routes/migrations,
не регистрируйте их повторно в provider. Module route file должен явно получать
нужные `api` middleware и prefix: `loadRoutesFrom()` не добавляет их за вас.

Модульной модели с нестандартным factory namespace задайте явную связь
`newFactory()`; пример в [testing.md](testing.md). Ни module provider, ни PSR-4
сами по себе не настраивают Pest suites и Laravel TestCase.

## 11. Processors, события и границы расширения

Когда фильтр переиспользуется, его можно выделить в processor. Не вводите
фиктивный `throughProcessors()` без реализации. Пример собственного invokable
processor, принимающего уже scoped query:

```php
namespace App\Processors;

use App\Models\Article;
use Illuminate\Database\Eloquent\Builder;

final readonly class PublishedArticles
{
    /**
     * @param Builder<Article> $query
     * @return Builder<Article>
     */
    public function __invoke(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
```

В модуле класс располагается в `Publishing/Processors`. Его вызов остаётся
явным: processor получает query, в котором уже установлен actor/tenant scope,
и добавляет условие, не строя новый unscoped `Article::query()`.

Регистрация event/listener принадлежит владельцу события. Listener не должен
выполнять запросы через чужой Controller. Внешняя интеграция живёт за реальной
границей; command/HTTP/job используют один use case с явно переданным контекстом.
Ручная загрузка routes, дополнительные providers и shared helpers не должны
дублировать уже существующие механизмы приложения.

### 11.1. Полная route registration для module provider

Если `PublishingServiceProvider` выше загружает файл самостоятельно и никто
другой не назначает prefix/middleware, содержимое модульного `Routes/api.php`:

```php
use App\Modules\Publishing\Controllers\ArticleIndexController;
use App\Modules\Publishing\Controllers\ArticleStoreController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth'])
    ->prefix('api/articles')
    ->name('articles.')
    ->group(function (): void {
        Route::get('/', ArticleIndexController::class)->name('index');
        Route::post('/', ArticleStoreController::class)->name('store');
    });
```

Если общий loader уже назначил `/api` и middleware, не повторяйте их в этом
файле: два prefixes дадут другой URL, а повторная registration — дубликаты.
Это конкретный пример явного подключения, не требование написать собственный
module loader в каждом приложении.

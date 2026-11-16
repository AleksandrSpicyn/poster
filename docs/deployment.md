# Развёртывание

Push в `main` запускает GitHub Actions: Composer audit, Pint, Rector, PHPStan,
Pest, TypeScript, сборку frontend и Scribe. После проверок CI собирает архив
с production-зависимостями и передаёт его серверу по SSH.

`deploy/server-deploy.sh` включает maintenance mode и обновляет файлы в
`/var/www/poster` через rsync. `.env`, `storage` и `public/storage` сохраняются.
Затем обновляется обнаружение пакетов (`package:discover`), выполняются миграции,
генерация Scribe и кеширование Laravel,
перезапуск Horizon. При завершении скрипта maintenance
mode выключается. На время обновления сайт недоступен.

## Очереди сериалов и восстановление

До включения сериального кабинета примените новые миграции, соберите frontend,
настройте `ADMIN_EMAIL` / `HORIZON_ALLOWED_EMAILS` и отдельный вход Codex для этой
установки. Все файлы `storage/app/private`, включая оригиналы персонажей, дубли,
монтажи и Codex credentials, должны переживать развёртывание. Вход через
`make codex-login` выполняет пользователь; учётная запись хоста не копируется.

Horizon обслуживает `default` (сценарии, генерация и публикации) и отдельную
очередь `render` с одним FFmpeg worker. Timeout монтажа — 3600 секунд, supervisor —
3660, Redis `retry_after` — 3720. Генерация дубля имеет timeout 480 секунд и lock
510 секунд; это включает извлечение кадра и стандартный `CODEX_TIMEOUT=300`.
При увеличении внешних таймаутов согласуйте всю цепочку job → supervisor →
`retry_after` и срок recovery. Для database queue значение `DB_QUEUE_RETRY_AFTER`
по умолчанию также 3720; нужны workers для `default`, `render` и `broadcasts`.
Horizon работает с Redis.

Scheduler раз в минуту запускает ограниченные reconciliation-команды:

```sh
php artisan app:reconcile-episode-scripts --limit=50
php artisan app:reconcile-shot-generations --limit=50
php artisan app:reconcile-episode-assemblies --limit=50
php artisan app:reconcile-publications --limit=50
php artisan app:reconcile-productions --limit=50
```

Они доставляют пропущенные jobs и восстанавливают безопасные шаги. Зависшая
отправка генерации/публикации сохраняет unknown, не повторяя внешний effect.
В кабинете можно проверить известный remote task ID или receipt публикации,
повторить скачивание готового видео и пересобрать локальный монтаж. Явное
закрытие неизвестного дубля требует пояснения и подтверждения возможного расхода;
это не подтверждает отмену задачи у провайдера и не запускает новый дубль.

Для проверки неизвестной публикации YouTube нужен read scope владельца канала.
Его запрашивает **Connect / Reconnect Youtube** в разделе **Channels**
(вместе с правом загрузки); альтернативный CLI-вход —
`php artisan youtube:auth {channel_id} --with-read-verification`.
Существующие токены автоматически не расширяются. Для browser OAuth добавьте
показанный в форме callback URI в allowed redirects приложения провайдера.
Instagram должен получать временный signed export URL по публичному HTTPS.

Резервная копия содержит БД и приватные media. Проверяйте восстановление в
отдельную БД/каталог: canonical pointer эпизода, manifest → media, checksums
и доступ только разрешённому оператору. Откат кода не должен удалять новые
таблицы, receipts или файлы. Платный пилот и внешняя публикация выполняются
отдельно от автоматических тестов.

## Автоматическое производство и WebSocket

Новые выпуски по расписанию выключены по умолчанию:

```dotenv
CONTENT_SCHEDULE_ENABLED=false
CONTENT_SCHEDULE_CRON="0 9 * * *"
CONTENT_SCHEDULE_TIMEZONE=Asia/Bangkok
```

На production расписание включено через `CONTENT_SCHEDULE_ENABLED=true` в
серверном `.env`; запуск — ежедневно в 09:00 по `Asia/Bangkok`.
После изменения флага выполните от пользователя приложения `php artisan config:cache`
и `php artisan horizon:terminate`, чтобы workers перечитали конфигурацию.
Deploy сохраняет `.env`, поэтому флаг переживает обновления приложения.
Проверить расписание можно командой `php artisan schedule:list --timezone=Asia/Bangkok`.

Включение автоматизации в настройках сериального проекта ставит первый выпуск
в очередь. Следующий выпуск запускается по расписанию или явной кнопкой/CLI.
Обычное редактирование включённых настроек не создаёт новые выпуски. Recovery
команды продолжают уже начатые операции независимо от выключенного расписания.

Флаг **Project enabled** действует отдельно от расписания и автоматизации сериала.
Выключенный проект пропускается CLI/планировщиком; активный выпуск останавливается,
а ожидающие этапы проверяют флаг перед генерацией, монтажом или публикацией.
История и настройки сохраняются. Уже отправленный запрос может завершиться;
получение его receipt и скачивание оплаченного дубля остаются доступны.

Horizon имеет отдельный `supervisor-broadcasts` с очередью `broadcasts`, чтобы
статусы не ожидали завершения длинной генерации. Для database queue запустите
отдельный worker `queue:work --queue=broadcasts --timeout=30 --tries=1`.

Локальный Compose запускает `reverb` вместе с web/app; браузер подключается через
Nginx `/app/` на том же origin. Нужные переменные приведены в `.env.docker.example`.
Workers и scheduler не включаются этим сервисом.

На сервере без Docker задайте в `.env`:

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=poster
REVERB_APP_KEY=<random-public-key>
REVERB_APP_SECRET=<random-secret>
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_ALLOWED_ORIGINS=example.com
```

Укажите реальный домен сайта без схемы в `REVERB_ALLOWED_ORIGINS`. В браузер
передаётся только публичный key через Inertia props; секрет не публикуется и
дополнительные `VITE_REVERB_*` для сборки не нужны. Reverb слушает loopback,
а публичное TLS-соединение обслуживает существующий Nginx.

Готовые конфигурации — `deploy/reverb-supervisor.conf` и
`deploy/reverb-nginx.conf`. Сверьте пользователя/пути, установите supervisor
конфигурацию в `/etc/supervisor/conf.d/`, включите Nginx snippet внутри TLS
server блока, выполните `nginx -t`, перечитайте Supervisor и перезагрузите Nginx.
Изменения системных конфигураций требуют административного SSH и не выполняются
ограниченным CI deploy-key. Скрипт deploy после обновления вызывает
`reverb:restart`; Supervisor поднимает процесс с новой конфигурацией.

Проверка: браузер показывает **Live updates** после авторизации приватного канала,
изменение статуса приходит без полной перезагрузки, введённый текст остаётся в форме.
При отключении Reverb показывается переподключение и включается чтение раз в 30 секунд.

## GitHub

Окружение `production` разрешает deploy из `main`. Повторный запуск доступен
через workflow_dispatch. Переменная `PRODUCTION_URL` содержит адрес сайта.
SSH secrets:

- `POSTER_SSH_HOST`, `POSTER_SSH_PORT`, `POSTER_SSH_USER`
- `POSTER_SSH_PRIVATE_KEY`, `POSTER_SSH_KNOWN_HOSTS`

Ключ пользователя `deploy` разрешает только
`/var/www/poster/deploy/server-deploy.sh`. Deploy выполняется без sudo.

## Сервер

Локальный Docker и CI используют MySQL 8.4: миграции и полный набор тестов
проверяются на MySQL до упаковки релиза. Рабочая локальная база — `poster`,
тестовая — `poster_test`; `phpunit.mysql.xml` принудительно задаёт её имя.
SQLite остаётся дополнительной быстрой проверкой и не заменяет MySQL-прогон.
Для JSON-колонок миграции не задают непереносимые строковые SQL defaults;
начальные структуры передаются через DTO/Tasks при создании записей.

Nginx обслуживает `/var/www/poster/public` через PHP 8.4 FPM.
OPcache проверяет изменения файлов при каждом запросе
(`opcache.validate_timestamps=1`, `opcache.revalidate_freq=0`).
Supervisor запускает `poster-horizon`, cron каждую минуту вызывает
`php8.4 artisan schedule:run`. Horizon сохраняет metrics snapshot каждые пять минут.

Очереди используют Redis. Для текущего сервера задан один worker
(`HORIZON_MAX_PROCESSES=1`) и один поток FFmpeg (`FFMPEG_THREADS=1`).

Queues доступен подтверждённым пользователям из `HORIZON_ALLOWED_EMAILS`;
при пустом значении используется `ADMIN_EMAIL`. Logs доступен подтверждённому
владельцу с email `ADMIN_EMAIL`. Почта пока сохраняется через `MAIL_MAILER=log`.

## Codex и подписка ChatGPT

Текстовые промпты и изображения генерирует Laravel AI с пользовательским
провайдером `codex`. Он запускает Codex CLI с входом ChatGPT;
`OPENAI_API_KEY` для этих этапов не требуется и не передаётся дочернему процессу.
Автоматического переключения на платный API нет. Laravel MCP здесь не требуется:
приложение напрямую использует text/image provider Laravel AI.

### Однократная подготовка сервера

До первого запуска генерации установите CLI через административный SSH-доступ.
CI deploy-key ограничен командой deploy и не подходит для установки или входа.
Один установщик используется в Docker и на сервере: он закрепляет Codex CLI
0.169.0, проверяет SHA-256 официального package-архива, поддерживает Linux x86_64
и arm64. Устанавливаются все бинарники из `bin/`, включая `codex-code-mode-host`,
который нужен встроенному инструменту генерации изображений.
Нужны `bash`, `curl`, `tar`, `sha256sum`, `install`; Node.js на сервере не нужен.

```sh
sudo bash /var/www/poster/deploy/install-codex.sh
```

Выполняйте вход от пользователя, который запускает генерацию, а не от root.
В примерах ниже это `deploy`; перед выполнением сверьте пользователя cron/worker
и при необходимости замените имя и группу. Если генерация выполняется несколькими
сервисами, используйте для неё одного системного пользователя с доступом к этому
приватному каталогу.

```sh
sudo install -d -o deploy -g deploy -m 700 /var/www/poster/storage/app/private/codex
sudo -u deploy env CODEX_HOME=/var/www/poster/storage/app/private/codex \
  /usr/local/bin/codex -c 'cli_auth_credentials_store="file"' login --device-auth
```

Откройте показанную CLI ссылку на своём компьютере и завершите вход ChatGPT.
Аккаунт должен разрешать device-code вход. Если он недоступен, используйте
обычный `codex login` через SSH с пробросом порта 1455, сохраняя тот же
`CODEX_HOME` и пользователя. Вход нужен отдельно для сервера и локального
контейнера: не используйте копию рабочего `auth.json` одновременно на двух машинах.

Проверка без генерации изображения и расходования лимитов:

```sh
sudo -u deploy env CODEX_HOME=/var/www/poster/storage/app/private/codex \
  /usr/local/bin/codex -c 'cli_auth_credentials_store="file"' login status
sudo chmod 600 /var/www/poster/storage/app/private/codex/auth.json
```

Ожидаемый результат — `Logged in using ChatGPT`. `auth.json` содержит токены:
храните его только на сервере, не в Git, CI-артефактах или публичном каталоге.
Codex обновляет авторизацию сам; каталог должен оставаться доступным для записи
и сохраняться при деплое. При отзыве или истечении сессии повторите вход.

### Конфигурация и доступ к кадру

По умолчанию приложение использует:

```dotenv
CODEX_BINARY=/usr/local/bin/codex
CODEX_MODEL=gpt-6.1-sol
CODEX_TIMEOUT=300
```

`CODEX_HOME` автоматически указывает на `storage/app/private/codex` текущего
приложения. При переопределении в `.env` используйте тот же абсолютный путь
в командах входа. Все генерации с этим каталогом сериализуются cache lock;
параллельный запуск возвращает ошибку, а не создаёт второй запрос. Сохраняйте
общий Redis/cache store для процессов этого экземпляра приложения.

`CODEX_MODEL` выбирает текстовую модель для промптов; доступ к ней определяется
аккаунтом и подпиской. Доступные модели описаны в [документации Codex](https://learn.chatgpt.com/docs/models?surface=cli).
Текстовый Gateway сохраняет инструкции и историю сообщений, запускается без
инструментов и с sandbox `read-only`. Он поддерживает синхронный обычный текст,
structured output и локальные изображения-референсы. Streaming, tools, документы и sampling options отклоняются явно.
CLI для изображений использует `workspace-write` и встроенный image tool.
Общий `CodexProcess` отвечает за отдельный вход, lock, таймаут, JSON-результат и
очистку временного каталога; ограничения первого кадра проверяет драйвер Poster.

Готовый PNG сохраняется в `storage/app/public/generated-images`. Deployment
выполняет `storage:link`. Убедитесь, что `APP_URL` содержит внешний HTTPS адрес
сервера и Nginx отдаёт `/storage/generated-images/...` без авторизации. Именно этот
URL получает Hailuo или Runway; локальный путь и `localhost` им недоступны. Адаптер
Seedance может передать собственный локальный кадр `generated-images` как Base64,
поэтому для него доступ к первому кадру не зависит от внешней доступности `APP_URL`.

Первый запуск `app:make-short` выполняет полный конвейер, включая платное видео
и публикацию. Для проверки установки достаточно команд версии и `login status`;
полный сценарий запускайте отдельно после настройки остальных интеграций.

CLI 0.169.0 использует встроенную модель `gpt-image-2` и размер `auto`; адаптер
запрашивает композицию 9:16 и проверяет портретный кадр не меньше 720×1280.
Это не гарантия точного соотношения сторон или Images 2.6 в конкретном аккаунте.
Лимиты изображений расходуют общую квоту Codex подписки. CLI хранит оригиналы
в своём приватном каталоге; публичные копии нужны для истории и видеопровайдеров.

Документация OpenAI: [изображения](https://learn.chatgpt.com/docs/image-generation),
[вход](https://learn.chatgpt.com/docs/auth),
[сохранение авторизации](https://learn.chatgpt.com/docs/auth/ci-cd-auth).

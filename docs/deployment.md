# Развёртывание

Push в `main` запускает GitHub Actions: Composer audit, Pint, Rector, PHPStan,
Pest, TypeScript, сборку frontend и Scribe. После проверок CI собирает архив
с production-зависимостями и передаёт его серверу по SSH.

`deploy/server-deploy.sh` включает maintenance mode и обновляет файлы в
`/var/www/poster` через rsync. `.env`, `storage` и `public/storage` сохраняются.
Затем выполняются миграции, генерация Scribe, кеширование Laravel,
перезапуск Horizon. При завершении скрипта maintenance
mode выключается. На время обновления сайт недоступен.

## GitHub

Окружение `production` разрешает deploy из `main`. Повторный запуск доступен
через workflow_dispatch. Переменная `PRODUCTION_URL` содержит адрес сайта.
SSH secrets:

- `POSTER_SSH_HOST`, `POSTER_SSH_PORT`, `POSTER_SSH_USER`
- `POSTER_SSH_PRIVATE_KEY`, `POSTER_SSH_KNOWN_HOSTS`

Ключ пользователя `deploy` разрешает только
`/var/www/poster/deploy/server-deploy.sh`. Deploy выполняется без sudo.

## Сервер

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

Изображения генерирует Laravel AI с пользовательским провайдером `codex`.
Он запускает Codex CLI с входом ChatGPT; `OPENAI_API_KEY` по-прежнему нужен
для текста идеи, но в процесс генерации изображения API-ключи не передаются.
Автоматического переключения на платный API нет. Laravel MCP здесь не требуется:
приложение напрямую использует image provider Laravel AI.

### Однократная подготовка сервера

До первого запуска генерации установите CLI через административный SSH-доступ.
CI deploy-key ограничен командой deploy и не подходит для установки или входа.
Один установщик используется в Docker и на сервере: он закрепляет Codex CLI
0.169.0, проверяет SHA-256 архива, поддерживает Linux x86_64 и arm64.
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
CODEX_TIMEOUT=300
```

`CODEX_HOME` автоматически указывает на `storage/app/private/codex` текущего
приложения. При переопределении в `.env` используйте тот же абсолютный путь
в командах входа. Все генерации с этим каталогом сериализуются cache lock;
параллельный запуск возвращает ошибку, а не создаёт второй запрос. Сохраняйте
общий Redis/cache store для процессов этого экземпляра приложения.

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

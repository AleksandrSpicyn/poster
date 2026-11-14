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

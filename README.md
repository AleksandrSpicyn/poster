# Poster

Laravel-приложение для генерации и публикации коротких видео с публичной
страницей разработчика и личным кабинетом на Vue/Inertia.

## Возможности

- Публичные страницы: профиль, политика, условия использования
- Регистрация, сессии, восстановление пароля и настройки профиля
- Списки prompts и публикаций с сортировкой, фильтрами и пагинацией
- Генерация идеи и изображения через OpenAI, видео через Hailuo/Runway,
  музыки через Suno; сборка ролика через FFmpeg
- Публикация в YouTube и Instagram, уведомления в Telegram
- TikTok и VK пока представлены заглушками: публикация возвращает `false`

## Локальная работа

Docker Compose использует собственные PostgreSQL и Redis, PHP с FFmpeg,
Nginx и Node. Рабочий `.env` с ключами не перезаписывается. Для локального
запуска используется отдельный `.env.docker` по примеру `.env.docker.example`.

```sh
cp .env.docker.example .env.docker
make build
make up
make init
make migrate
make npm-build
```

Приложение: <http://localhost:8088>. Vite: порт 5178 (`make npm-dev`).
Команды выполняются внутри контейнера; `make help` показывает доступные команды.
Ключи интеграций добавляются только в локальный `.env.docker`; без них внешняя
генерация и публикация недоступны. Автоматические worker/scheduler запускаются
отдельным профилем после настройки ключей: `docker compose --profile workers up -d`.

## Структура и правила

`app/Actions` — сценарии, `app/Tasks` — операции, `app/Classes/Generators` и
`app/Classes/Publishers` — внешние интеграции. HTTP-код находится в
`app/Http`, интерфейс — в `resources/js`, проверки — в `tests`.

Инструкции агентам: [AGENTS.md](AGENTS.md). Правила и скиллы импортированы из
[ai-init](https://github.com/AleksandrSpicyn/ai-init), точная версия —
[docs/ai-init-source.json](docs/ai-init-source.json).

План и результаты модернизации: [docs/modernization.md](docs/modernization.md).

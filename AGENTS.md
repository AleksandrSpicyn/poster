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
- Скиллы ai-init находятся в `.agents/skills/et-*/SKILL.md`; источник и commit
  записаны в `docs/ai-init-source.json`. Читайте применимый скилл и его references.
- Pest: короткие русские `test()`, функциональные проверки через реальные routes,
  factories/relations, внешние API через fakes. Не подменяйте внутренние слои,
  когда проверяете полный сценарий. Проверяйте payload, persistence и эффекты.
- PHP: native types, точные generics/array shapes, early return, collection-first
  для преобразований, без ведущего `_` у callback parameters. Сохраняйте контракты.
- Не подавляйте PHPStan blanket ignores/baseline для достижения максимального уровня.
- В тестах запрещены реальные платные API, публикация и уведомления. Не используйте
  рабочую БД; тестовая конфигурация из `phpunit.xml` изолирована.
- Docker Compose проекта изолирован от B2B. Не останавливайте чужие контейнеры,
  не удаляйте volumes. Не переносите секреты и инфраструктурные адреса B2B.
- Проверки и ограничения сообщайте по факту: локальные тесты не доказывают CI/deploy.

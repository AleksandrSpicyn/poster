.DEFAULT_GOAL := help
EXEC = docker compose exec -T --user appuser app
PHP = $(EXEC) php
ARTISAN = $(PHP) artisan
GRAPHIFY ?= graphify
CODEX = $(EXEC) env CODEX_HOME=/var/www/storage/app/private/codex /usr/local/bin/codex -c 'cli_auth_credentials_store="file"'

.PHONY: help build up down status init migrate npm-build npm-dev test test-mysql test-sqlite pint pint-fix stan rector rector-dry docs quality pipe boost ide-helper graph graph-update graph-html codex-login codex-status
help:
	@echo 'build up down status init migrate npm-build npm-dev test pint pint-fix stan rector rector-dry docs quality pipe boost ide-helper'
	@echo 'graph graph-update graph-html (Graphify CLI on host)'
	@echo 'codex-login codex-status (ChatGPT subscription, separate login for this installation)'
	@echo 'test / test-mysql (isolated MySQL poster_test), test-sqlite (fast in-memory checks)'
build:
	docker compose build
up:
	docker compose up -d
down:
	docker compose down
status:
	docker compose ps
init:
	$(EXEC) composer install --no-interaction
	$(EXEC) npm ci --no-fund
	$(EXEC) sh -c 'if grep -q "^APP_KEY=$$" .env.docker; then php artisan key:generate --env=docker --no-interaction; fi'
	$(ARTISAN) storage:link
migrate:
	$(ARTISAN) migrate --no-interaction
npm-build:
	$(EXEC) npm run build
npm-dev:
	$(EXEC) npm run dev -- --host 0.0.0.0 --port 5178
test: test-mysql
test-mysql:
	$(PHP) -d memory_limit=512M vendor/bin/pest --configuration phpunit.mysql.xml --compact
test-sqlite:
	$(PHP) -d memory_limit=512M vendor/bin/pest --compact
pint:
	$(PHP) vendor/bin/pint --test
pint-fix:
	$(PHP) vendor/bin/pint
stan:
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G
rector:
	$(PHP) vendor/bin/rector process
rector-dry:
	$(PHP) vendor/bin/rector process --dry-run
docs:
	$(ARTISAN) scribe:generate
ide-helper:
	$(ARTISAN) ide-helper:generate --no-interaction
boost:
	$(ARTISAN) boost:install
graph graph-update:
	$(GRAPHIFY) extract . --code-only --max-workers 2
	$(GRAPHIFY) cluster-only . --no-label
graph-html:
	$(GRAPHIFY) export html
codex-login:
	$(EXEC) install -d -m 700 /var/www/storage/app/private/codex
	$(CODEX) login --device-auth
codex-status:
	$(CODEX) login status
quality: pint rector-dry stan
pipe: quality test docs npm-build

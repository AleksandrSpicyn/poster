.DEFAULT_GOAL := help
EXEC = docker compose exec -T --user appuser app
PHP = $(EXEC) php
ARTISAN = $(PHP) artisan

.PHONY: help build up down status init migrate npm-build npm-dev test pint pint-fix stan rector rector-dry docs quality pipe boost
help:
	@echo 'build up down status init migrate npm-build npm-dev test pint pint-fix stan rector rector-dry docs quality pipe boost'
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
	$(ARTISAN) storage:link
migrate:
	$(ARTISAN) migrate --no-interaction
npm-build:
	$(EXEC) npm run build
npm-dev:
	$(EXEC) npm run dev -- --host 0.0.0.0 --port 5178
test:
	$(PHP) vendor/bin/pest --compact
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
boost:
	$(ARTISAN) boost:install
quality: pint rector-dry stan
pipe: quality test docs npm-build

# Prefer Compose V2 plugin (GitHub Actions / modern Docker Desktop); fall back to docker-compose V1 (REQ-MAKE-010).
COMPOSE_BIN := $(shell docker compose version >/dev/null 2>&1 && echo "docker compose" || echo "docker-compose")
dc = $(SHELL) -c '$(COMPOSE_BIN) $(1)'

.PHONY: help up down build install shell logs test test-coverage cache-clear update-bundle ensure-up restart setup-db assets-install assets

BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/../..)
export DOCKER_CONFIG := $(BUNDLE_ROOT)/.docker
SERVICE_PHP := php
PNPM := pnpm

help:
	@echo "Symfony 8 Demo - Device Intelligence Bundle"
	@echo ""
	@echo "  make up             Start containers"
	@echo "  make down           Stop containers"
	@echo "  make restart        Restart containers"
	@echo "  make build          Build images (no cache)"
	@echo "  make install        Install Composer + pnpm and build Vite assets"
	@echo "  make assets         pnpm install + Vite build (Pentatrion)"
	@echo "  make assets-install Publish bundle IIFE to public/bundles/"
	@echo "  make setup-db       Create database and schema"
	@echo "  make shell          Open shell in PHP container"
	@echo "  make test           Run tests"
	@echo "  make update-bundle  Refresh bundle autoload, assets, and cache"

# REQ-DEMO-005: canonical up — Waiting…, Installing…, Demo started at: from PORT
up:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	@$(call dc,up -d --build)
	@echo "Waiting for container to be ready..."
	@sleep 5
	@echo "Installing dependencies..."
	@$(call dc,exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction)
	@$(MAKE) assets-install
	@$(MAKE) assets
	@$(MAKE) setup-db
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	echo "Demo started at: http://localhost:$$PORT"

down:
	@$(call dc,down)

restart: ensure-up
	@$(call dc,restart)

build:
	@$(call dc,build --no-cache)

ensure-up:
	@if ! $(call dc,exec -T php true) >/dev/null 2>&1; then \
		echo "Starting container..."; \
		$(call dc,up -d --build); \
		sleep 8; \
		$(call dc,exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction); \
		$(MAKE) assets-install; \
		$(MAKE) assets; \
	fi

install: ensure-up
	@$(call dc,exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction)
	@$(MAKE) assets-install
	@$(MAKE) assets

assets-install: ensure-up
	@$(call dc,exec -T php php bin/console assets:install public --symlink --relative --no-interaction)

assets: ensure-up
	@$(call dc,exec -T php pnpm install)
	@$(call dc,exec -T php pnpm rebuild esbuild)
	@$(call dc,exec -T php pnpm run build)
	@echo "Restarting FrankenPHP worker so Pentatrion reloads entrypoints.json..."
	@$(call dc,restart php)
	@sleep 3

setup-db: ensure-up
	@$(call dc,exec -T php php bin/console doctrine:database:create --if-not-exists --no-interaction) || true
	@$(call dc,exec -T php php bin/console doctrine:schema:update --force --no-interaction) || true

shell: ensure-up
	@$(call dc,exec php sh)

logs:
	@$(call dc,logs -f)

# REQ-DEMO-007: sync mounted bundle before tests
test: ensure-up update-bundle setup-db
	@$(call dc,exec -e APP_ENV=test php composer test)

test-coverage: ensure-up setup-db
	@$(call dc,exec -e APP_ENV=test php composer test-coverage)

cache-clear: ensure-up
	@$(call dc,exec php php bin/console cache:clear)

update-bundle: ensure-up
	@$(call dc,exec php composer dump-autoload --optimize)
	@$(MAKE) assets-install
	@$(MAKE) assets
	@$(call dc,exec php php bin/console cache:clear --env=dev --no-warmup) || true
	@echo "Bundle updated. Reload the page in the browser."

# REQ-MAKE-008: update-deps (REQ-MAKE-008)
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-update-deps.mk

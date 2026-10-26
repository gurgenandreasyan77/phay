SHELL := /bin/bash
.DEFAULT_GOAL := help

# local-* : your machine, compose.yaml + .env (+ api/.env for Laravel).
# prod-*  : the server, compose.prod.yaml + .env.prod. The two never share a
#           compose project, env file or volume.
LOCAL    := docker compose
PROD_ENV ?= .env.prod
PROD     := docker compose --env-file $(PROD_ENV) -f compose.prod.yaml
# Artisan in prod runs as www-data, the owner of storage/ (logs, uploads).
PROD_ART := $(PROD) exec -u www-data backend php artisan

TS := $$(date +%Y%m%d-%H%M%S)

# A value from an env file, with a default: $(call env_get,KEY,file,default)
env_get = $$(v=$$(sed -n 's/^$(1)=//p' $(2) 2>/dev/null | tail -n1); echo "$${v:-$(3)}")
LOCAL_DB_USER = $(call env_get,POSTGRES_USER,.env,phay)
LOCAL_DB_NAME = $(call env_get,POSTGRES_DB,.env,phay)
PROD_DB_USER  = $(call env_get,POSTGRES_USER,$(PROD_ENV),phay)
PROD_DB_NAME  = $(call env_get,POSTGRES_DB,$(PROD_ENV),phay)

.PHONY: help \
	local-setup local-composer local-vendor local-key local-build local-up local-down local-restart \
	local-logs local-ps local-migrate local-migrate-status local-fresh \
	local-site-keys local-bootstrap local-artisan local-shell local-shell-admin \
	local-test local-test-backend local-test-admin local-backup local-restore \
	prod-check prod-init prod-key prod-config prod-build prod-up prod-down prod-restart \
	prod-logs prod-logs-app prod-ps prod-migrate prod-migrate-status prod-bootstrap \
	prod-site-keys prod-artisan prod-shell prod-deploy prod-backup prod-backup-prune \
	prod-restore prod-restore-storage

help: ## This list
	@grep -E '^[a-zA-Z0-9_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-22s\033[0m %s\n", $$1, $$2}'

# ===========================================================================
# LOCAL
# ===========================================================================

local-setup: ## First start on a new machine: env files, vendor, images, APP_KEY, migrations
	[ -f .env ] || cp .env.example .env
	[ -f api/.env ] || cp api/.env.example api/.env
	$(MAKE) local-vendor
	$(LOCAL) build
	$(LOCAL) up -d
	grep -qE '^APP_KEY=.+' api/.env || $(MAKE) local-key
	$(MAKE) local-migrate
	@echo ""
	@echo "Phay is up. Admin: http://localhost:$(call env_get,ADMIN_PORT,.env,3001)  API: http://localhost:$(call env_get,API_PORT,.env,8080)/api/health"
	@echo "First super admin: make local-bootstrap email=you@example.com name=\"Your Name\""

local-composer: ## composer install into api/vendor (the dev image has no composer)
	docker run --rm -v "$(CURDIR)/api:/app" -w /app composer:2 composer install --ignore-platform-reqs --no-interaction

# api/ is bind-mounted into the backend, so vendor/ has to exist on the host:
# a fresh clone (or a pulled composer.lock) needs it before artisan can start.
local-vendor: ## composer install only when api/vendor is missing or older than composer.lock
	[ -f api/vendor/autoload.php ] && [ api/vendor/autoload.php -nt api/composer.lock ] || $(MAKE) local-composer

local-key: ## Generate APP_KEY into api/.env (only for a new local database)
	$(LOCAL) exec backend php artisan key:generate --force

local-build: ## Rebuild local images
	$(LOCAL) build

local-up: local-vendor ## Start the local stack
	$(LOCAL) up -d

local-down: ## Stop the local stack (data stays in volumes)
	$(LOCAL) down

local-restart: ## Restart all, or one: make local-restart s=backend
	$(LOCAL) restart $(s)

local-logs: ## Follow logs, all or one: make local-logs s=backend
	$(LOCAL) logs -f --tail=200 $(s)

local-ps: ## Containers and their state
	$(LOCAL) ps

local-migrate: ## Run new migrations
	$(LOCAL) exec backend php artisan migrate --force

local-migrate-status: ## Which migrations ran
	$(LOCAL) exec backend php artisan migrate:status

local-fresh: ## DROP all local tables and migrate again (local only)
	$(LOCAL) exec backend php artisan migrate:fresh --force

local-site-keys: ## Connected sites with their X-Site-Key and webhook
	$(LOCAL) exec backend php artisan platform:site:list

local-bootstrap: ## First super admin: make local-bootstrap email=you@example.com name="Your Name"
	@[ -n "$(email)" ] || (echo 'Usage: make local-bootstrap email=you@example.com name="Your Name"' && exit 1)
	$(LOCAL) exec backend php artisan platform:bootstrap "$(email)" --name="$(or $(name),Admin)"

local-artisan: ## Any artisan command: make local-artisan cmd="route:list"
	$(LOCAL) exec backend php artisan $(cmd)

local-shell: ## Shell in the backend container
	$(LOCAL) exec backend sh

local-shell-admin: ## Shell in the admin container
	$(LOCAL) exec admin sh

local-test: local-test-backend local-test-admin ## Backend tests + admin lint and typecheck

local-test-backend: local-vendor ## PHPUnit (in-memory SQLite, never touches the local database)
	$(LOCAL) exec backend php artisan test

# `npm run typecheck` drops .next/types first: route types left by an old
# `next build` would otherwise fail tsc.
local-test-admin: ## Admin eslint + tsc
	$(LOCAL) exec admin npm run lint
	$(LOCAL) exec admin npm run typecheck

local-backup: ## Dump the local database to backups/phay-local-*.sql
	mkdir -p backups
	$(LOCAL) exec -T postgres pg_dump --no-owner --no-acl -U $(LOCAL_DB_USER) $(LOCAL_DB_NAME) > backups/phay-local-$(TS).sql
	@echo "Backup written to backups/"

# Into an empty database: right after the first local-up, before local-migrate.
local-restore: ## Restore a dump into an EMPTY local database: make local-restore file=backups/x.sql
	@[ -n "$(file)" ] || (echo "Usage: make local-restore file=backups/phay-local-XXXX.sql" && exit 1)
	$(LOCAL) exec -T postgres psql -v ON_ERROR_STOP=1 -U $(LOCAL_DB_USER) -d $(LOCAL_DB_NAME) < $(file)

# ===========================================================================
# PRODUCTION
# ===========================================================================

prod-check:
	@[ -f $(PROD_ENV) ] || (echo "No $(PROD_ENV). Run: make prod-init (see docs/DEPLOY.md)" && exit 1)

prod-init: ## Create .env.prod from .env.prod.example with a new APP_KEY and DB password
	@[ ! -f $(PROD_ENV) ] || (echo "$(PROD_ENV) already exists, not touching it" && exit 1)
	cp .env.prod.example $(PROD_ENV)
	chmod 600 $(PROD_ENV)
	sed -i.bak "s|^APP_KEY=.*|APP_KEY=base64:$$(openssl rand -base64 32)|" $(PROD_ENV)
	sed -i.bak "s|^POSTGRES_PASSWORD=.*|POSTGRES_PASSWORD=$$(openssl rand -hex 24)|" $(PROD_ENV)
	rm -f $(PROD_ENV).bak
	@echo "$(PROD_ENV) created. Fill MAIL_*, ANTHROPIC_API_KEY, GOOGLE_* ... then: make prod-build prod-up"

prod-key: ## Print a new APP_KEY (only for a NEW database)
	@echo "base64:$$(openssl rand -base64 32)"

prod-config: prod-check ## Validate compose.prod.yaml with .env.prod
	$(PROD) config --quiet && echo "compose.prod.yaml + $(PROD_ENV): OK"

prod-build: prod-check ## Build production images
	$(PROD) build

prod-up: prod-check ## Start (or update to freshly built images)
	$(PROD) up -d --remove-orphans

prod-down: prod-check ## Stop production (data stays in volumes)
	$(PROD) down

prod-restart: prod-check ## Restart all, or one: make prod-restart s=backend
	$(PROD) restart $(s)

prod-logs: prod-check ## Container logs, all or one: make prod-logs s=admin
	$(PROD) logs -f --tail=200 $(s)

prod-logs-app: prod-check ## Laravel's own log (storage/logs)
	$(PROD) exec backend sh -c 'tail -n 200 -f storage/logs/laravel*.log'

prod-ps: prod-check ## Containers and their health
	$(PROD) ps

prod-migrate: prod-check ## Run new migrations
	$(PROD_ART) migrate --force

prod-migrate-status: prod-check ## Which migrations ran
	$(PROD_ART) migrate:status

prod-bootstrap: prod-check ## First super admin: make prod-bootstrap email=you@phay.am name="Your Name"
	@[ -n "$(email)" ] || (echo 'Usage: make prod-bootstrap email=you@phay.am name="Your Name"' && exit 1)
	$(PROD_ART) platform:bootstrap "$(email)" --name="$(or $(name),Admin)"

prod-site-keys: prod-check ## Connected sites with their X-Site-Key and webhook
	$(PROD_ART) platform:site:list

prod-artisan: prod-check ## Any artisan command: make prod-artisan cmd="about"
	$(PROD_ART) $(cmd)

prod-shell: prod-check ## Shell in the backend container
	$(PROD) exec backend sh

prod-deploy: prod-check ## Update: backup, git pull, build, up, migrate
	$(MAKE) prod-backup
	git pull --ff-only
	$(MAKE) prod-build
	$(MAKE) prod-up
	$(MAKE) prod-migrate
	$(PROD) ps

prod-backup: prod-check ## Database + uploaded files to backups/ (gzip)
	mkdir -p backups
	chmod 700 backups
	set -o pipefail; $(PROD) exec -T postgres pg_dump --no-owner --no-acl -U $(PROD_DB_USER) $(PROD_DB_NAME) | gzip > backups/phay-db-$(TS).sql.gz
	$(PROD) exec -T api tar -C /var/www/html/public/storage -czf - . > backups/phay-storage-$(TS).tar.gz
	@ls -lh backups | tail -n 4

prod-backup-prune: ## Delete backups older than 14 days (days=30 to change)
	find backups -name 'phay-*' -type f -mtime +$(or $(days),14) -print -delete

# Replaces the whole database: the app is stopped, the schema dropped and the
# dump loaded. Take a prod-backup first if the current data may be needed.
prod-restore: prod-check ## Replace the DB with a dump: make prod-restore file=backups/phay-db-X.sql.gz confirm=yes
	@[ -n "$(file)" ] || (echo "Usage: make prod-restore file=backups/phay-db-XXXX.sql.gz confirm=yes" && exit 1)
	@[ "$(confirm)" = "yes" ] || (echo "This REPLACES the production database. Add confirm=yes" && exit 1)
	$(PROD) stop admin api scheduler backend
	$(PROD) exec -T postgres psql -v ON_ERROR_STOP=1 -U $(PROD_DB_USER) -d $(PROD_DB_NAME) -c 'DROP SCHEMA public CASCADE; CREATE SCHEMA public;'
	set -o pipefail; if [[ "$(file)" == *.gz ]]; then gunzip -c "$(file)"; else cat "$(file)"; fi | $(PROD) exec -T postgres psql -v ON_ERROR_STOP=1 -U $(PROD_DB_USER) -d $(PROD_DB_NAME)
	$(PROD) up -d

prod-restore-storage: prod-check ## Unpack uploaded files: make prod-restore-storage file=backups/phay-storage-X.tar.gz
	@[ -n "$(file)" ] || (echo "Usage: make prod-restore-storage file=backups/phay-storage-XXXX.tar.gz" && exit 1)
	$(PROD) exec -T -u www-data backend tar -C /var/www/html/storage/app/public -xzf - < $(file)

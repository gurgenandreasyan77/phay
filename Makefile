SHELL := /bin/bash
PROD := -f compose.prod.yaml

.PHONY: setup up down restart logs ps \
	migrate migrate-status seed seed-servio fresh key-generate site-keys \
	test test-backend test-admin lint typecheck \
	build prod-build prod-up prod-down prod-logs prod-migrate \
	shell-backend shell-admin artisan \
	backup-db restore-db

## First-time setup: env files, dev images, an empty platform ready to use.
setup:
	[ -f .env ] || cp .env.example .env
	[ -f api/.env ] || cp api/.env.example api/.env
	docker compose build
	docker compose up -d
	$(MAKE) migrate
	@echo "Phay is up. Admin: http://localhost:$${ADMIN_PORT:-3001}  API: http://localhost:$${API_PORT:-8080}/api/health"
	@echo "First super admin: make artisan cmd='platform:bootstrap you@example.com --name=\"Your Name\"'"

## Start/stop the dev stack.
up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart

logs:
	docker compose logs -f

ps:
	docker compose ps

## Database.
migrate:
	docker compose exec backend php artisan migrate --force

migrate-status:
	docker compose exec backend php artisan migrate:status

# The platform itself needs no seeded content.
seed:
	docker compose exec backend php artisan db:seed --force

# Servio's catalog, posts and rates into the default business: a realistic
# business to work on locally.
seed-servio:
	docker compose exec backend php artisan db:seed --class=ServioDemoSeeder --force

fresh:
	docker compose exec backend php artisan migrate:fresh --force

## Connected sites with their X-Site-Key (the site's NEXT_PUBLIC_SITE_KEY).
site-keys:
	docker compose exec backend php artisan platform:site:list

key-generate:
	docker compose exec backend php artisan key:generate --show

## Tests and checks.
test-backend:
	docker compose exec backend php artisan test

# `npm run typecheck` drops .next/types first: route types left by an old
# `next build` (e.g. for pages that have since moved) would otherwise fail tsc.
test-admin:
	docker compose exec admin npm run lint
	docker compose exec admin npm run typecheck

test: test-backend test-admin

lint:
	docker compose exec admin npm run lint

typecheck:
	docker compose exec admin npm run typecheck

## Production. `build` is a plain alias for `prod-build` — `up`/`down`/`logs`
## intentionally keep their dev meaning above; use `prod-up`/`prod-down`/
## `prod-logs` for those in production.
build: prod-build

prod-build:
	docker compose $(PROD) build

prod-up:
	docker compose $(PROD) up -d

prod-down:
	docker compose $(PROD) down

prod-logs:
	docker compose $(PROD) logs -f

prod-migrate:
	docker compose $(PROD) exec backend php artisan migrate --force

## Shells and ad-hoc artisan commands (usage: make artisan cmd="queue:work").
shell-backend:
	docker compose exec backend sh

shell-admin:
	docker compose exec admin sh

artisan:
	docker compose exec backend php artisan $(cmd)

## Backup / restore PostgreSQL (dumps to ./backups, which git ignores).
## Dumps carry no owner or grants, so they restore under any DB user.
backup-db:
	mkdir -p backups
	docker compose exec -T postgres pg_dump --no-owner --no-acl -U $${POSTGRES_USER:-phay} $${POSTGRES_DB:-phay} > backups/phay-$$(date +%Y%m%d-%H%M%S).sql
	@echo "Backup written to backups/"

# Into an empty database (right after the first `make up`, before `make migrate`).
restore-db:
	[ -n "$(file)" ] || (echo "Usage: make restore-db file=backups/phay-XXXXXXXX.sql" && exit 1)
	docker compose exec -T postgres psql -v ON_ERROR_STOP=1 -U $${POSTGRES_USER:-phay} -d $${POSTGRES_DB:-phay} < $(file)

SHELL := /bin/bash
PROD := -f compose.prod.yaml

.PHONY: setup up down restart logs ps \
	migrate migrate-status seed fresh key-generate \
	test test-backend test-frontend test-admin lint typecheck \
	build prod-build prod-up prod-down prod-logs prod-migrate \
	shell-backend shell-frontend shell-admin artisan \
	backup-dbmak restore-db

## First-time setup: env files, dev images, database ready to use.
setup:
	[ -f .env ] || cp .env.example .env
	[ -f servio-back/.env ] || cp servio-back/.env.example servio-back/.env
	[ -f servio-front/.env.local ] || cp servio-front/.env.example servio-front/.env.local
	docker compose build
	docker compose up -d
	$(MAKE) migrate
	$(MAKE) seed
	@echo "Servio is up. Frontend: http://localhost:$${FRONTEND_PORT:-3000}  Admin: http://localhost:$${ADMIN_PORT:-3001}  API: http://localhost:$${API_PORT:-8080}/api/health"

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

seed:
	docker compose exec backend php artisan db:seed --force

fresh:
	docker compose exec backend php artisan migrate:fresh --seed --force

key-generate:
	docker compose exec backend php artisan key:generate --show

## Tests and checks.
test-backend:
	docker compose exec backend php artisan test

test-frontend:
	docker compose exec frontend npm run lint
	docker compose exec frontend npx tsc --noEmit

test-admin:
	docker compose exec admin npm run lint
	docker compose exec admin npx tsc --noEmit

test: test-backend test-frontend test-admin

lint:
	docker compose exec frontend npm run lint
	docker compose exec admin npm run lint

typecheck:
	docker compose exec frontend npx tsc --noEmit
	docker compose exec admin npx tsc --noEmit

## Production. `build` is a plain alias for `prod-build` — `up`/`down`/`logs`
## intentionally keep their dev meaning above; use `prod-up`/`prod-down`/
## `prod-logs` for those in production, same as before.
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

shell-frontend:
	docker compose exec frontend sh

shell-admin:
	docker compose exec admin sh

artisan:
	docker compose exec backend php artisan $(cmd)

## Backup / restore PostgreSQL (dumps to ./backups).
backup-db:
	mkdir -p backups
	docker compose exec -T postgres pg_dump -U $${POSTGRES_USER:-servio} $${POSTGRES_DB:-servio} > backups/servio-$$(date +%Y%m%d-%H%M%S).sql
	@echo "Backup written to backups/"

restore-db:
	[ -n "$(file)" ] || (echo "Usage: make restore-db file=backups/servio-XXXXXXXX.sql" && exit 1)
	docker compose exec -T postgres psql -U $${POSTGRES_USER:-servio} -d $${POSTGRES_DB:-servio} < $(file)

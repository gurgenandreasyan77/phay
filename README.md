# Servio

Corporate website for **Servio** — a professional cleaning and upholstery
dry-cleaning service in Armenia. Trilingual (Armenian / Russian / English),
with a lead-capture form backed by a small REST API.

## Architecture

```
servio/
├── frontend/            Next.js 16 (App Router, TypeScript strict) — the public site
├── backend/              Laravel 13 API-only — services catalog + lead intake
├── docker/
│   ├── nginx/            Dev nginx config (proxies to php-fpm)
│   ├── php/               Shared php.ini / opcache / fpm-pool config (dev + prod)
│   └── production/        Prod nginx config, baked into the "nginx" build target
├── deploy/
│   └── servio.nginx.conf  Example HOST-level nginx config for the domain + SSL
├── compose.yaml            Dev stack
├── compose.prod.yaml       Prod stack
├── Makefile                 make setup / up / test / prod-up / ...
└── .env.example              Compose-level env vars (ports, DB creds, shared URLs)
```

Two independent apps, each with its own `.env.example`:

- **frontend/** — the Next.js app. `NEXT_PUBLIC_*` vars are read by the browser;
  `NEXT_INTERNAL_API_URL` is server-only, used for Server Component data
  fetching over the Docker network.
- **backend/** — the Laravel API. Serves `/api/v1/categories`, `/api/v1/services`,
  `/api/v1/services/{slug}`, `POST /api/v1/leads`, and `/api/health`.

In Docker, the Laravel API is actually two containers: **backend** (PHP-FPM,
no HTTP of its own) and **api** (nginx, serves `backend/public` and proxies
`*.php` to `backend:9000`). The frontend talks to `api`, not `backend`,
directly.

## Tech stack

| Layer | Choice | Notes |
| --- | --- | --- |
| Frontend | Next.js 16.3, React 19.2, TypeScript 5.9 (strict) | App Router, Turbopack, `output: standalone` |
| Styling | Tailwind CSS 4 | CSS-first `@theme` tokens in `frontend/src/app/globals.css` |
| i18n | next-intl 4 | Locale-prefixed routing (`/hy`, `/ru`, `/en`), `hy` is default |
| Forms | react-hook-form + zod | Client-side validation mirrors the API's rules |
| Backend | Laravel 13, PHP 8.3 | API-only, no Blade/Vite scaffolding |
| Database | PostgreSQL 17 | |
| Infra | Docker Compose, nginx, PHP-FPM | Separate dev/prod Dockerfile targets |

## Requirements

- Docker + Docker Compose v2
- `make` (optional but recommended — see [Makefile](Makefile) for the raw
  `docker compose` commands if you don't have it)

Nothing else needs to be installed on the host — Node, PHP, and Postgres all
run inside containers.

## Quick start (local development)

```bash
git clone <repo-url> servio && cd servio
make setup
```

`make setup` will:
1. copy `.env.example` → `.env` (root), `backend/.env.example` → `backend/.env`,
   `frontend/.env.example` → `frontend/.env.local` (only if they don't already exist)
2. build the dev images
3. start the stack
4. run migrations and seed the database with the initial service catalog

When it finishes:
- Frontend: http://localhost:3000 (redirects to `/hy`)
- API health check: http://localhost:8080/api/health
- API: http://localhost:8080/api/v1/categories

If you'd rather run the steps yourself:

```bash
cp .env.example .env
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env.local
docker compose build
docker compose up -d
make migrate
make seed
```

### Everyday commands

```bash
make up            # start the dev stack
make down           # stop it
make logs            # tail all container logs
make migrate          # run new migrations
make fresh              # drop everything, re-migrate, re-seed
make test               # backend Pest/PHPUnit tests + frontend lint/typecheck
make test-backend        # php artisan test
make test-frontend        # eslint + tsc --noEmit
make shell-backend         # shell into the backend container
make artisan cmd="queue:work"  # run an arbitrary artisan command
```

## Environment variables

### Root `.env` (drives `compose.yaml` / `compose.prod.yaml`)

| Variable | Purpose |
| --- | --- |
| `FRONTEND_PORT`, `API_PORT`, `POSTGRES_PORT` | Host ports — change if they collide with another project on the same server |
| `POSTGRES_DB` / `POSTGRES_USER` / `POSTGRES_PASSWORD` | Database credentials |
| `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL` | Laravel core settings. Generate `APP_KEY` with `make key-generate` |
| `LEADS_RATE_LIMIT_PER_MINUTE` | Rate limit on `POST /api/v1/leads`, per IP |
| `CORS_ALLOWED_ORIGINS` | Comma-separated origins allowed to call the API from a browser |
| `NEXT_PUBLIC_SITE_URL` | Public site URL — canonical links, sitemap, JSON-LD. **In production this must be set in `.env` *before* running `docker compose -f compose.prod.yaml build`** — Next.js inlines `NEXT_PUBLIC_*` vars into the compiled bundle at build time, so setting it only for `up`/at runtime has no effect (the build itself fails with a clear error if it's unset or still points at `localhost`) |
| `NEXT_PUBLIC_API_URL` | Browser-facing API base URL |
| `NEXT_INTERNAL_API_URL` | Server-only API base URL (Docker-internal); defaults to `http://api/api/v1` in-container |
| `NEXT_PUBLIC_CONTACT_PHONE` / `_EMAIL` / `_CITY` | Contact details shown site-wide — **placeholders, replace before launch** |
| `NEXT_PUBLIC_INSTAGRAM_URL` / `_FACEBOOK_URL` / `_WHATSAPP_URL` / `_TELEGRAM_URL` / `_VIBER_URL` | Optional — fallback for the footer/contacts page when no matching admin-managed contact channel exists |
| `NEXT_PUBLIC_GA_ID` | Optional — Google Analytics only loads when set |

`backend/.env.example` and `frontend/.env.example` document the same
variables from each app's own point of view (useful if you ever run either
app outside Docker).

## Testing

```bash
make test-backend     # 19 PHPUnit feature tests: leads, validation,
                        # rate limiting, honeypot, service catalog, health check
make test-frontend      # eslint + tsc --noEmit
cd frontend && npm run build   # full production build (also type-checks)
```

Backend tests run against an **in-memory SQLite** database
(`backend/phpunit.xml`), completely isolated from the Postgres dev database —
`RefreshDatabase` never touches your local data. If you ever see PHPUnit
`<env>`/`<server>` warnings after upgrading PHPUnit, keep `force="true"` on
every DB_* entry: inside Docker, `DB_CONNECTION=pgsql` etc. are already real
container environment variables, and PHPUnit's `<env>` alone won't override
an existing `$_SERVER` value — `<server force="true">` is what actually wins.

## Production build & deployment

### 1. Build and smoke-test the images locally

```bash
docker compose -f compose.prod.yaml build
```

### 2. Prepare the server (Ubuntu)

```bash
# Docker Engine + Compose plugin
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER   # log out/in after this

# nginx + certbot, for the host-level reverse proxy and SSL
sudo apt update
sudo apt install -y nginx certbot python3-certbot-nginx
```

### 3. Copy the repo and configure `.env`

```bash
git clone <repo-url> /opt/servio && cd /opt/servio
cp .env.example .env
```

Edit `.env` and set real values for at least:

- `POSTGRES_PASSWORD` — a strong password
- `APP_KEY` — generate one:
  `docker compose -f compose.prod.yaml run --rm backend php artisan key:generate --show`
- `APP_URL` — `https://servio.am` (or your real domain)
- `CORS_ALLOWED_ORIGINS` — `https://servio.am`
- `NEXT_PUBLIC_SITE_URL` — `https://servio.am`
- `NEXT_PUBLIC_API_URL` — `https://servio.am/api/v1` (same-origin through the
  host nginx config below — see `servio-deploy/servio.nginx.conf`)
- the `NEXT_PUBLIC_CONTACT_*` and social vars — see [Placeholders](#placeholders-to-replace-before-launch)

### 4. Start the stack and initialize the database

```bash
docker compose -f compose.prod.yaml up -d --build
docker compose -f compose.prod.yaml exec backend php artisan migrate --force
docker compose -f compose.prod.yaml exec backend php artisan db:seed --force
```

Postgres is **not** exposed to the host in production — only `api` (on
`API_PORT`) and `frontend` (on `FRONTEND_PORT`) are.

### 5. Point the domain at it — host nginx + SSL

Copy `servio-deploy/servio.nginx.conf` to `/etc/nginx/sites-available/servio.am`,
adjust the domain and upstream ports if you changed them, then:

```bash
sudo ln -s /etc/nginx/sites-available/servio.am /etc/nginx/sites-enabled/
sudo mkdir -p /var/www/certbot
sudo nginx -t && sudo systemctl reload nginx

# Get a certificate (certbot edits the 443 server block for you)
sudo certbot --nginx -d servio.am -d www.servio.am

sudo systemctl reload nginx
```

The frontend calls `/api/v1/...` on its own origin — `servio-deploy/servio.nginx.conf`
forwards `/api/` to the `api` container untouched (no CORS involved in
production; CORS only matters for local dev where the ports differ).

### Updating the site without losing data

```bash
cd /opt/servio
git pull
docker compose -f compose.prod.yaml build
docker compose -f compose.prod.yaml up -d
docker compose -f compose.prod.yaml exec backend php artisan migrate --force
```

The Postgres data volume (`postgres_data`) is untouched by rebuilds/restarts —
it's only removed if you explicitly run `docker compose ... down -v`, which
you should never do in production.

### Rollback

```bash
git checkout <previous-tag-or-commit>
docker compose -f compose.prod.yaml build
docker compose -f compose.prod.yaml up -d
```

If the rollback also needs to undo a migration:
`docker compose -f compose.prod.yaml exec backend php artisan migrate:rollback --force`

### Logs & troubleshooting

```bash
docker compose -f compose.prod.yaml logs -f              # everything
docker compose -f compose.prod.yaml logs -f backend        # just Laravel
docker compose -f compose.prod.yaml ps                      # health status of every container
docker compose -f compose.prod.yaml exec backend php artisan tinker   # inspect data
```

Common issues:
- **502 from the host nginx** — check `docker compose -f compose.prod.yaml ps`;
  the `api`/`frontend` containers need to be `healthy`, not just `running`.
- **API returns CORS errors in dev** — check `CORS_ALLOWED_ORIGINS` in the
  root `.env` matches the frontend's actual origin.
- **Frontend shows stale service data after an API change** — in dev, this is
  the Next.js fetch cache (`revalidate: 3600`); `docker compose restart frontend`
  or wait an hour. In prod this resolves itself the same way.

## Database: migrations, seeders, backup, restore

```bash
make migrate         # run pending migrations
make migrate-status    # what's been run
make seed                # (re-)run seeders — safe to re-run, uses updateOrCreate
make fresh                 # drop everything + migrate + seed (dev only!)
```

### Backup

```bash
make backup-db
# writes ./backups/servio-YYYYMMDD-HHMMSS.sql
```

### Restore

```bash
make restore-db file=backups/servio-20260101-120000.sql
```

Both targets work against whichever stack is currently up (`compose.yaml` by
default — pass `-f compose.prod.yaml` manually, or adapt the Makefile
targets, if restoring in production).

## Extending the site

### Adding a new language

1. Add the locale to `frontend/src/i18n/routing.ts` (`locales` array).
2. Add `frontend/messages/<locale>.json` with the same key structure as
   `hy.json` (the canonical shape used for TypeScript message types — see
   `frontend/global.d.ts`).
3. Add the locale's flag/label to `LanguageSwitcher` (`frontend/src/components/layout/LanguageSwitcher.tsx`).
4. On the backend, add the locale to `App\Enums\Locale` and to the JSON
   translation blobs on `service_categories`/`services` (via a new seeder run
   or an admin update once one exists) and to `preferred_locale` validation
   in `StoreLeadRequest`.
5. Add the corresponding `lang/<locale>/validation.php` for localized backend
   validation messages (copy `lang/en/validation.php` as a starting point).

### Adding a new service

Add an entry to `backend/database/seeders/ServiceSeeder.php` (or, once an
admin panel exists, create it there) with `category_id`, `slug`, `icon`, and
the `name`/`short_description`/`description` JSON blobs for all three
locales, then `make seed`. Pick an `icon` value that has a mapping in
`frontend/src/lib/icons.tsx` (`serviceIcons`), or add a new mapping there.

### Adding a new service category

Same idea, in `ServiceCategorySeeder.php`. The homepage and `/services` page
pick up new categories automatically — no frontend code changes needed.

### Replacing the logo

See `frontend/public/logo/README.md`. In short: drop the real SVG/PNG files
into `frontend/public/logo/` and swap the text wordmark in
`frontend/src/components/Logo.tsx` for an `<Image>` — the component's props
stay the same so `Header`/`Footer` don't need any changes.

### Replacing photos

See `frontend/public/images/README.md` for the exact filenames, recommended
sizes, and which section each one feeds. Nothing breaks if a file is
missing — sections fall back to the on-brand SVG illustration / placeholder
component until real photos are added.

## Placeholders to replace before launch

| What | Where | Current value |
| --- | --- | --- |
| Domain | `servio-deploy/servio.nginx.conf`, `.env` (`APP_URL`, `NEXT_PUBLIC_SITE_URL`, `CORS_ALLOWED_ORIGINS`) | `servio.am` |
| Phone | `.env` (`NEXT_PUBLIC_CONTACT_PHONE`) | `+374 55 337633` |
| Email | `.env` (`NEXT_PUBLIC_CONTACT_EMAIL`) | `info@servio.am` |
| City/address | `.env` (`NEXT_PUBLIC_CONTACT_CITY`) | `Yerevan, Armenia` |
| Social links | `.env` (`NEXT_PUBLIC_INSTAGRAM_URL`, `_FACEBOOK_URL`, `_WHATSAPP_URL`, `_TELEGRAM_URL`, `_VIBER_URL`) | empty (hidden until set) |
| Logo | `frontend/public/logo/` | text wordmark |
| Photos | `frontend/public/images/` | SVG illustration / placeholder boxes |
| Legal details (company name/registration, if required) | `frontend/messages/*.json` → `Privacy`/`Terms` sections | generic, non-legal-advice text |
| Analytics ID | `.env` (`NEXT_PUBLIC_GA_ID`) | empty (analytics disabled until set) |

## What's next

- Admin panel for managing services/categories/lead statuses (the backend's
  service layer and Sanctum are already in place for this — see
  `app/Services/LeadService.php`, `App\Enums\LeadStatus`)
- Email/Telegram/CRM notification on new leads (hook into `LeadService::create`)
- Real photography (see `frontend/public/images/README.md`)
- Additional service categories (plumbing, electrical, AC repair — the data
  model already supports this without schema changes)

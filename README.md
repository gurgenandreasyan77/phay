# Servio

Corporate website for **Servio** — a professional cleaning and upholstery
dry-cleaning service in Armenia. Trilingual (Armenian / Russian / English),
with a lead-capture form backed by a small REST API.

## Architecture

```
servio/
├── servio-front/         Next.js 16 (App Router, TypeScript strict) — the public site
├── servio-admin/         Next.js 16 — the admin panel (own app, own port)
├── servio-back/          Laravel 13 API — catalog, leads, admin API
├── servio-docker/
│   ├── nginx/            Dev nginx config (proxies to php-fpm)
│   ├── php/               Shared php.ini / opcache / fpm-pool config (dev + prod)
│   └── production/        Prod nginx config, baked into the "nginx" build target
├── servio-deploy/
│   └── servio.nginx.conf  Example HOST-level nginx config for the domains + SSL
├── compose.yaml            Dev stack
├── compose.prod.yaml       Prod stack
├── Makefile                 make setup / up / test / prod-up / ...
└── .env.example              Compose-level env vars (ports, DB creds, shared URLs)
```

Three independent apps, each with its own `.env.example`:

- **servio-admin/** — the admin panel. Talks only to the Laravel admin API
  (server-side, token in an httpOnly cookie) and, after each edit, asks the
  public site to refresh its cache via `POST /api/revalidate` with the shared
  `REVALIDATE_SECRET`. See `servio-admin/README.md`.
- **servio-front/** — the public Next.js site. `/admin/*` redirects to `ADMIN_URL`. `NEXT_PUBLIC_*` vars are read by the browser;
  `NEXT_INTERNAL_API_URL` is server-only, used for Server Component data
  fetching over the Docker network.
- **backend/** — the Laravel API. Serves `/api/v1/categories`, `/api/v1/services`,
  `/api/v1/services/{slug}`, `POST /api/v1/leads`, and `/api/health`.
  It is multi-tenant: see [Businesses and sites](#businesses-and-sites).

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
4. run migrations and seed the database with Servio's catalog (`ServioDemoSeeder`)

Then put the site key into the root `.env` (the site gets 401 without it) and
restart the frontend:

```bash
make site-keys                      # copy the X-Site-Key of servio.am
# .env: NEXT_PUBLIC_SITE_KEY=site_pk_...
docker compose up -d frontend
```

When it finishes:
- Frontend: http://localhost:3000 (redirects to `/hy`)
- Admin: http://localhost:3001 (redirects to `/admin`)
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
| `ANTHROPIC_API_KEY` | Production: key for the AI assistant in each business's admin. In development put it in `servio-back/.env` instead. Without it the assistant page says it is not connected |
| `ASSISTANT_MONTHLY_LIMIT` | AI assistant questions per business per month (default 100) |

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
# The first super admin: prints a one-time link (1 hour) to set a password, then 2FA.
docker compose -f compose.prod.yaml exec backend php artisan platform:bootstrap you@servio.am --name="Your Name"
```

Postgres is **not** exposed to the host in production — only `api` (on
`API_PORT`), `frontend` (on `FRONTEND_PORT`) and `admin` (on `ADMIN_PORT`) are.

Production also requires `ADMIN_URL` (e.g. `https://admin.servio.am`) and a
random `REVALIDATE_SECRET` (`openssl rand -hex 32`) in the root `.env`.

### 5. Point the domain at it — host nginx + SSL

Copy `servio-deploy/servio.nginx.conf` to `/etc/nginx/sites-available/servio.am`,
adjust the domain and upstream ports if you changed them, then:

```bash
sudo ln -s /etc/nginx/sites-available/servio.am /etc/nginx/sites-enabled/
sudo mkdir -p /var/www/certbot
sudo nginx -t && sudo systemctl reload nginx

# Get a certificate (certbot edits the 443 server block for you)
sudo certbot --nginx -d servio.am -d www.servio.am -d admin.servio.am

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

## Businesses and sites

The API serves several businesses (tenants) from one database. Every content
row (services, products, leads, settings, feature flags...) belongs to one
business, and every query is limited to the current one automatically
(`BelongsToTenant` trait).

- **Public API:** the `X-Site-Key` header picks the business and is required
  (no key: 401). `make site-keys` prints every site's key; the site gets it as
  `NEXT_PUBLIC_SITE_KEY`. CORS allows each active site's origins (its listed
  origins, else its domain with and without `www`) on top of
  `CORS_ALLOWED_ORIGINS`, so a new site needs no deploy.
- **Cache refresh:** after an edit Laravel sends the affected cache tags to each
  of the business's sites (`revalidate_url` + secret, set on Platform → Sites;
  `App\Sites\SiteRevalidator`), after the response has gone out.
- **Admin API:** the signed-in user's business (`tenant_user`). A super admin
  without a business works on the default one.

The admin has two levels:

- **Platform** (`/admin/platform`, super admin only; where a super admin
  lands after signing in): **Բիզնեսներ** lists every business with its
  **Մուտքեր** (logins: name, email, password shown once to hand over),
  **Մոդուլներ** (optional modules: Dry cleaning, Auto Care, Market, per
  business) and **Մտնել** (work inside that business). **Կայքեր** lists the
  sites of every business with their keys (copy, rotate, allowed origins,
  on/off) and connects a new site to a chosen business.
- **Business** (`/admin`): what an owner always sees, and a super admin after
  **Մտնել** or the business switcher in the top bar (with a "← Պլատֆորմ" link
  back): dashboard, orders, services, settings, plus the modules this business
  has on. **Ծառայություններ** is the same for every business (name,
  description, price, photo, status); trade-specific screens such as the
  upholstery calculator live in a module. Sales import, analytics and an AI assistant are next (shown as
  "soon" to a super admin only).

An owner signs in to the same panel and sees only their business. Platform
endpoints answer 403 to an owner, not just a hidden menu. A new business
starts with every optional module off. A suspended business locks its owners
out.

If your own login isn't a super admin (no "← Պլատֆորմ" link in the menu, /admin/platform is 404):

```bash
make artisan cmd="platform:super-admin you@example.com"
```

### Sign-in security

- **No admin password in any .env or seeder.** The very first super admin comes
  from `php artisan platform:bootstrap you@servio.am` (refuses once a super
  admin exists): it prints a one-time link (`BOOTSTRAP_TTL_MINUTES`, 60) to
  `ADMIN_URL/admin/invite#token`, where the person sets their own password.
- **Everyone else is invited.** Staff create a login with name, email and role
  only; the person gets an invitation link (emailed, and shown to staff to pass
  on by messenger; 72 h, one time) and sets their own password. A forgotten
  password: "Forgot password?" on the sign-in page, or a reset link from staff
  (1 h). A reset signs out every device and doesn't skip 2FA.
- **2FA (TOTP)** with any authenticator app plus 8 one-time recovery codes.
  Required for platform staff and business owners (`TWO_FACTOR_REQUIRED_FOR_OWNERS`);
  an owner can require it for their whole team. Until it's set up the API
  answers 403 `two_factor_setup_required` and the admin opens `/admin/setup-2fa`.
  Lost phone: staff reset it on the logins list; the last resort is
  `php artisan platform:two-factor:reset email`.
- 2FA secrets are encrypted with `APP_KEY`: back the key up apart from the
  database and never change it on a live database (rotate with `APP_PREVIOUS_KEYS`).
- Email and phone are confirmed with 6-digit codes on "Իմ հաշիվը" (mail via
  `MAIL_*`, SMS via `SMS_DRIVER=log|twilio`; in development both go to
  `storage/logs/laravel.log`).
- Sessions last 7 days (`SANCTUM_EXPIRATION`), are listed on "Իմ հաշիվը" with
  their device and IP, and can be signed out one by one.
- Every sign-in, failed attempt, 2FA/password change and invitation is in
  `security_events` (platform → "Անվտանգության մատյան").

To connect servio-front (or a copy of it for another business), put the key
in its env: `NEXT_PUBLIC_SITE_KEY=site_pk_...` (root `.env` for Docker; a
production build needs it at build time, like every `NEXT_PUBLIC_*`). It is
sent as `X-Site-Key` on every API call, including the lead forms.

The same from the console:

```bash
make artisan cmd="platform:tenant:create 'Test Shop' --owner=you@example.com"
make artisan cmd="platform:site:create test-shop shop.am --domain=shop.am --origin=https://shop.am"
# prints: Site key: site_pk_...
curl -H "X-Site-Key: site_pk_..." localhost:8080/api/v1/services   # [] for a new business
```

The site key is publishable (the browser needs it for the lead form), like a
Stripe publishable key. `--origin` limits which browser origins may use it;
without it any origin may.

### Analytics and the AI assistant

Every business's admin has **Վերլուծություն** (analytics) and **AI օգնական**
(AI assistant), built on that business's own orders:

- An order counts on the day it was created; revenue counts on the day it was
  completed, from its **final price** (the «Վերջնական գին» column in
  Պատվերներ). Completed orders without a price are flagged on the page.
- The assistant (`POST /api/v1/admin/assistant`) sends the model a snapshot of
  the business's services, its 30-day and 365-day figures and its last 30
  orders without client names or phones, plus the conversation. It uses
  `claude-opus-5-5` through the official `anthropic-ai/sdk`, with server-side
  fallbacks (`fallbacks: "default"`) for declined requests. Each business has
  a monthly question limit; usage and tokens are kept in `assistant_usages`.
- Settings (`servio-back/.env`): `ANTHROPIC_API_KEY`, `ASSISTANT_MONTHLY_LIMIT`,
  and optionally `ASSISTANT_MODEL`, `ASSISTANT_EFFORT` (default `low`),
  `ASSISTANT_MAX_TOKENS`.

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

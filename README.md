# Phay

Phay is a CRM and business assistant for small businesses: orders and sales
tables, imports, analytics, expenses, employees, a calendar, an AI assistant
and subscriptions. Each business is a tenant. A business can also connect its
own public website, which reads its catalog and sends leads through the
public API.

Servio (servio.am) is one of those businesses. Its website lives in its own
repository and talks to Phay over HTTP, like any other site.

```
phay/
├── api/        Laravel 13 API: public site API (/api/v1/*) and admin API (/api/v1/admin/*)
├── admin/      Next.js 16 admin panel (app.phay.am), UI kit in admin/src/ui
├── docker/     PHP and nginx configs used by the images
├── deploy/     phay.nginx.conf: host nginx for phay.am, app.phay.am, api.phay.am
├── compose.yaml        development stack
└── compose.prod.yaml   production stack
```

## How it fits together

```
browser ──► admin (:3001)  ──► api nginx (:8080) ──► backend (php-fpm) ──► postgres
                                      ▲
business websites (servio.am, ...) ───┘  X-Site-Key header
```

- **admin** talks only to the admin API, through its own `/api/admin/*` route
  handlers (the token stays in an httpOnly cookie).
- **Business websites** call the public API with their site key in
  `X-Site-Key`. Without a key the API answers 401.
- **CORS** allows each active site's origins (its listed origins, else its
  domain with and without `www`) on top of `CORS_ALLOWED_ORIGINS`, so a new
  site needs no deploy.
- **Cache refresh:** after an edit Laravel sends the affected cache tags to
  each of the business's sites (webhook URL and secret set on Platform →
  Sites, `App\Sites\SiteRevalidator`), after the response has gone out.

The stack is its own Docker Compose project (`phay`) with its own network,
database and volumes, so a site's stack (for example `servio`) runs next to it
on the same machine without clashing. A site in another compose project
reaches the API at `http://host.docker.internal:8080`.

## Local development

Requirements: Docker with Compose. Nothing else.

```bash
git clone git@github.com:gurgenandreasyan77/phay.git && cd phay
make setup        # copies .env files, builds, starts, generates APP_KEY, migrates
make artisan cmd="platform:bootstrap you@example.com --name='Your Name'"
```

The last command prints a one-time link where you set the first super admin's
password.

- Admin: http://localhost:3001
- API: http://localhost:8080/api/v1/health

Useful commands:

```bash
make up / make down / make logs
make migrate
make seed-servio     # demo catalog of Servio (services, calculator, market)
make site-keys       # X-Site-Key of every site
make test            # backend tests + admin lint and typecheck
make backup-db       # backups/phay-YYYYMMDD-HHMMSS.sql (not in git)
make restore-db file=backups/....sql   # into an empty database, before migrate
```

After changing backend code run `docker compose restart backend`. After
changing admin dependencies or config run `rm -rf admin/.next` and
`docker compose restart admin`.

## Connecting a website

1. Platform → Businesses: create the business (or use an existing one).
2. Platform → Sites: add a site for it with its domain. Copy the key.
3. In the website's env: `NEXT_PUBLIC_SITE_KEY=site_pk_...` and the API URL
   (`https://api.phay.am/api/v1` in production,
   `http://host.docker.internal:8080/api/v1` from another local compose project).
4. Optional: set the site's revalidation webhook (for a Next.js site
   `https://site.am/api/revalidate`) and its secret, then press
   «Ստուգել webhook-ը». The secret must match the site's `REVALIDATE_SECRET`.

The same from the console:

```bash
make artisan cmd="platform:tenant:create 'Test Shop' --owner=you@example.com"
make artisan cmd="platform:site:create test-shop shop.am --domain=shop.am --origin=https://shop.am"
make artisan cmd="platform:site:list"
```

The site key is publishable (the browser needs it for the lead form), like a
Stripe publishable key.

## Environment variables

Root `.env` drives both compose files. `api/.env.example` documents the same
values for running Laravel outside Docker.

| Variable | Purpose |
| --- | --- |
| `ADMIN_PORT`, `API_PORT`, `POSTGRES_PORT` | Host ports |
| `POSTGRES_DB` / `POSTGRES_USER` / `POSTGRES_PASSWORD` | Database |
| `APP_KEY`, `APP_URL`, `APP_DEBUG` | Laravel core. Back `APP_KEY` up apart from the database: 2FA secrets and site webhook secrets are encrypted with it |
| `ADMIN_URL` | Where invitation and reset links point (`https://app.phay.am`) |
| `CORS_ALLOWED_ORIGINS` | Extra browser origins; site origins are added automatically |
| `MAIL_*`, `SMS_DRIVER`, `TWILIO_*` | Invitations, codes, password resets |
| `ANTHROPIC_API_KEY`, `ASSISTANT_*` | AI assistant |
| `GOOGLE_CLIENT_ID` / `_SECRET` / `GOOGLE_REDIRECT_URI` | Google Calendar sync |
| `PLATFORM_DEFAULT_TENANT` | Slug of the business a super admin works on by default (`servio`) |

## Production (phay.am)

Three hostnames on one server, all pointing at it in DNS:

- `app.phay.am`: the admin
- `api.phay.am`: the API
- `phay.am`: redirects to `app.phay.am` for now

```bash
git clone git@github.com:gurgenandreasyan77/phay.git /opt/phay && cd /opt/phay
cp .env.example .env          # set APP_ENV=production, APP_DEBUG=false, APP_URL=https://api.phay.am,
                              # ADMIN_URL=https://app.phay.am, strong POSTGRES_PASSWORD, mail, keys
docker compose -f compose.prod.yaml build
docker compose -f compose.prod.yaml up -d
docker compose -f compose.prod.yaml exec backend php artisan key:generate --force   # only on first install
docker compose -f compose.prod.yaml exec backend php artisan migrate --force
docker compose -f compose.prod.yaml exec backend php artisan platform:bootstrap you@phay.am --name="Your Name"
```

Uploaded files live in the `public_storage` volume, shared by the backend
(writes) and the API nginx (serves `/storage/*`), so they survive rebuilds.

Host nginx and SSL:

```bash
sudo cp deploy/phay.nginx.conf /etc/nginx/sites-available/phay.am
sudo ln -s /etc/nginx/sites-available/phay.am /etc/nginx/sites-enabled/
sudo certbot certonly --nginx -d phay.am -d app.phay.am -d api.phay.am
sudo nginx -t && sudo systemctl reload nginx
```

Updating:

```bash
docker compose -f compose.prod.yaml exec -T postgres pg_dump --no-owner --no-acl -U phay phay > backups/phay-$(date +%Y%m%d-%H%M%S).sql
git pull
make prod-build prod-up prod-migrate
```

Moving uploads from another host (for example after the split from the servio
repository): copy `storage/app/public` into the volume, then rewrite the stored
absolute URLs:

```bash
docker compose -f compose.prod.yaml exec backend php artisan platform:media:rehost https://servio.am https://api.phay.am --dry-run
```

## Sign-in security

- No admin password in any `.env` or seeder. The first super admin comes from
  `platform:bootstrap` (refuses once one exists) with a one-time link.
- Everyone else is invited by email (72 h link) and sets their own password.
- 2FA (TOTP) with recovery codes, required for platform staff and business
  owners. Last resort: `php artisan platform:two-factor:reset email`.
- Sessions last 7 days and are listed on «Իմ հաշիվը». Security events are on
  Platform → «Անվտանգության մատյան».

## Testing

```bash
make test-backend   # PHPUnit on in-memory SQLite, never touches the dev database
make test-admin     # eslint + tsc
```

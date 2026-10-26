# Phay

Phay is a CRM and business assistant for small businesses: orders and sales
tables, imports, analytics, expenses, employees, a calendar, an AI assistant
and subscriptions. Each business is a tenant. A business can also connect its
own public website, which reads its catalog and sends leads through the
public API.

Each business website lives in its own repository and talks to Phay over
HTTP with its site key.

```
phay/
├── api/        Laravel 13 API: public site API (/api/v1/*) and admin API (/api/v1/admin/*)
├── admin/      Next.js 16 admin panel (app.phay.am), UI kit in admin/src/ui
├── docker/     PHP and nginx configs used by the images
├── deploy/     host nginx: phay.nginx.conf, tls-params.conf, acme.nginx.conf (first certificates)
├── docs/       DEPLOY.md: local and production, step by step
├── compose.yaml        development stack
└── compose.prod.yaml   production stack
```

## How it fits together

```
browser ──► admin (:3001)  ──► api nginx (:8080) ──► backend (php-fpm) ──► postgres
                                      ▲                     │
business websites ────────────────────┘  X-Site-Key header  └──► media (Chromium)
```

- **admin** talks only to the admin API, through its own `/api/admin/*` route
  handlers (the token stays in an httpOnly cookie).
- **Business websites** call the public API with their site key in
  `X-Site-Key`. Without a key the API answers 401.
- **CORS** allows each active site's origins (its listed origins, else its
  domain with and without `www`) on top of `CORS_ALLOWED_ORIGINS`, so a new
  site needs no deploy.
- **media** (`media/server.js`) renders the AI agents' designs (an HTML page
  each) to PNG for the backend, with `MEDIA_TOKEN`. JavaScript is off and every
  request the page makes is refused; it is reached only on the compose network.
  Without `MEDIA_URL` the agents give briefs instead of pictures.
- **Cache refresh:** after an edit Laravel sends the affected cache tags to
  each of the business's sites (webhook URL and secret set on Platform →
  Sites, `App\Sites\SiteRevalidator`), after the response has gone out.

The stack is its own Docker Compose project (`phay`) with its own network,
database and volumes, so a business site's stack runs next to it
on the same machine without clashing. A site in another compose project
reaches the API at `http://host.docker.internal:8080`.

## Running it

Full step-by-step guide (in Russian): **[docs/DEPLOY.md](docs/DEPLOY.md)**:
server preparation (Docker, firewall, nginx,
certificates), production and local setup, every env variable and where to
get it, the first super admin, backups, updates, rollback.

| | Local | Production |
| --- | --- | --- |
| Env files | `.env` (compose: ports, DB) + `api/.env` (Laravel) | `.env.prod` (everything, `make prod-init`) |
| Compose | `compose.yaml`, project `phay` | `compose.prod.yaml`, project `phay-prod` |
| Commands | `make local-setup`, `local-up`, `local-down`, `local-migrate`, `local-test`, `local-backup` ... | `make prod-build`, `prod-up`, `prod-migrate`, `prod-deploy`, `prod-backup` ... |

Quick local start:

```bash
make local-setup                                   # env files, vendor, images, APP_KEY, migrations
make local-bootstrap email=you@example.com name="Your Name"   # one-time link for the first super admin
```

- Admin: http://localhost:3001
- API: http://localhost:8080/api/health

Run `make` for every command. After changing backend code run
`make local-restart s=backend`; after changing admin dependencies
`rm -rf admin/.next` and `make local-restart s=admin`.

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
make local-artisan cmd="platform:tenant:create 'Test Shop' --owner=you@example.com"
make local-artisan cmd="platform:site:create test-shop shop.am --domain=shop.am --origin=https://shop.am"
make local-site-keys
```

The site key is publishable (the browser needs it for the lead form), like a
Stripe publishable key.

## Environment variables

Local: compose values in `.env.example`, Laravel values in `api/.env.example`.
Production: `.env.prod.example`. Each key is commented there; the table with
where to get every value is in `docs/DEPLOY.md`, section 7. Back `APP_KEY` up
apart from the database: 2FA secrets, Google tokens and site webhook secrets
are encrypted with it.

## Production

`app.phay.am` (admin), `api.phay.am` (API), `phay.am` (redirects to the admin
for now); business sites can run next to it on the same server. Ports are published on
127.0.0.1 only, the host nginx (`deploy/phay.nginx.conf`) terminates TLS.
Besides php-fpm a `scheduler` container runs `schedule:work` (subscription
warnings, session pruning). See `docs/DEPLOY.md`.

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
make local-test-backend   # PHPUnit on in-memory SQLite, never touches the dev database
make local-test-admin     # eslint + tsc
```

## Brand assets

`admin/src/app/icon.png` (favicon and app icon of the admin) is a
**placeholder**: a plain "P" on the primary colour. Replace it with the real
Phay logo at the same path and size (512×512 PNG); nothing else refers to it.

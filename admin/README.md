# servio-admin

Admin panel for Servio, as a standalone Next.js 16 app (it used to live in
`servio-front/src/app/admin`). It talks only to the Laravel API
(`servio-back`, `/api/v1/admin/*`) and never shares code or cache with the
public site.

- Dev: `make up` from the repo root, then http://localhost:3001 (redirects to `/admin`).
- Without Docker: `npm ci && npm run dev` (port 3001), with `NEXT_INTERNAL_API_URL`
  pointing at the API (e.g. `http://localhost:8080/api/v1`).

## How it fits together

```
browser ──► servio-admin (:3001)
              ├── /admin/*      Server Components, auth via httpOnly cookie `admin_token`
              └── /api/admin/*  Route Handlers: forward to Laravel with the bearer token
```

The admin knows nothing about the businesses' sites. After an edit Laravel
itself sends the affected cache tags to each site's webhook (set per site on
Platform → Sites, see `App\Sites\SiteRevalidator`).

## Environment

| Variable | Purpose |
|---|---|
| `NEXT_INTERNAL_API_URL` | Laravel API base, e.g. `http://api/api/v1` inside Docker |

Design system and product notes: `DESIGN.md`, `PRODUCT.md`.

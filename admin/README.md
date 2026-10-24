# Phay admin

The admin panel of Phay, a standalone Next.js 16 app. It talks only to the
Laravel API (`../api`, `/api/v1/admin/*`) and knows nothing about the
businesses' websites.

- Dev: `make up` from the repo root, then http://localhost:3001 (redirects to `/admin`).
- Without Docker: `npm ci && npm run dev` (port 3001), with `NEXT_INTERNAL_API_URL`
  pointing at the API (e.g. `http://localhost:8080/api/v1`).
- UI kit: `src/ui` (import from `@/ui`, dialogs from `@/ui/dialog/...`).

## How it fits together

```
browser ──► admin (:3001)
              ├── /admin/*      Server Components, auth via httpOnly cookie `admin_token`
              └── /api/admin/*  Route Handlers: forward to Laravel with the bearer token
```

After an edit Laravel itself sends the affected cache tags to each site's
webhook (set per site on Platform → Sites, see `App\Sites\SiteRevalidator`).

## Environment

| Variable | Purpose |
|---|---|
| `NEXT_INTERNAL_API_URL` | Laravel API base, e.g. `http://api/api/v1` inside Docker |

Design system and product notes: `DESIGN.md`, `PRODUCT.md`.

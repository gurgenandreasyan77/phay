# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Operators/dispatchers at Servio (a cleaning/upholstery-care business in Yerevan) who manage leads, orders, services, and store content. Primarily desktop, but the admin is also opened from phone/tablet while working in the field.

## Product Purpose

Internal admin tool that is evolving into a sellable, white-label CRM product for service businesses in general — not just Servio. Today it covers: dashboard overview, leads/orders management, services and additional-services pricing, auto-care content (gallery/videos/service categories), shop products, market categories, SEO task pages, and site settings (address, contact channels).

## Positioning

A calm, professional B2B SaaS CRM — not an "AI app" look (no neon/purple gradients, glow, heavy glassmorphism). Built to be relabeled and resold to different companies, so no visual element should be tied to one specific business identity.

## Operating Context

Used daily, for extended stretches, by non-technical staff who need to scan lists and statuses quickly. Currently Armenian-only UI, outside the public site's i18n tree. Auth via `admin_token` httpOnly cookie + Sanctum, validated server-side per Server Component.

## Capabilities and Constraints

Existing, backend-supported modules only (Laravel API under `App\Http\Controllers\Api\V1\Admin\*`): Dashboard, Orders/Leads, Services (+ additional services, calculator settings), Auto-care (gallery, videos, service categories/services), Products (shop), Market categories, SEO task pages, Settings (address, contact channels).

No backend yet for: Clients (CRM contacts distinct from leads), Employees, Tasks, Calendar, Payments, Expenses, Documents, Analytics, Automations, Roles/permissions, Audit log. These are explicitly out of scope for implementation right now — they may appear in the sidebar as visibly disabled "coming soon" placeholders so the intended CRM structure is legible, but no fake working screens, data, or backend calls for them.

White-label requirement: every color/spacing/radius token must live in CSS variables / design tokens, never hardcoded inside components, so the accent color, logo, product name, contact info, and favicon can later be swapped per tenant without touching component code.

## Brand Commitments

None tied to a single business — Servio's current teal accent is explicitly being replaced by a new, reusable design-system palette (recorded in DESIGN.md) rather than preserved as brand identity.

## Evidence on Hand

Existing implementation: `admin/src/app/admin/**` (Next.js App Router, Tailwind, tokens in `src/app/admin/admin.css`). Backend: `api/app/Http/Controllers/Api/V1/Admin/*`. Treated as functional/content authority; visual styling is being replaced, not preserved.

## Product Principles

1. Clarity and scanability over decoration — built for repeated daily use by non-technical operators, not a showcase.
2. White-label by construction: no per-business values hardcoded in components; everything themeable via tokens.
3. One consistent design system across every screen — no section gets its own visual style.
4. Only ship UI for capabilities the backend actually has; future CRM structure is signaled, never faked.

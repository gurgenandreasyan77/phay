---
name: Phay Admin
description: Calm, white-label B2B operations console for service businesses
colors:
  background: "#F4F6F5"
  surface: "#FFFFFF"
  surface-secondary: "#EAF0EF"
  sidebar: "#0B2E36"
  sidebar-hover: "#153D46"
  sidebar-text: "#D6E4E4"
  sidebar-text-muted: "#8CA4A7"
  primary: "#0F4C5C"
  primary-hover: "#0B3D4A"
  primary-light: "#E0EEEE"
  text-primary: "#1C2628"
  text-secondary: "#68716D"
  text-muted: "#929A96"
  border: "#DAE1E0"
  border-strong: "#C5CFCD"
  success: "#3F7658"
  success-light: "#E5F1E9"
  warning: "#B98232"
  warning-light: "#F8EEDC"
  danger: "#B85D5D"
  danger-light: "#F7E6E5"
  info: "#527589"
  info-light: "#E5EEF2"
  pending: "#88715C"
  pending-light: "#F1EBE5"
typography:
  body:
    fontFamily: "ui-sans-serif, system-ui, 'Segoe UI', sans-serif"
    fontSize: "14px"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "ui-sans-serif, system-ui, 'Segoe UI', sans-serif"
    fontSize: "12px"
    fontWeight: 500
    lineHeight: 1.3
rounded:
  sm: "6px"
  md: "8px"
  lg: "10px"
  xl: "14px"
spacing:
  sm: "8px"
  md: "16px"
  lg: "24px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "#FFFFFF"
    rounded: "{rounded.md}"
    height: "40px"
    padding: "0 16px"
  button-primary-hover:
    backgroundColor: "{colors.primary-hover}"
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.text-primary}"
    rounded: "{rounded.md}"
    height: "40px"
    padding: "0 16px"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.text-primary}"
    rounded: "{rounded.md}"
    height: "40px"
    padding: "0 12px"
  card:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.lg}"
    padding: "20px"
---

# Design System: Phay Admin

## Overview

**Creative North Star: "The Instrument Panel"**

Phay Admin is being built as a white-label CRM product, not a single business's branded tool — the visual system has to work for any service company it gets relabeled for, so nothing in it is allowed to read as "AI-generated SaaS" or as one company's personality. It is an Operate-mode surface: an operator opens it for hours a day to scan leads, prices, and content, so clarity and low visual noise outrank expression. The system explicitly rejects the current-generation AI-app default — purple/blue gradients, neon glow, heavy glassmorphism, oversized rounded corners, loud status pills — in favor of a quieter instrument-panel language: a dark, informationally-neutral sidebar frames a warm, paper-light work surface, and a single muted green stands in for "this matters" wherever it appears.

**Key Characteristics:**
- Warm light ground, not cold gray or pure white, so long sessions don't feel clinical.
- One accent color, used sparingly, never decoratively.
- Flat surfaces separated by hairline borders, not shadows.
- Compact, information-dense tables and forms over card grids.
- Status communicated by small, muted-background badges, never saturated color blocks.

## Colors

The palette is deliberately low-saturation: every role color is a muted, desaturated tone so the one true accent (primary lagoon) still reads as "the important one" when it appears.

### Primary
- **Deep Lagoon** (`#0F4C5C`, the logo's base color): the only color used for primary actions, the active sidebar indicator, selected-row state, and headline KPI numbers. Hover state `#0B3D4A`; `#E0EEEE` is its light tint for soft highlight panels and badges.

### Neutral
- **Cool Paper** (`#F4F6F5`): page background.
- **Card White** (`#FFFFFF`): surface for cards, tables, inputs.
- **Soft Sage** (`#ECEFEB`): secondary surface — table header rows, hover states, inline highlight panels.
- **Graphite** (`#202623`): primary text.
- **Stone** (`#68716D`): secondary text, descriptions.
- **Ash** (`#929A96`): placeholder and disabled text.
- **Hairline** (`#DCE1DD`) / **Hairline Strong** (`#C8CEC9`): all borders and dividers.

### Status (semantic, not primary)
- **Success** (`#3F7658` / bg `#E5F1E9`): completed, published, positive deltas.
- **Warning** (`#B98232` / bg `#F8EEDC`): needs attention, pending review.
- **Danger** (`#B85D5D` / bg `#F7E6E5`): cancelled, destructive confirmation only — never a standing toolbar color.
- **Info** (`#527589` / bg `#E5EEF2`): neutral informational state (e.g. "new").
- **Pending** (`#88715C` / bg `#F1EBE5`): in-contact / awaiting-input states.

### Named Rules
**The One Accent Rule.** The primary green is reserved for primary actions, the active nav indicator, selected rows, and headline metrics. It never fills a whole card, a whole table row, or a decorative background.

**The Quiet Danger Rule.** Destructive actions (delete) render as a neutral secondary button by default; red is earned only inside an explicit confirmation step, never worn as a standing toolbar color.

## Typography

**Body/UI Font:** `ui-sans-serif, system-ui, "Segoe UI", sans-serif` (no custom webfont — the admin's primary content is Armenian, which falls back to the platform sans regardless of any Latin display face, so the system font is the honest choice rather than a cosmetic one).

**Character:** Plain, legible, and structural — type carries almost no personality of its own; hierarchy comes from size/weight steps and spacing, not voice.

### Hierarchy
- **Title** (600, 20px): page titles (`PageHeader`).
- **Section heading** (600, 16px): card/section titles (`SectionCard`).
- **Body** (400, 14px): table cells, form values, nav labels.
- **Label** (500, 12px, uppercase for sidebar group headers only): field labels, table headers, sidebar group labels.

## Layout

Fixed 256px dark sidebar + fluid content column, content padded 16–32px depending on breakpoint. A 64px sticky top bar carries breadcrumbs, disabled search/notification affordances, and the user menu. Below `lg`, the sidebar collapses to an off-canvas drawer triggered from the top bar; content reflows to a single column and KPI/table grids drop from 4–8 columns to 1–2. No fixed-width desktop grid: content uses `max-width`-free fluid layout so it holds at both 1280px and ultra-wide monitors.

## Elevation & Depth

Flat by default. Cards and tables are separated from the page by a 1px hairline border (`#DCE1DD`), not shadow. The only shadow in the system is a soft `0 12px 28px -12px rgba(32,38,35,0.22)` reserved for genuinely floating surfaces — the user-menu dropdown and the mobile nav drawer.

### Named Rules
**The Border-Not-Shadow Rule.** Resting surfaces (cards, tables, inputs) separate from the page with a 1px border. Shadow is reserved for things that float above content (menus, drawers), never for resting cards.

## Shapes

Two radius steps, deliberately small: 8px (`rounded-md`) for buttons and inputs, 10px (`rounded-lg`) for cards and tables. Nothing uses large/pill radii except true status badges and the sidebar's "coming soon" tags, which are fully rounded to read as small tags rather than buttons.

## Components

### Buttons
- **Shape:** 8px radius, 40px height, `px-4` (16px) horizontal padding, `sm` variant at 32px height / `px-3`.
- **Primary:** solid `#0F4C5C` fill, white text, hover `#0B3D4A`. Reserved for the one primary action per view.
- **Secondary:** white surface, 1px border, graphite text, hover fills `#ECEFEB`. The default for every non-primary action, including delete triggers.
- **Ghost:** text-only, muted color, hover fills `#ECEFEB`. Used for "cancel" and low-emphasis actions.
- **Danger:** solid `#B85D5D` fill — exists in the kit but is only ever reached from an explicit confirmation context, never as a resting toolbar button.

### Badges
- **Style:** fully-rounded pill, 12px text, no border, light tint background matched to a semantic text color (e.g. `bg-success-light` / `text-success`). Never a solid saturated fill.
- **Use:** lead/order/product status, fulfillment type, "coming soon" sidebar tags.

### Cards / Containers
- **Corner style:** 10px radius.
- **Background:** white on the warm page background.
- **Shadow strategy:** none at rest (see Elevation).
- **Border:** 1px `#DCE1DD`.
- **Internal padding:** 20px (`p-5`).

### Inputs / Fields
- **Style:** white surface, 1px border, 8px radius, 40px height, 12px horizontal padding.
- **Focus:** border shifts to primary green + a 2px `primary-light` ring — no glow, no color-shifted background.
- **Disabled:** `surface-secondary` background, muted text, `not-allowed` cursor.

### Tables
- **Header:** `surface-secondary` background, 12px medium-weight muted text.
- **Rows:** 1px hairline divider, very light `surface-secondary/60` tint on hover, `primary-light` background when selected.
- **Empty state:** centered muted single line inside the table body, not a separate illustration block.

### Navigation (Sidebar)
- **Style:** deep lagoon (`#0B2E36`) fixed column, grouped by section with 11px uppercase muted group labels.
- **Default:** muted sidebar text (`#929C97`), transparent background.
- **Hover:** `#153D46` background, text brightens to `#D6E4E4`.
- **Active:** same hover background plus a 2px primary-green indicator bar on the item's left edge — the only place a hard-edged color bar is used in the whole system, because the brief explicitly calls for it on this one element.
- **Disabled ("coming soon"):** reduced-opacity muted text, non-interactive, with a small `Շուտով` tag on the right so the product's intended shape stays legible without faking a working screen.
- **Mobile:** same component rendered inside a slide-over drawer with a scrim, triggered from the top bar's menu button.

## Do's and Don'ts

### Do:
- **Do** keep every color, radius, and spacing value as a CSS custom property in `admin/src/app/admin/admin.css` (its own Tailwind v4 `@theme` entry point, isolated from the public site's `globals.css`) so a tenant's brand color and a future dark theme can override them without touching component markup.
- **Do** use the secondary button style for delete triggers; reserve solid danger-red for the moment inside a confirmation step.
- **Do** use badges (light tint + semantic text color) for every status value, never a raw colored `<span>`.
- **Do** render an unbuilt CRM module as a disabled, tagged sidebar row rather than omitting it or faking a working screen.

### Don't:
- **Don't** introduce gradients, glow, or glassmorphism anywhere in the admin — the brief explicitly refuses the "AI app" look.
- **Don't** give cards or buttons large/pill radii; the system caps at 10px except true pill badges and tags.
- **Don't** use pure black (`#000`) or the public site's teal (`#427b6a`-family) tokens inside `/admin` — the two token systems are intentionally decoupled.
- **Don't** stack shadows on resting surfaces; depth comes from a 1px border, not elevation.

## Logo

The Phay logo is a «p» whose bowl is three arcs (three AI agents) around three dots: the gold one is the owner, the two others are teammates. Lagoon gradient `#0F4C5C` → `#5FD0B0`, owner dot `#F2B544`, wordmark `#0C3540` (Outfit SemiBold, outlined). In code use `PhayMark` / `PhayLogo` from `src/app/admin/_components/brand/PhayLogo.tsx` with `tone` `color` (light backgrounds), `onDark` (sidebar, dark panels) or `white` (on the brand color). Favicon and app icons live in `src/app/` (`icon.svg`, `icon.png`, `apple-icon.png`, `favicon.ico`). Never recolor the mark, stretch it, or put the color version on the brand teal.


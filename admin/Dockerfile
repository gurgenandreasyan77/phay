# syntax=docker/dockerfile:1

FROM node:24-alpine AS base
WORKDIR /app
ENV PORT=3001

# ---- dev: source is bind-mounted at runtime ------------------------------
FROM base AS dev
ENV NODE_ENV=development
COPY package.json package-lock.json ./
RUN npm ci
COPY healthcheck.js ./healthcheck.js
EXPOSE 3001
HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD ["node", "healthcheck.js"]
CMD ["npm", "run", "dev"]

# ---- deps: install once, reused by the production build -----------------
FROM base AS deps
COPY package.json package-lock.json ./
RUN npm ci

# ---- builder: produce the standalone production server ------------------
# The admin has no NEXT_PUBLIC_* build-time vars: everything it needs
# (API URL, revalidation secret) is read server-side at runtime.
FROM deps AS builder
ENV NODE_ENV=production
COPY . .
RUN npm run build

# ---- runner: minimal, non-root production image -------------------------
FROM base AS runner
ENV NODE_ENV=production
ENV HOSTNAME=0.0.0.0
RUN addgroup -g 1001 -S nodejs && adduser -S nextjs -u 1001
COPY --from=builder --chown=nextjs:nodejs /app/.next/standalone ./
COPY --from=builder --chown=nextjs:nodejs /app/.next/static ./.next/static
# The app icons, the service worker and the offline page (PWA).
COPY --from=builder --chown=nextjs:nodejs /app/public ./public
COPY --chown=nextjs:nodejs healthcheck.js ./healthcheck.js
USER nextjs
EXPOSE 3001
HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD ["node", "healthcheck.js"]
CMD ["node", "server.js"]

# syntax=docker/dockerfile:1
# Phay media: Chromium that renders the AI agents' designs to PNG (server.js)
# and ffmpeg that cuts the owner's clips into videos (video.js).
# Debian's chromium has amd64 and arm64 builds (a Mac with Apple Silicon too).
FROM node:22-bookworm-slim

ENV CHROMIUM_PATH=/usr/bin/chromium \
    PORT=3100 \
    NODE_ENV=production

# Fonts the designs may use (listed to the model in App\Media\DesignFormat::FONTS):
# Noto for Armenian, Cyrillic and Latin, colour emoji, and a few display faces.
ARG FONTS=https://raw.githubusercontent.com/google/fonts/main/ofl
RUN apt-get update \
    && apt-get install -y --no-install-recommends chromium ffmpeg fonts-noto-color-emoji fonts-dejavu-core fontconfig ca-certificates curl \
    && mkdir -p /usr/share/fonts/truetype/phay && cd /usr/share/fonts/truetype/phay \
    && for f in \
        "notosansarmenian/NotoSansArmenian[wdth,wght].ttf" \
        "notoserifarmenian/NotoSerifArmenian[wdth,wght].ttf" \
        "notosans/NotoSans[wdth,wght].ttf" \
        "notosans/NotoSans-Italic[wdth,wght].ttf" \
        "notoserif/NotoSerif[wdth,wght].ttf" \
        "montserrat/Montserrat[wght].ttf" \
        "montserrat/Montserrat-Italic[wght].ttf" \
        "playfairdisplay/PlayfairDisplay[wght].ttf" \
        "playfairdisplay/PlayfairDisplay-Italic[wght].ttf" \
        "rubik/Rubik[wght].ttf" \
      ; do curl -fsSL -o "$(basename "$f")" "$FONTS/$(echo "$f" | sed 's/\[/%5B/; s/\]/%5D/')"; done \
    && fc-cache -f \
    && apt-get purge -y curl && apt-get autoremove -y && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --omit=dev --no-audit --no-fund
COPY server.js video.js ./

USER node
EXPOSE 3100
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD node -e "fetch('http://127.0.0.1:3100/health').then(r => process.exit(r.ok ? 0 : 1)).catch(() => process.exit(1))"
CMD ["node", "server.js"]

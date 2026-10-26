// Phay media: renders the AI agents' designs (a full HTML page with inline
// CSS) to PNG for the API (App\Media\HttpRenderer). Only the API calls it,
// on the compose network, with MEDIA_TOKEN; no port is published.
//
// The HTML comes from a language model that may have read a stranger's web
// page, so it is treated as hostile: JavaScript is off, every request the
// page makes is refused (pictures come inline as data: URIs, fonts are
// installed in the image) and each render gets a fresh browser context.

import http from "node:http";
import puppeteer from "puppeteer-core";

const PORT = Number(process.env.PORT || 3100);
const TOKEN = process.env.MEDIA_TOKEN || "";
const CHROMIUM = process.env.CHROMIUM_PATH || "/usr/bin/chromium";
/** Inline pictures make the HTML heavy: a few photos of up to 5 MB, base64. */
const MAX_BODY = 32 * 1024 * 1024;
/** Renders at once; the rest wait their turn. */
const CONCURRENCY = Number(process.env.MEDIA_CONCURRENCY || 2);
const MIN_SIDE = 100;
const MAX_SIDE = 4000;
/** The page the HTML is served as: a name that never reaches the network. */
const ORIGIN = "https://design.phay.invalid/";

if (!TOKEN) {
  console.error("MEDIA_TOKEN is not set: refusing to start.");
  process.exit(1);
}

let browser = null;

async function getBrowser() {
  if (browser?.connected) return browser;
  browser = await puppeteer.launch({
    executablePath: CHROMIUM,
    headless: true,
    args: [
      // The container is the sandbox (no user namespaces in Docker by default).
      "--no-sandbox",
      "--disable-dev-shm-usage",
      "--disable-gpu",
      "--hide-scrollbars",
      "--font-render-hinting=none",
      // Nothing the page names resolves, whatever slips past the interception.
      "--host-resolver-rules=MAP * ~NOTFOUND",
    ],
  });
  browser.on("disconnected", () => {
    browser = null;
  });
  return browser;
}

let running = 0;
const waiting = [];

async function inTurn(work) {
  if (running >= CONCURRENCY) await new Promise((resolve) => waiting.push(resolve));
  running++;
  try {
    return await work();
  } finally {
    running--;
    waiting.shift()?.();
  }
}

async function render(html, width, height) {
  const context = await (await getBrowser()).createBrowserContext();
  try {
    const page = await context.newPage();
    await page.setJavaScriptEnabled(false);
    await page.setViewport({ width, height, deviceScaleFactor: 1 });
    await page.setRequestInterception(true);
    page.on("request", (request) => {
      const url = request.url();
      if (url === ORIGIN) {
        request.respond({ status: 200, contentType: "text/html; charset=utf-8", body: html });
      } else if (url.startsWith("data:")) {
        request.continue();
      } else {
        request.abort("blockedbyclient");
      }
    });
    await page.goto(ORIGIN, { waitUntil: "load", timeout: 20_000 });
    // Installed fonts load almost at once; still, wait for them.
    await page.evaluate(() => document.fonts.ready).catch(() => null);
    return await page.screenshot({ type: "png", clip: { x: 0, y: 0, width, height } });
  } finally {
    await context.close().catch(() => null);
  }
}

function readBody(request) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    let size = 0;
    request.on("data", (chunk) => {
      size += chunk.length;
      if (size > MAX_BODY) {
        reject(Object.assign(new Error("The HTML is too big."), { status: 413 }));
        request.destroy();
        return;
      }
      chunks.push(chunk);
    });
    request.on("end", () => resolve(Buffer.concat(chunks).toString("utf8")));
    request.on("error", reject);
  });
}

function send(response, status, body) {
  response.writeHead(status, { "Content-Type": "application/json" });
  response.end(JSON.stringify(body));
}

const server = http.createServer(async (request, response) => {
  if (request.method === "GET" && request.url === "/health") {
    return send(response, 200, { ok: true });
  }
  if (request.headers.authorization !== `Bearer ${TOKEN}`) {
    return send(response, 401, { message: "Unauthorized." });
  }
  if (request.method !== "POST" || request.url !== "/render") {
    return send(response, 404, { message: "Not found." });
  }

  try {
    const input = JSON.parse(await readBody(request));
    const width = Math.round(Number(input.width));
    const height = Math.round(Number(input.height));
    if (typeof input.html !== "string" || input.html.trim() === "") {
      return send(response, 422, { message: "html is required." });
    }
    if (![width, height].every((side) => side >= MIN_SIDE && side <= MAX_SIDE)) {
      return send(response, 422, { message: `width and height must be ${MIN_SIDE}-${MAX_SIDE}.` });
    }

    const started = Date.now();
    const png = await inTurn(() => render(input.html, width, height));
    response.writeHead(200, { "Content-Type": "image/png", "Content-Length": png.length, "X-Render-Ms": Date.now() - started });
    response.end(png);
  } catch (error) {
    const status = error.status ?? (error instanceof SyntaxError ? 400 : 500);
    if (status === 500) console.error("render failed:", error.message);
    send(response, status, { message: status === 500 ? "Render failed." : error.message });
  }
});

server.listen(PORT, () => console.log(`phay media listening on :${PORT}`));

for (const signal of ["SIGTERM", "SIGINT"]) {
  process.on(signal, async () => {
    server.close();
    await browser?.close().catch(() => null);
    process.exit(0);
  });
}

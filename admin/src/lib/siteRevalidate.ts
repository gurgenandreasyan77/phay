// Server-only. The public site (servio-front) caches API responses under
// tags (see the *_TAG constants in ./api.ts). The admin used to live in the
// same Next.js app and could call revalidateTag() directly; as a separate
// app it asks the site to drop those tags through its /api/revalidate route.
//
// Failures are logged, never thrown: the write to Laravel already succeeded,
// and the site's ISR window (1 hour) is the worst-case fallback.

const SITE_REVALIDATE_URL = process.env.SITE_REVALIDATE_URL;
const REVALIDATE_SECRET = process.env.REVALIDATE_SECRET;

export async function revalidateSite(...tags: string[]): Promise<void> {
  if (!SITE_REVALIDATE_URL || !REVALIDATE_SECRET) {
    console.warn("[revalidate] SITE_REVALIDATE_URL or REVALIDATE_SECRET is not set; public site cache not refreshed");
    return;
  }

  try {
    const response = await fetch(SITE_REVALIDATE_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json", "X-Revalidate-Secret": REVALIDATE_SECRET },
      body: JSON.stringify({ tags }),
      cache: "no-store",
      signal: AbortSignal.timeout(3000),
    });

    if (!response.ok) {
      console.warn(`[revalidate] site responded ${response.status} for tags: ${tags.join(", ")}`);
    }
  } catch (error) {
    console.warn(`[revalidate] could not reach the site for tags: ${tags.join(", ")}`, error);
  }
}

/** "Chrome · macOS" from a user agent, enough to recognise one's own device. */
export function deviceLabel(userAgent: string | null | undefined): string {
  if (!userAgent) return "Անհայտ սարք";
  const ua = userAgent;
  const browser = /Edg\//.test(ua)
    ? "Edge"
    : /OPR\//.test(ua)
      ? "Opera"
      : /YaBrowser\//.test(ua)
        ? "Yandex"
        : /Chrome\//.test(ua)
          ? "Chrome"
          : /Firefox\//.test(ua)
            ? "Firefox"
            : /Safari\//.test(ua)
              ? "Safari"
              : /node|undici/i.test(ua)
                ? "Սերվեր"
                : "Բրաուզեր";
  const os = /iPhone|iPad/.test(ua)
    ? "iOS"
    : /Android/.test(ua)
      ? "Android"
      : /Mac OS X/.test(ua)
        ? "macOS"
        : /Windows/.test(ua)
          ? "Windows"
          : /Linux/.test(ua)
            ? "Linux"
            : "";
  return os ? `${browser} · ${os}` : browser;
}

/** "17.10.2026 18:27" in Yerevan time (the same on the server and in the browser). */
export { formatDateTime as dateTime } from "../tables/shared";

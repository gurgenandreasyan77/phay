import type { MetadataRoute } from "next";

/**
 * Phay installs as an app (PWA): from the browser menu on Android and
 * desktop, «Add to Home Screen» on iPhone. It opens on the admin, full
 * screen, with the colour «Лагуна» mark on white (the maskable one has room
 * for Android's circle).
 */
export default function manifest(): MetadataRoute.Manifest {
  return {
    id: "/admin",
    name: "Phay",
    short_name: "Phay",
    description: "Ձեր բիզնեսի վահանակը՝ պատվերներ, վաճառքներ, վերլուծություն և AI աշխատակիցներ։",
    lang: "hy",
    start_url: "/admin",
    scope: "/",
    display: "standalone",
    orientation: "any",
    background_color: "#ffffff",
    theme_color: "#ffffff",
    categories: ["business", "productivity"],
    icons: [
      { src: "/icons/phay-192.png?v=3", sizes: "192x192", type: "image/png", purpose: "any" },
      { src: "/icons/phay-512.png?v=3", sizes: "512x512", type: "image/png", purpose: "any" },
      { src: "/icons/phay-maskable-192.png?v=3", sizes: "192x192", type: "image/png", purpose: "maskable" },
      { src: "/icons/phay-maskable-512.png?v=3", sizes: "512x512", type: "image/png", purpose: "maskable" },
    ],
    shortcuts: [
      { name: "Վահանակ", url: "/admin", icons: [{ src: "/icons/phay-192.png?v=3", sizes: "192x192" }] },
      { name: "Պատվերներ", url: "/admin/orders", icons: [{ src: "/icons/phay-192.png?v=3", sizes: "192x192" }] },
      { name: "AI օգնական", url: "/admin/assistant", icons: [{ src: "/icons/phay-192.png?v=3", sizes: "192x192" }] },
      { name: "Գրել Phay-ին", url: "/admin/support", icons: [{ src: "/icons/phay-192.png?v=3", sizes: "192x192" }] },
    ],
  };
}

"use client";

import { useEffect } from "react";
import { TriangleAlert } from "lucide-react";
import { buttonClasses } from "@/ui";
import "./admin/admin.css";
import { ErrorDetails } from "./admin/_components/errors/ErrorDetails";
import { ErrorScreen } from "./admin/_components/errors/ErrorScreen";

/**
 * When even the admin's frame (its root layout) failed: the page has to
 * bring its own <html>, styles and title.
 */
export default function GlobalError({ error, retry }: { error: Error & { digest?: string }; retry: () => void }) {
  useEffect(() => {
    console.error("[admin] layout failed", error.digest ?? "", error);
  }, [error]);

  return (
    <html lang="hy">
      <body className="min-h-screen bg-background text-ink antialiased">
        <title>Սխալ · Phay</title>
        <ErrorScreen
          fullScreen
          icon={TriangleAlert}
          tone="danger"
          title="Ինչ-որ բան այն չէ"
          description="Phay-ը չկարողացավ բացել էջը։ Փորձեք նորից մի քանի վայրկյանից. եթե սխալը կրկնվի, գրեք մեզ և նշեք ստորև բերված կոդը։"
          actions={
            <>
              <button type="button" className={buttonClasses("primary")} onClick={() => retry()}>
                Փորձել նորից
              </button>
              {/* A full page load: the client router belongs to the layout that failed. */}
              {/* eslint-disable-next-line @next/next/no-html-link-for-pages */}
              <a href="/admin" className={buttonClasses("secondary")}>
                Գլխավոր էջ
              </a>
            </>
          }
          details={<ErrorDetails error={error} />}
        />
      </body>
    </html>
  );
}

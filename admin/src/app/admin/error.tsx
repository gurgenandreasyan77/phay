"use client";

import { useEffect } from "react";
import Link from "next/link";
import { TriangleAlert } from "lucide-react";
import { buttonClasses } from "@/ui";
import { ErrorDetails } from "./_components/errors/ErrorDetails";
import { ErrorScreen } from "./_components/errors/ErrorScreen";

/**
 * "Something went wrong" for any admin page that failed (the API down or
 * answering with an error, a bug). The full error is in the admin's
 * server log under the digest shown here; in development the message is
 * shown too.
 */
export default function AdminError({ error, retry }: { error: Error & { digest?: string }; retry: () => void }) {
  useEffect(() => {
    console.error("[admin] page failed", error.digest ?? "", error);
  }, [error]);

  return (
    <ErrorScreen
      icon={TriangleAlert}
      tone="danger"
      title="Ինչ-որ բան այն չէ"
      description="Էջը բեռնելիս սխալ տեղի ունեցավ։ Փորձեք նորից. եթե սխալը կրկնվի, գրեք Phay-ի թիմին և նշեք ստորև բերված կոդը։"
      actions={
        <>
          <button type="button" className={buttonClasses("primary")} onClick={() => retry()}>
            Փորձել նորից
          </button>
          <Link href="/admin" className={buttonClasses("secondary")}>
            Գլխավոր էջ
          </Link>
        </>
      }
      details={<ErrorDetails error={error} />}
    />
  );
}

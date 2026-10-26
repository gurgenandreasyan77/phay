import Link from "next/link";
import { Wrench } from "lucide-react";
import { buttonClasses } from "@/ui";
import { ErrorScreen } from "../_components/errors/ErrorScreen";

export const metadata = { title: "Տեխնիկական աշխատանքներ · Phay" };

/**
 * The API answers 503 maintenance (php artisan down): reached by a redirect
 * from src/lib/adminAuth.ts. Public and without API calls, so it can't loop.
 */
export default function AdminMaintenancePage() {
  return (
    <ErrorScreen
      fullScreen
      code="503"
      icon={Wrench}
      tone="warning"
      title="Տեխնիկական աշխատանքներ"
      description="Մենք թարմացնում ենք Phay-ը։ Սովորաբար դա տևում է մի քանի րոպե։ Ձեր տվյալները պահպանված են։"
      actions={
        <Link href="/admin" className={buttonClasses("primary")}>
          Փորձել նորից
        </Link>
      }
    />
  );
}

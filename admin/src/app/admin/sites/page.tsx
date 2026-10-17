import { notFound } from "next/navigation";
import { requireAdmin } from "@/lib/adminAuth";
import { getSitesAdmin } from "@/lib/adminApi";
import { SitesManager } from "./SitesManager";
import { PageHeader } from "../_components/ui";

export const dynamic = "force-dynamic";

export default async function AdminSitesPage() {
  const admin = await requireAdmin();
  // Platform screen: a business owner doesn't manage site keys (the API says 403 too).
  if (!admin.isSuperAdmin) notFound();
  const sites = await getSitesAdmin();

  return (
    <div className="space-y-8">
      <PageHeader
        title="Կայքեր"
        description={
          <>
            «{admin.currentTenant?.name ?? "Servio"}» բիզնեսին միացված կայքերը։ Կայքն իր բանալիով ստանում է միայն այս բիզնեսի
            ծառայություններն ու կարգավորումները, իսկ նրա հայտերը գալիս են այստեղ՝ «Պատվերներ» բաժին։
          </>
        }
      />
      <SitesManager sites={sites} />
    </div>
  );
}

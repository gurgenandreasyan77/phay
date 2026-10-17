import { requireAdmin } from "@/lib/adminAuth";
import { getSitesAdmin } from "@/lib/adminApi";
import { SitesManager } from "./SitesManager";
import { PageHeader } from "../_components/ui";

export const dynamic = "force-dynamic";

export default async function AdminSitesPage() {
  const admin = await requireAdmin();
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

import Link from "next/link";
import { requireModule } from "@/lib/adminAuth";
import { getServiceCategoriesAdmin, getServicesAdmin } from "@/lib/adminApi";
import { ServicesManager } from "./ServicesManager";
import { PageHeader, SectionCard, buttonClasses } from "@/ui";

export const dynamic = "force-dynamic";

/**
 * Everything a service's own page on the site can show: what is included,
 * the steps, FAQ, car types and materials, prices by car type, SEO.
 */
export default async function AdminServiceDetailsPage() {
  await requireModule("services", "catalog");

  const [services, categories] = await Promise.all([getServicesAdmin(), getServiceCategoriesAdmin()]);

  return (
    <div className="space-y-10">
      <PageHeader
        title="Ծառայությունների մանրամասն էջեր"
        description="Ծառայության էջի ամբողջ բովանդակությունը կայքում՝ ինչ է ներառված, փուլերը, հաճախ տրվող հարցերը, մեքենայի տեսակները և նյութերը, SEO։ Կարճ ցանկը և գները՝ «Ծառայություններ» էջում։"
        actions={
          <Link href="/admin/services" className={buttonClasses("secondary")}>
            Ծառայություններ
          </Link>
        }
      />

      <SectionCard title="Ծառայություններ">
        <ServicesManager items={services} categories={categories} />
      </SectionCard>
    </div>
  );
}

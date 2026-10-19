import { notFound } from "next/navigation";
import { requirePermission } from "@/lib/adminAuth";
import { getFeatureFlagsAdmin, getServiceCategoriesAdmin, getServicesAdmin } from "@/lib/adminApi";
import { ServiceCategoriesManager } from "./ServiceCategoriesManager";
import { ServicesCatalog } from "./ServicesCatalog";
import { PageHeader, SectionCard } from "../_components/ui";

export const dynamic = "force-dynamic";

/**
 * The business's own services: what it does, for how much. A module the
 * platform turns on (and charges for); anything trade-specific (a
 * calculator, car types...) lives in a module of its own.
 */
export default async function AdminServicesPage() {
  await requirePermission("catalog");
  const flags = await getFeatureFlagsAdmin();
  if (!flags.find((f) => f.key === "services")?.active) notFound();

  const [services, categories] = await Promise.all([getServicesAdmin(), getServiceCategoriesAdmin()]);

  return (
    <div className="space-y-10">
      <PageHeader
        title="Ծառայություններ"
        description="Ձեր բիզնեսի ծառայություններն ու գները։ «Ակտիվ» ծառայությունները երևում են կայքում, «Սևագիր»-ը՝ միայն այստեղ։"
      />

      <SectionCard title="Ծառայություններ">
        <ServicesCatalog services={services} categories={categories} />
      </SectionCard>

      <SectionCard
        title="Կատեգորիաներ"
        description="Ծառայությունների խմբերը, որոնցով դրանք դասավորվում են կայքում։"
      >
        <ServiceCategoriesManager items={categories} />
      </SectionCard>
    </div>
  );
}

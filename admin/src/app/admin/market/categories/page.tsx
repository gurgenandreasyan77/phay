import { requirePermission } from "@/lib/adminAuth";
import { getProductCategoriesAdmin } from "@/lib/adminApi";
import { CategoriesManager } from "./CategoriesManager";
import { PageHeader } from "../../_components/ui";

export const dynamic = "force-dynamic";

export default async function AdminProductCategoriesPage() {
  await requirePermission("catalog");
  const items = await getProductCategoriesAdmin();

  return (
    <div className="space-y-6">
      <PageHeader
        title="Market կատեգորիաներ"
        description={
          <>
            Կատեգորիաների slug-երը (hy/ru/en) կազմում են /market/{"{category}"} URL-ը։ Slug-ը փոխելուց հին
            հղումների համար ավտոմատ ռեդիրեկտ չի ստեղծվում կատեգորիաների համար. փոփոխեք զգուշորեն։
          </>
        }
      />

      <CategoriesManager items={items} />
    </div>
  );
}

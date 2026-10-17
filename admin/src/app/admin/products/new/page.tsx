import { requireAdmin } from "@/lib/adminAuth";
import { getProductsAdmin, getProductCategoriesAdmin } from "@/lib/adminApi";
import { getServices } from "@/lib/api";
import { ProductEditor } from "../ProductEditor";
import { PageHeader } from "../../_components/ui";

export const dynamic = "force-dynamic";

export default async function NewProductPage() {
  await requireAdmin();

  const [categories, services, products] = await Promise.all([
    getProductCategoriesAdmin(),
    getServices("hy"),
    getProductsAdmin(),
  ]);

  return (
    <div className="space-y-6">
      <PageHeader title="Նոր ապրանք" description="Ապրանքը կստեղծվի Draft կարգավիճակով։" />

      <ProductEditor
        categories={categories.map((c) => ({ id: c.id, name: c.name.hy ?? c.name.ru ?? c.name.en ?? "" }))}
        services={services.map((s) => ({ id: s.id, name: s.name }))}
        allProducts={products.map((p) => ({ id: p.id, name: p.name.hy ?? p.name.ru ?? p.name.en ?? "" }))}
      />
    </div>
  );
}

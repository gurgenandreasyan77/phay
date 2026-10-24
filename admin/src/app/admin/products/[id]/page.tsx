import { notFound } from "next/navigation";
import { requirePermission } from "@/lib/adminAuth";
import { getProductsAdmin, getProductCategoriesAdmin } from "@/lib/adminApi";
import { getServices } from "@/lib/api";
import { ProductEditor } from "../ProductEditor";
import { PageHeader } from "@/ui";

export const dynamic = "force-dynamic";

export default async function EditProductPage(props: { params: Promise<{ id: string }> }) {
  await requirePermission("catalog");
  const { id } = await props.params;

  const [categories, services, products] = await Promise.all([
    getProductCategoriesAdmin(),
    getServices("hy"),
    getProductsAdmin(),
  ]);

  const item = products.find((p) => p.id === Number(id));
  if (!item) notFound();

  return (
    <div className="space-y-6">
      <PageHeader title="Խմբագրել ապրանքը" description={item.slugHy} />

      <ProductEditor
        item={item}
        categories={categories.map((c) => ({ id: c.id, name: c.name.hy ?? c.name.ru ?? c.name.en ?? "" }))}
        services={services.map((s) => ({ id: s.id, name: s.name }))}
        allProducts={products.map((p) => ({ id: p.id, name: p.name.hy ?? p.name.ru ?? p.name.en ?? "" }))}
      />
    </div>
  );
}

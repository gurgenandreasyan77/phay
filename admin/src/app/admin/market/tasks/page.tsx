import { requirePermission } from "@/lib/adminAuth";
import { getTaskPagesAdmin, getProductsAdmin } from "@/lib/adminApi";
import { getServices } from "@/lib/api";
import { TaskPagesManager } from "./TaskPagesManager";
import { PageHeader } from "@/ui";

export const dynamic = "force-dynamic";

export default async function AdminTaskPagesPage() {
  await requirePermission("catalog");

  const [items, products, services] = await Promise.all([
    getTaskPagesAdmin(),
    getProductsAdmin(),
    getServices("hy"),
  ]);

  return (
    <div className="space-y-6">
      <PageHeader
        title="SEO խնդիրների էջեր (/market/tasks)"
        description="Այս էջերը հրապարակվում են միայն այն դեպքում, երբ intro դաշտը լրացված է երեք լեզուներով էլ։"
      />

      <TaskPagesManager
        items={items}
        products={products.map((p) => ({ id: p.id, name: p.name.hy ?? p.name.ru ?? p.name.en ?? "" }))}
        services={services.map((s) => ({ id: s.id, name: s.name }))}
      />
    </div>
  );
}

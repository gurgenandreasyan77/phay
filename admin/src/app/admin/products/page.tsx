import Link from "next/link";
import { requireModule } from "@/lib/adminAuth";
import { getProductsAdmin } from "@/lib/adminApi";
import { ProductsList } from "./ProductsList";
import { PageHeader, buttonClasses } from "@/ui";

export const dynamic = "force-dynamic";

export default async function AdminProductsPage() {
  await requireModule("market", "catalog");
  const items = await getProductsAdmin();

  return (
    <div className="space-y-10">
      <PageHeader
        title="Խանութ"
        description="Կայքի «Market» բաժնում ցուցադրվող քիմիական միջոցները։ Draft կարգավիճակով ապրանքները երևում են միայն ադմին վահանակում, մինչև իրական տվյալները (գին, լուսանկար, նշանակություն, անվտանգություն) լրացվեն և կարգավիճակը փոխվի Published-ի։"
        actions={
          <Link href="/admin/products/new" className={buttonClasses("primary")}>
            + Նոր ապրանք
          </Link>
        }
      />

      <ProductsList items={items} />
    </div>
  );
}

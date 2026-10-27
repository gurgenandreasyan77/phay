import { requirePermission } from "@/lib/adminAuth";
import { getDuplicates } from "@/lib/adminApi";
import { PageHeader } from "@/ui";
import { DuplicatesReview } from "../_components/tables/Duplicates";

export const dynamic = "force-dynamic";

/**
 * The same order, sale or expense written in several tables (a copied
 * table, a month's table made from a bigger one): the owner keeps all of
 * them or deletes the extra copy, so the calendar and analytics count each
 * record once.
 */
export default async function AdminDuplicatesPage() {
  await requirePermission("orders");
  const data = await getDuplicates();

  return (
    <div className="space-y-6">
      <PageHeader
        title="Կրկնվող գրառումներ"
        description="Նույն պատվերը, վաճառքը կամ ծախսը՝ մի քանի աղյուսակում։ Եթե բոլոր դաշտերը նույնն են, այն արդեն հաշվվում է մեկ անգամ։ Եթե մի տողում ինչ-որ բան պակասում է (օր.՝ հասցեն), երկուսն էլ հաշվվում են, մինչև որոշեք։ Այլ կատարող, գին կամ ժամ ունեցող տողերը տարբեր գրառումներ են և այստեղ չեն երևում։"
      />
      <DuplicatesReview groups={data?.groups ?? []} />
    </div>
  );
}

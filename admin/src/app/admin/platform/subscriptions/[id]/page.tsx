import Link from "next/link";
import { notFound } from "next/navigation";
import { requireSuperAdmin } from "@/lib/adminAuth";
import { getTenantSubscriptionAdmin } from "@/lib/adminApi";
import { PageHeader } from "@/ui";
import { TenantSubscriptionManager } from "./TenantSubscriptionManager";

export const dynamic = "force-dynamic";

/** One business's subscription (super admin): plan, payments, free access, turning off. */
export default async function TenantSubscriptionPage({ params }: { params: Promise<{ id: string }> }) {
  await requireSuperAdmin();
  const { id } = await params;
  const data = await getTenantSubscriptionAdmin(Number(id));
  if (!data) notFound();

  return (
    <div className="space-y-6">
      <PageHeader
        title={data.tenant.name}
        description="Բաժանորդագրություն, վճարումներ և անվճար մուտք։ Բիզնեսը ստանում է հաղորդագրություն չատում յուրաքանչյուր փոփոխության մասին։"
        actions={
          <Link href="/admin/platform/subscriptions" className="text-sm text-ink-muted hover:text-ink">
            ← Բոլոր բաժանորդագրությունները
          </Link>
        }
      />
      <TenantSubscriptionManager data={data} />
    </div>
  );
}

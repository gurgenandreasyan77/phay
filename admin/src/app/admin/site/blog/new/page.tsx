import Link from "next/link";
import { notFound } from "next/navigation";
import { requirePermission } from "@/lib/adminAuth";
import { getFeatureFlagsAdmin } from "@/lib/adminApi";
import { PageHeader, buttonClasses } from "../../../_components/ui";
import { PostEditor } from "../PostEditor";

export const dynamic = "force-dynamic";

export default async function NewPostPage() {
  await requirePermission("catalog");
  const flags = await getFeatureFlagsAdmin();
  if (!flags.find((f) => f.key === "site")?.active) notFound();

  return (
    <div className="space-y-6">
      <PageHeader
        title="Նոր հոդված"
        actions={
          <Link href="/admin/site/blog" className={buttonClasses("secondary")}>
            Բլոգ
          </Link>
        }
      />
      <PostEditor post={null} />
    </div>
  );
}

import Link from "next/link";
import { requireModule } from "@/lib/adminAuth";
import { PageHeader, buttonClasses } from "@/ui";
import { PostEditor } from "../PostEditor";

export const dynamic = "force-dynamic";

export default async function NewPostPage() {
  await requireModule("site", "catalog");

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

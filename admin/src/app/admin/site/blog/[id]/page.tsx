import Link from "next/link";
import { notFound } from "next/navigation";
import { requireModule } from "@/lib/adminAuth";
import { getPostAdmin } from "@/lib/adminApi";
import { PageHeader, buttonClasses } from "@/ui";
import { PostEditor } from "../PostEditor";

export const dynamic = "force-dynamic";

export default async function EditPostPage(props: { params: Promise<{ id: string }> }) {
  await requireModule("site", "catalog");
  const { id } = await props.params;
  const post = Number.isInteger(Number(id)) ? await getPostAdmin(Number(id)) : null;
  if (!post) notFound();

  return (
    <div className="space-y-6">
      <PageHeader
        title={post.title.hy || post.slug}
        description={`/blog/${post.slug}`}
        actions={
          <Link href="/admin/site/blog" className={buttonClasses("secondary")}>
            Բլոգ
          </Link>
        }
      />
      <PostEditor key={post.updatedAt ?? post.id} post={post} />
    </div>
  );
}

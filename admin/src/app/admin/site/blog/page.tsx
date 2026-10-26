import Link from "next/link";
import { requireModule } from "@/lib/adminAuth";
import { getPostsAdmin } from "@/lib/adminApi";
import { Badge, EmptyRow, PageHeader, TableWrap, Td, Th, Thead, Tr, buttonClasses } from "@/ui";
import { formatMoment } from "../../_components/tables/shared";
import { POST_CATEGORY_LABELS } from "./shared";

export const dynamic = "force-dynamic";

/** The blog of the business's site: drafts and published posts. */
export default async function AdminBlogPage() {
  await requireModule("site", "catalog");

  const posts = await getPostsAdmin();

  return (
    <div className="space-y-6">
      <PageHeader
        title="Բլոգ"
        description="Հոդվածներ ձեր կայքի համար՝ խորհուրդներ, ուղեցույցներ, նորություններ։ Հրապարակվածները երևում են կայքի բլոգում։"
        actions={
          <Link href="/admin/site/blog/new" className={buttonClasses("primary")}>
            Նոր հոդված
          </Link>
        }
      />

      <TableWrap>
        <Thead>
          <Th>Վերնագիր</Th>
          <Th>Թեմա</Th>
          <Th>Վիճակ</Th>
          <Th>Հրապարակման օր</Th>
        </Thead>
        <tbody>
          {posts === null ? (
            <EmptyRow colSpan={4}>Չհաջողվեց բեռնել հոդվածները։</EmptyRow>
          ) : posts.length === 0 ? (
            <EmptyRow colSpan={4}>Դեռ հոդվածներ չկան։</EmptyRow>
          ) : (
            posts.map((post) => (
              <Tr key={post.id}>
                <Td>
                  <Link href={`/admin/site/blog/${post.id}`} className="font-medium text-primary hover:underline">
                    {post.title.hy || post.title.ru || post.title.en || post.slug}
                  </Link>
                </Td>
                <Td>{POST_CATEGORY_LABELS[post.category] ?? post.category}</Td>
                <Td>
                  <Badge tone={post.status === "published" ? "success" : "default"}>
                    {post.status === "published" ? "Հրապարակված" : "Սևագիր"}
                  </Badge>
                </Td>
                <Td>{post.publishedAt ? formatMoment(post.publishedAt) : "—"}</Td>
              </Tr>
            ))
          )}
        </tbody>
      </TableWrap>
    </div>
  );
}

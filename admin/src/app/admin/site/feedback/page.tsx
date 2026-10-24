import Link from "next/link";
import { notFound } from "next/navigation";
import { requirePermission } from "@/lib/adminAuth";
import { getFeatureFlagsAdmin, getFeedbackMessages } from "@/lib/adminApi";
import { EmptyCard, PageHeader, buttonClasses } from "@/ui";
import { FeedbackList } from "./FeedbackList";

export const dynamic = "force-dynamic";

/** What visitors wrote through the feedback form of the business's site. */
export default async function AdminFeedbackPage(props: { searchParams: Promise<{ page?: string }> }) {
  await requirePermission("orders");
  const flags = await getFeatureFlagsAdmin();
  if (!flags.find((f) => f.key === "site")?.active) notFound();

  const page = Math.max(1, Number((await props.searchParams).page) || 1);
  const feedback = await getFeedbackMessages(page);

  return (
    <div className="space-y-6">
      <PageHeader
        title="Հետադարձ կապ"
        description="Կայքի հետադարձ կապի ձևից եկած հարցեր, կարծիքներ և առաջարկներ։ Պատվերները՝ «Պատվերներ» բաժնում։"
      />

      {feedback === null ? (
        <EmptyCard>Չհաջողվեց բեռնել հաղորդագրությունները։</EmptyCard>
      ) : feedback.items.length === 0 ? (
        <EmptyCard>Դեռ հաղորդագրություններ չկան։</EmptyCard>
      ) : (
        <>
          <FeedbackList items={feedback.items} />
          {feedback.meta.lastPage > 1 ? (
            <div className="flex items-center gap-3 text-sm text-ink-muted">
              {page > 1 ? (
                <Link href={`/admin/site/feedback?page=${page - 1}`} className={buttonClasses("secondary", "sm")}>
                  Նախորդ
                </Link>
              ) : null}
              <span>
                {page} / {feedback.meta.lastPage}
              </span>
              {page < feedback.meta.lastPage ? (
                <Link href={`/admin/site/feedback?page=${page + 1}`} className={buttonClasses("secondary", "sm")}>
                  Հաջորդ
                </Link>
              ) : null}
            </div>
          ) : null}
        </>
      )}
    </div>
  );
}

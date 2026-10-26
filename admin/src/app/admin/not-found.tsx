import Link from "next/link";
import { SearchX } from "lucide-react";
import { getMe } from "@/lib/adminApi";
import { isPlatformStaff } from "@/lib/roles";
import { buttonClasses } from "@/ui";
import { BackButton } from "./_components/errors/BackButton";
import { ErrorScreen } from "./_components/errors/ErrorScreen";

export const metadata = { title: "Էջը չի գտնվել · Phay" };

/**
 * 404 of the admin: an address that doesn't exist (typed by hand, an old
 * link) or a record that is gone. Shown inside the admin's frame.
 */
export default async function AdminNotFound() {
  const me = await getMe();
  const home = isPlatformStaff(me) ? "/admin/platform" : "/admin";

  return (
    <ErrorScreen
      code="404"
      icon={SearchX}
      title="Էջը չի գտնվել"
      description="Հնարավոր է՝ հասցեն սխալ է գրված, կամ էջը տեղափոխվել կամ ջնջվել է։ Ստուգեք հասցեն կամ անցեք գլխավոր էջ։"
      actions={
        <>
          <Link href={home} className={buttonClasses("primary")}>
            Գլխավոր էջ
          </Link>
          <BackButton fallback={home} />
        </>
      }
    />
  );
}

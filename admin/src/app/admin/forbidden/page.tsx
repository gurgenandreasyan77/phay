import Link from "next/link";
import { ShieldX } from "lucide-react";
import { getMe } from "@/lib/adminApi";
import { BUSINESS_ROLE_LABELS, isPlatformStaff } from "@/lib/roles";
import { buttonClasses } from "@/ui";
import { BackButton } from "../_components/errors/BackButton";
import { ErrorScreen } from "../_components/errors/ErrorScreen";
import { SignOutButton } from "../_components/errors/SignOutButton";

export const dynamic = "force-dynamic";
export const metadata = { title: "Մուտքն արգելված է · Phay" };

const MODULE_LABELS: Record<string, string> = {
  services: "Ծառայություններ",
  market: "Խանութ",
  site: "Կայք",
  social: "Սոցցանցեր",
};

/**
 * 403: why a page can't be opened, and where to go instead. Reached by a
 * redirect from src/lib/adminAuth.ts (?reason=role|module|account|suspended).
 * It doesn't call requireAdmin itself: an account without a business would
 * be sent straight back here.
 */
export default async function AdminForbiddenPage(props: PageProps<"/admin/forbidden">) {
  const query = await props.searchParams;
  const reason = typeof query.reason === "string" ? query.reason : "role";
  const moduleKey = typeof query.module === "string" ? query.module : "";
  const me = await getMe();
  const home = isPlatformStaff(me) ? "/admin/platform" : "/admin";

  if (reason === "account" || reason === "suspended" || !me) {
    return (
      <ErrorScreen
        code="403"
        icon={ShieldX}
        tone="warning"
        title={reason === "suspended" ? "Բիզնեսը կասեցված է" : "Հաշիվը կապված չէ բիզնեսի հետ"}
        description={
          reason === "suspended"
            ? "Այս բիզնեսի աշխատանքը ժամանակավորապես կասեցված է։ Տվյալները պահպանված են։ Մանրամասների համար կապվեք Phay-ի թիմի հետ։"
            : "Ձեր հաշիվը դեռ ոչ մի բիզնեսի չի միացված։ Խնդրեք բիզնեսի սեփականատիրոջը կամ Phay-ի թիմին ձեզ ավելացնել։"
        }
        actions={<SignOutButton variant="primary" />}
      />
    );
  }

  if (reason === "module") {
    const name = MODULE_LABELS[moduleKey];
    return (
      <ErrorScreen
        code="403"
        icon={ShieldX}
        title={name ? `«${name}» մոդուլը միացված չէ` : "Մոդուլը միացված չէ"}
        description="Այս բաժինը մաս է կազմում մոդուլի, որը ձեր բիզնեսի համար դեռ միացված չէ։ Մոդուլը կարող եք պատվիրել բաժանորդագրության էջում։"
        actions={
          <>
            <Link href="/admin/subscription#modules" className={buttonClasses("primary")}>
              Մոդուլներ և բաժանորդագրություն
            </Link>
            <Link href={home} className={buttonClasses("secondary")}>
              Գլխավոր էջ
            </Link>
          </>
        }
      />
    );
  }

  const role = me.businessRole ? BUSINESS_ROLE_LABELS[me.businessRole] : null;
  return (
    <ErrorScreen
      code="403"
      icon={ShieldX}
      title="Այս բաժինը ձեզ համար փակ է"
      description={
        <>
          {role ? (
            <>
              Ձեր դերը բիզնեսում՝ <span className="font-medium text-ink">{role}</span>, թույլ չի տալիս բացել այս էջը։{" "}
            </>
          ) : (
            "Ձեր դերը թույլ չի տալիս բացել այս էջը։ "
          )}
          Եթե այն ձեզ անհրաժեշտ է, դիմեք բիզնեսի սեփականատիրոջը։
        </>
      }
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

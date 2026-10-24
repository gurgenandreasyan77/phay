import { requireAdmin } from "@/lib/adminAuth";
import { AuthCard } from "../_components/auth/AuthCard";
import { SetupTwoFactor } from "./SetupTwoFactor";

export const dynamic = "force-dynamic";

/**
 * Required 2FA comes before anything else (platform staff, owners, or a
 * business that asks for it): every other page sends here until it's on.
 */
export default async function SetupTwoFactorPage() {
  const admin = await requireAdmin({ allowLocked: true, allowTwoFactorSetup: true });
  const required = admin.security?.twoFactorSetupRequired ?? false;

  return (
    <AuthCard
      wide
      title="Միացրեք երկքայլ հաստատումը"
      description={
        required
          ? "Ձեր հաշիվը հասանելիություն ունի ֆինանսական տվյալներին, ուստի մուտքը պաշտպանվում է նաև հեռախոսի կոդով։ Առանց դրա վահանակը չի բացվի։"
          : "2FA-ն արդեն միացված է։"
      }
    >
      <SetupTwoFactor required={required} home={admin.platformRole ? "/admin/platform" : "/admin"} />
    </AuthCard>
  );
}

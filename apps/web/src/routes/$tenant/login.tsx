import { createFileRoute, useParams } from "@tanstack/react-router"
import {
  CalendarCheckIcon,
  DashboardSquare01Icon,
  Globe02Icon,
} from "@hugeicons/core-free-icons"

import { AuthLayout } from "#/components/auth/auth-layout.tsx"
import {
  DemoQuickLogin,
  isDemoLoginEnabled,
} from "#/components/auth/demo-quick-login.tsx"
import { TenantLoginForm } from "#/components/auth/tenant-login-form.tsx"
import { useTrans } from "#/hooks/use-trans.ts"

export const Route = createFileRoute("/$tenant/login")({
  component: TenantLoginPage,
})

function TenantLoginPage() {
  const { tenant } = useParams({ from: "/$tenant/login" })
  const { t } = useTrans()

  return (
    <AuthLayout
      kicker={t("auth.login_kicker")}
      title={t("brand.tagline")}
      subtitle={tenant}
      points={[
        { icon: CalendarCheckIcon, label: t("brand.value_1") },
        { icon: DashboardSquare01Icon, label: t("brand.value_2") },
        { icon: Globe02Icon, label: t("brand.value_3") },
      ]}
      formHeading={t("auth.welcome_back")}
      formSubheading={t("auth.login_subtitle")}
      footerLink={{
        text: t("auth.no_account"),
        linkLabel: t("auth.register"),
        to: "/register",
      }}
    >
      {isDemoLoginEnabled() ? (
        <div className="mb-6">
          <DemoQuickLogin kind="clinic" showDivider />
        </div>
      ) : null}

      <TenantLoginForm tenant={tenant} />
    </AuthLayout>
  )
}

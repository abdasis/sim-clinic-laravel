import { createFileRoute, useParams } from "@tanstack/react-router"

import { useBreadcrumbTail } from "#/components/breadcrumb-tail.tsx"
import { AccessDenied } from "#/components/ui/access-denied.tsx"
import { useAuthUser } from "#/hooks/use-auth-user.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { ConnectionCard } from "./components/connection-card.tsx"
import { CredentialsCard } from "./components/credentials-card.tsx"

export const Route = createFileRoute("/$tenant/clinic/settings/whatsapp/")({
  component: WhatsappSettingsPage,
})

function WhatsappSettingsPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/settings/whatsapp/" })
  const { t } = useTrans()
  const authUser = useAuthUser()

  useBreadcrumbTail(t("settings.nav.whatsapp"))

  // ponytail: guard peran setelah mount, sama seperti halaman staf — sesi
  // dibaca dari localStorage yang belum tersedia saat render pertama.
  if (authUser?.clinic_role !== "admin") {
    return <AccessDenied illustration="users" description={t("settings.whatsapp.admin_only")} />
  }

  return (
    <div className="max-w-3xl">
      <div className="mb-4">
        <h1 className="text-xl font-semibold tracking-tight">
          {t("settings.whatsapp.title")}
        </h1>
        <p className="mt-1 text-sm text-pretty text-muted-foreground">
          {t("settings.whatsapp.description")}
        </p>
      </div>

      <div className="space-y-4">
        <ConnectionCard tenant={tenant} />
        <CredentialsCard tenant={tenant} />
      </div>
    </div>
  )
}

import { createFileRoute } from "@tanstack/react-router"

import { useTrans } from "#/hooks/use-trans.ts"
import { ClinicGatewayList } from "./components/clinic-gateway-list.tsx"
import { GatewayAddressCard } from "./components/gateway-address-card.tsx"

export const Route = createFileRoute("/central/whatsapp-gateway/")({
  component: WhatsappGatewayPage,
})

function WhatsappGatewayPage() {
  const { t } = useTrans()

  return (
    <div className="mx-auto w-full max-w-5xl px-4 py-6">
      <header className="mb-4">
        <h1 className="text-xl font-semibold tracking-tight">
          {t("whatsapp_gateway.title")}
        </h1>
        <p className="mt-1 max-w-2xl text-sm text-pretty text-muted-foreground">
          {t("whatsapp_gateway.description")}
        </p>
      </header>

      <div className="space-y-4">
        <GatewayAddressCard />
        <ClinicGatewayList />
      </div>
    </div>
  )
}

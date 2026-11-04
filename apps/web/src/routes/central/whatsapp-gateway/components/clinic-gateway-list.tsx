import { useQuery } from "@tanstack/react-query"

import { Button } from "#/components/ui/button.tsx"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "#/components/ui/card.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import { ClinicGatewayCard } from "./clinic-gateway-card.tsx"
import type { ClinicGatewayRow } from "./gateway-types.ts"

export function ClinicGatewayList() {
  const { t } = useTrans()

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ["central-whatsapp-gateway-clinics"],
    queryFn: () =>
      apiGet<{ data: ClinicGatewayRow[] }>(
        "/central/whatsapp-gateway/clinics",
      ),
    select: (res) => res.data,
  })

  const rows = data ?? []

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">
          {t("whatsapp_gateway.clinics_title")}
        </CardTitle>
        <CardDescription className="text-pretty">
          {t("whatsapp_gateway.clinics_desc")}
        </CardDescription>
      </CardHeader>
      <CardContent>
        {isLoading ? (
          <div className="space-y-3">
            <Skeleton className="h-28 w-full" />
            <Skeleton className="h-28 w-full" />
          </div>
        ) : isError ? (
          <div className="flex flex-col items-start gap-3 py-4">
            <p className="text-sm text-muted-foreground">
              {t("whatsapp_gateway.load_failed")}
            </p>
            <Button variant="outline" size="sm" onClick={() => void refetch()}>
              {t("general.retry")}
            </Button>
          </div>
        ) : rows.length === 0 ? (
          <p className="py-4 text-sm text-muted-foreground">
            {t("whatsapp_gateway.empty")}
          </p>
        ) : (
          <ul className="space-y-3">
            {rows.map((row) => (
              <li key={row.tenant_id}>
                <ClinicGatewayCard row={row} />
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  )
}

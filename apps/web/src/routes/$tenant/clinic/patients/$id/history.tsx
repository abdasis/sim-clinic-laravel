import { createFileRoute, useParams } from "@tanstack/react-router"
import { useQuery } from "@tanstack/react-query"
import { Card, CardContent } from "#/components/ui/card.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"

export const Route = createFileRoute("/$tenant/clinic/patients/$id/history")({
  component: PatientHistoryPage,
})

interface HistoryEntry {
  date: string
  service_name: string
  status: string
  assignee_name: string
  type: string
}

function PatientHistoryPage() {
  const { tenant, id } = useParams({
    from: "/$tenant/clinic/patients/$id/history",
  })
  const { t } = useTrans()

  const { data, isLoading } = useQuery({
    queryKey: ["patients", tenant, id, "history"],
    queryFn: () =>
      apiGet<{ data: HistoryEntry[] }>(
        `/${tenant}/clinic/patients/${id}/history`,
      ),
  })

  const entries = data?.data ?? []

  return (
    <div>
      <ClinicBreadcrumb
        items={[
          { label: tenant, to: "/$tenant/clinic/patients", params: { tenant } },
          { label: t("clinic.clinic") },
          {
            label: t("patient.title"),
            to: "/$tenant/clinic/patients",
            params: { tenant },
          },
          { label: t("patient.history") },
        ]}
      />
      <h1 className="mb-4 text-xl font-semibold">{t("patient.history")}</h1>
      {isLoading ? (
        <div className="space-y-2">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="h-16 w-full" />
          ))}
        </div>
      ) : entries.length === 0 ? (
        <div className="text-sm text-muted-foreground">
          {t("patient.history_empty")}
        </div>
      ) : (
        <ol className="relative space-y-3 border-s ps-6">
          {entries.map((entry, i) => (
            <li key={i} className="relative">
              <span className="absolute -start-[1.6rem] top-2 size-2 rounded-full bg-primary" />
              <Card>
                <CardContent className="flex items-center justify-between gap-4 py-4">
                  <div>
                    <div className="text-sm text-muted-foreground">
                      {entry.date}
                    </div>
                    <div className="font-medium">{entry.service_name}</div>
                    <div className="text-sm text-muted-foreground">
                      {entry.assignee_name}
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    <Badge variant="outline">
                      {t(`patient.history_type.${entry.type}`)}
                    </Badge>
                    <Badge>{entry.status}</Badge>
                  </div>
                </CardContent>
              </Card>
            </li>
          ))}
        </ol>
      )}
    </div>
  )
}

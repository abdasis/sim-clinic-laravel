import { createFileRoute, useParams } from "@tanstack/react-router"
import { useQuery } from "@tanstack/react-query"
import { Card, CardContent } from "#/components/ui/card.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import { StatusBadge } from "#/components/ui/status-badge.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { useBreadcrumbTail } from "#/components/breadcrumb-tail.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { EmptyState } from "#/components/ui/empty-state.tsx"
import { apiGet } from "#/lib/api.ts"
import { formatDateTime } from "#/lib/format.ts"
import { cn } from "#/lib/utils.ts"

export const Route = createFileRoute("/$tenant/clinic/patients/$id/history")({
  component: PatientHistoryPage,
})

/** Warna status dipakai dua kali: di lencana dan di titik garis waktunya. */
const HISTORY_STATUS_VARIANTS = {
  pending: "secondary",
  confirmed: "default",
  done: "outline",
  cancelled: "destructive",
} as const

const HISTORY_STATUS_DOT: Record<string, string> = {
  pending: "bg-amber-500",
  confirmed: "bg-emerald-500",
  done: "bg-muted-foreground",
  cancelled: "bg-destructive",
}

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

  useBreadcrumbTail(t("patient.history"))
  return (
    <div>
      <h1 className="mb-4 text-xl font-semibold">{t("patient.history")}</h1>
      {isLoading ? (
        <div className="space-y-2">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="h-16 w-full" />
          ))}
        </div>
      ) : entries.length === 0 ? (
        <EmptyState
          className="py-10"
          illustration="history-clock"
          title={t("patient.history_empty")}
          description={t("patient.history_empty_desc")}
        />
      ) : (
        <ol className="relative space-y-3 border-s ps-6">
          {entries.map((entry, i) => (
            <li key={i} className="relative">
              <span
                aria-hidden
                className={cn(
                  // Titik garis waktu ikut warna status: kunjungan yang batal
                  // tidak boleh terbaca sama dengan yang selesai.
                  "absolute -start-[1.65rem] top-4 size-2 rounded-full ring-4 ring-background",
                  HISTORY_STATUS_DOT[entry.status] ?? "bg-muted-foreground",
                )}
              />
              <Card className="transition-colors hover:border-border">
                <CardContent className="flex flex-wrap items-center justify-between gap-3 py-3">
                  <div className="min-w-0">
                    <div className="text-xs tabular-nums text-muted-foreground">
                      {/* Sebelumnya cap waktu ISO tercetak mentah di sini. */}
                      {formatDateTime(entry.date)}
                    </div>
                    <div className="truncate font-medium">
                      {entry.service_name}
                    </div>
                    <div className="truncate text-sm text-muted-foreground">
                      {entry.assignee_name}
                    </div>
                  </div>
                  <div className="flex shrink-0 items-center gap-2">
                    <Badge variant="outline" className="font-normal">
                      {t(`patient.history_type.${entry.type}`)}
                    </Badge>
                    <StatusBadge
                      status={entry.status}
                      label={t(`clinic.booking_status.${entry.status}`)}
                      variantMap={HISTORY_STATUS_VARIANTS}
                    />
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

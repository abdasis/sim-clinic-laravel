import { createFileRoute, useParams } from "@tanstack/react-router"
import { useEffect, useMemo, useState } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import { HugeiconsIcon } from "@hugeicons/react"
import { ClockIcon, EyeIcon } from "@hugeicons/core-free-icons"

import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { DataTable } from "#/components/datatable/datatable.tsx"
import { StatsSection } from "#/components/stats/stats-section.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Input } from "#/components/ui/input.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import { Label } from "#/components/ui/label.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useDataTable } from "#/hooks/use-data-table.ts"
import { useStats } from "#/hooks/use-stats.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import { formatDateTime } from "#/lib/format.ts"
import {
  useActivityFilterOptions,
  type ActivityRow,
} from "#/hooks/use-activity-log.ts"
import type { DataTableParams, DataTableResponse } from "#/types/data-table.ts"
import { ActivityDetailSheet } from "./components/activity-detail-sheet.tsx"

export const Route = createFileRoute("/$tenant/clinic/activity-logs/")({
  component: ActivityLogsPage,
})

/** Awal rentang bawaan: 30 hari terakhir, sama dengan rentang statistiknya. */
function defaultRange() {
  const today = new Date()
  const start = new Date(today)
  start.setDate(start.getDate() - 29)

  const iso = (date: Date) =>
    new Date(date.getTime() - date.getTimezoneOffset() * 60000)
      .toISOString()
      .slice(0, 10)

  return { from: iso(start), to: iso(today) }
}

function ActivityLogsPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/activity-logs/" })
  const { t } = useTrans()

  const initial = useMemo(defaultRange, [])
  const [from, setFrom] = useState(initial.from)
  const [to, setTo] = useState(initial.to)
  const [selected, setSelected] = useState<ActivityRow | null>(null)

  const options = useActivityFilterOptions(tenant)
  const stats = useStats({ tenant, module: "activity-logs" })

  const columns = useMemo<ColumnDef<ActivityRow>[]>(
    () => [
      {
        accessorKey: "logged_at",
        header: t("activity_log.logged_at"),
        cell: ({ row }) => (
          <span className="text-xs whitespace-nowrap tabular-nums text-muted-foreground">
            {formatDateTime(row.original.logged_at)}
          </span>
        ),
      },
      {
        id: "description",
        header: t("activity_log.summary"),
        cell: ({ row }) => (
          <div className="min-w-0 max-w-lg">
            <p className="truncate font-medium">
              {row.original.description ?? row.original.event_label}
            </p>
            {row.original.subject_name ? (
              <p className="truncate text-xs text-muted-foreground">
                {row.original.subject_type_label} · {row.original.subject_name}
              </p>
            ) : null}
          </div>
        ),
      },
      {
        accessorKey: "module",
        header: t("activity_log.module"),
        cell: ({ row }) => (
          <Badge variant="secondary" className="font-normal whitespace-nowrap">
            {row.original.module_label}
          </Badge>
        ),
      },
      {
        accessorKey: "event",
        header: t("activity_log.event"),
        cell: ({ row }) => (
          <span className="text-xs whitespace-nowrap text-muted-foreground">
            {row.original.event_label}
          </span>
        ),
      },
      {
        // Id-nya mengikuti nama penyaring server supaya pilihan faceted
        // langsung terkirim sebagai filter[causer_id].
        id: "causer_id",
        header: t("activity_log.causer"),
        cell: ({ row }) =>
          row.original.causer_name ? (
            <span className="whitespace-nowrap">{row.original.causer_name}</span>
          ) : (
            <span className="text-xs text-muted-foreground">
              {t("activity_log.system")}
            </span>
          ),
      },
      {
        id: "actions",
        header: "",
        cell: ({ row }) => (
          <div className="flex justify-end">
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  variant="ghost"
                  size="icon"
                  className="size-8 transition-colors"
                  aria-label={t("activity_log.detail")}
                  onClick={() => setSelected(row.original)}
                >
                  <HugeiconsIcon icon={EyeIcon} className="size-4" />
                </Button>
              </TooltipTrigger>
              <TooltipContent>{t("activity_log.detail")}</TooltipContent>
            </Tooltip>
          </div>
        ),
      },
    ],
    [t],
  )

  const { table, isLoading, meta, isError, error, refetch } =
    useDataTable<ActivityRow>({
      queryKey: ["activity-logs", tenant, from, to],
      queryFn: (params: DataTableParams) =>
        apiGet<DataTableResponse<ActivityRow>>(
          `/${tenant}/clinic/activity-logs`,
          {
            page: params.page,
            per_page: params.per_page,
            sort: params.sort,
            direction: params.direction,
            search: params.search,
            filter: { ...params.filters, from, to },
          },
        ),
      columns,
    })

  // `r` memuat ulang daftar dan angkanya sekaligus. Tanpa modifier supaya
  // tidak bertabrakan dengan reload browser (Cmd/Ctrl+R).
  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      const target = event.target as HTMLElement | null

      if (
        event.key !== "r" ||
        event.metaKey ||
        event.ctrlKey ||
        event.altKey ||
        target?.tagName === "INPUT" ||
        target?.tagName === "TEXTAREA" ||
        target?.isContentEditable
      ) {
        return
      }

      void refetch()
      void stats.refetch()
    }

    window.addEventListener("keydown", onKey)

    return () => window.removeEventListener("keydown", onKey)
  })

  return (
    <div>
      <ClinicBreadcrumb
        items={[
          {
            label: tenant,
            to: "/$tenant/clinic/activity-logs",
            params: { tenant },
          },
          { label: t("clinic.clinic") },
          { label: t("activity_log.title") },
        ]}
      />

      <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div className="min-w-0">
          <h1 className="flex items-center gap-2 text-xl font-semibold tracking-tight">
            <HugeiconsIcon
              icon={ClockIcon}
              className="size-5 text-muted-foreground"
            />
            {t("activity_log.title")}
          </h1>
          <p className="mt-1 max-w-2xl text-sm text-pretty text-muted-foreground">
            {t("activity_log.description")}
          </p>
        </div>

        <div className="flex flex-wrap items-end gap-2">
          <div className="space-y-1">
            <Label htmlFor="activity-from" className="text-xs">
              {t("activity_log.from")}
            </Label>
            <Input
              id="activity-from"
              type="date"
              value={from}
              max={to}
              onChange={(event) => setFrom(event.target.value)}
              className="h-8 w-40 tabular-nums"
            />
          </div>
          <div className="space-y-1">
            <Label htmlFor="activity-to" className="text-xs">
              {t("activity_log.to")}
            </Label>
            <Input
              id="activity-to"
              type="date"
              value={to}
              min={from}
              onChange={(event) => setTo(event.target.value)}
              className="h-8 w-40 tabular-nums"
            />
          </div>
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant="outline"
                size="sm"
                className="h-8"
                onClick={() => void refetch()}
              >
                {t("stats.refresh")}
              </Button>
            </TooltipTrigger>
            <TooltipContent className="flex items-center gap-2">
              {t("stats.refresh_hint")}
              <Kbd>r</Kbd>
            </TooltipContent>
          </Tooltip>
        </div>
      </div>

      <StatsSection
        stats={stats.data?.data}
        isLoading={stats.isLoading}
        isError={stats.isError}
        isFetching={stats.isFetching}
        onRefresh={() => void stats.refetch()}
      />

      <DataTable
        table={table}
        isLoading={isLoading}
        isError={isError}
        error={error}
        onRetry={() => void refetch()}
        searchPlaceholder={t("general.search")}
        meta={meta}
        emptyIllustration="default"
        emptyTitle={t("activity_log.empty_title")}
        emptyDescription={t("activity_log.empty_desc")}
        faceted={[
          {
            columnId: "module",
            title: t("activity_log.filter_module"),
            options: options.data?.data.modules ?? [],
          },
          {
            columnId: "event",
            title: t("activity_log.filter_event"),
            options: options.data?.data.events ?? [],
          },
          {
            columnId: "causer_id",
            title: t("activity_log.filter_causer"),
            options: options.data?.data.causers ?? [],
          },
        ]}
      />

      <ActivityDetailSheet
        activity={selected}
        onOpenChange={(open) => {
          if (!open) setSelected(null)
        }}
      />
    </div>
  )
}

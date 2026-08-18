import { createFileRoute, Link, useParams } from "@tanstack/react-router"
import { useState } from "react"
import { useQuery } from "@tanstack/react-query"
import { HugeiconsIcon } from "@hugeicons/react"
import {
  BubbleChatIcon,
  Link01Icon,
  Settings02Icon,
  TimeQuarterPassIcon,
} from "@hugeicons/core-free-icons"

import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { EmptyState } from "#/components/ui/empty-state.tsx"
import { Progress } from "#/components/ui/progress.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import { formatDateTime } from "#/lib/format.ts"
import { BroadcastFormDialog } from "./components/broadcast-form-dialog.tsx"
import { AutoReminderDialog } from "./components/auto-reminder-dialog.tsx"
import { BroadcastSettingsDialog } from "./components/broadcast-settings-dialog.tsx"
import {
  ConnectionDialog,
  RemindersDialog,
  TemplatesDialog,
} from "./components/whatsapp-tools.tsx"

export const Route = createFileRoute("/$tenant/clinic/broadcasts/")({
  component: BroadcastsPage,
})

interface BroadcastRow {
  id: number
  title: string
  kind_label?: string | null
  status?: string | null
  status_label?: string | null
  audience_label: string
  created_by_name?: string | null
  created_at?: string | null
  recipients_total: number
  recipients_sent: number
  recipients_pending: number
}

function BroadcastsPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/broadcasts/" })
  const { t } = useTrans()
  const [createOpen, setCreateOpen] = useState(false)
  const [settingsOpen, setSettingsOpen] = useState(false)
  const [connectionOpen, setConnectionOpen] = useState(false)
  const [templatesOpen, setTemplatesOpen] = useState(false)
  const [remindersOpen, setRemindersOpen] = useState(false)
  const [autoReminderOpen, setAutoReminderOpen] = useState(false)

  const dashboard = useQuery({
    queryKey: ["wa-dashboard", tenant],
    queryFn: () =>
      apiGet<{
        data: {
          today: { sent: number; failed: number; pending: number }
          reminders_today: { total: number; sent: number; failed: number }
          active_campaigns: number
        }
      }>(`/${tenant}/clinic/broadcasts/dashboard`),
  })

  const { data, isLoading } = useQuery({
    queryKey: ["broadcasts", tenant],
    queryFn: () =>
      apiGet<{ data: BroadcastRow[] }>(`/${tenant}/clinic/broadcasts`, {
        per_page: 50,
      }),
  })

  const rows = data?.data ?? []

  return (
    <div className="space-y-4">

      <div className="flex flex-wrap items-center justify-between gap-2">
        <h1 className="text-xl font-semibold">{t("broadcast.title")}</h1>
        <div className="flex flex-wrap gap-2">
          <Button variant="outline" size="sm" onClick={() => setConnectionOpen(true)} className="gap-1.5">
            <HugeiconsIcon icon={Link01Icon} strokeWidth={2} className="size-3.5" />
            {t("broadcast.connection")}
          </Button>
          <Button variant="outline" size="sm" onClick={() => setTemplatesOpen(true)}>
            {t("broadcast.templates")}
          </Button>
          <Button variant="outline" size="sm" onClick={() => setRemindersOpen(true)}>
            {t("broadcast.reminders")}
          </Button>
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant="outline"
                size="sm"
                onClick={() => setAutoReminderOpen(true)}
                className="gap-1.5"
              >
                <HugeiconsIcon
                  icon={TimeQuarterPassIcon}
                  strokeWidth={2}
                  className="size-3.5"
                />
                {t("broadcast.auto_reminder_title")}
              </Button>
            </TooltipTrigger>
            <TooltipContent className="max-w-64 text-pretty">
              {t("broadcast.auto_reminder_hint")}
            </TooltipContent>
          </Tooltip>
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant="outline"
                size="icon"
                aria-label={t("broadcast.settings")}
                onClick={() => setSettingsOpen(true)}
              >
                <HugeiconsIcon
                  icon={Settings02Icon}
                  strokeWidth={2}
                  className="size-4"
                />
              </Button>
            </TooltipTrigger>
            <TooltipContent>{t("broadcast.settings")}</TooltipContent>
          </Tooltip>
          <Button onClick={() => setCreateOpen(true)}>
            {t("broadcast.add")}
          </Button>
        </div>
      </div>

      {dashboard.data ? (
        <div className="grid gap-3 sm:grid-cols-3">
          <div className="rounded-lg border border-border/50 bg-card p-3">
            <p className="text-xs text-muted-foreground">{t("broadcast.today_messages")}</p>
            <p className="mt-1 text-lg font-semibold tabular-nums">
              {dashboard.data.data.today.sent}
              <span className="ml-1 text-xs font-normal text-muted-foreground">
                {t("broadcast.status.sent")}
              </span>
            </p>
            <p className="text-xs text-muted-foreground tabular-nums">
              {dashboard.data.data.today.failed} {t("broadcast.status.failed").toLowerCase()} · {dashboard.data.data.today.pending} {t("broadcast.status.pending").toLowerCase()}
            </p>
          </div>
          <div className="rounded-lg border border-border/50 bg-card p-3">
            <p className="text-xs text-muted-foreground">{t("broadcast.reminders_today")}</p>
            <p className="mt-1 text-lg font-semibold tabular-nums">
              {dashboard.data.data.reminders_today.total}
            </p>
            <p className="text-xs text-muted-foreground tabular-nums">
              {dashboard.data.data.reminders_today.sent} {t("broadcast.status.sent").toLowerCase()} · {dashboard.data.data.reminders_today.failed} {t("broadcast.status.failed").toLowerCase()}
            </p>
          </div>
          <div className="rounded-lg border border-border/50 bg-card p-3">
            <p className="text-xs text-muted-foreground">{t("broadcast.active_campaigns")}</p>
            <p className="mt-1 text-lg font-semibold tabular-nums">
              {dashboard.data.data.active_campaigns}
            </p>
          </div>
        </div>
      ) : null}

      {isLoading ? (
        <div className="space-y-2">
          {Array.from({ length: 3 }, (_, index) => (
            <Skeleton key={index} className="h-20 w-full rounded-lg" />
          ))}
        </div>
      ) : rows.length === 0 ? (
        <EmptyState
          className="py-16"
          illustration="default"
          title={t("broadcast.empty_title")}
          description={t("broadcast.empty_desc")}
          action={
            <Button size="sm" onClick={() => setCreateOpen(true)}>
              {t("broadcast.add")}
            </Button>
          }
        />
      ) : (
        <ul className="space-y-2">
          {rows.map((row) => {
            const progress =
              row.recipients_total > 0
                ? Math.round((row.recipients_sent / row.recipients_total) * 100)
                : 0

            return (
              <li key={row.id}>
                <Link
                  to="/$tenant/clinic/broadcasts/$id"
                  params={{ tenant, id: String(row.id) }}
                  className="group block rounded-lg border border-border/50 bg-card p-4 shadow-sm transition-[transform,border-color] duration-150 ease-out hover:-translate-y-0.5 hover:border-border focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                  <div className="flex flex-wrap items-start justify-between gap-2">
                    <div className="flex min-w-0 items-center gap-2">
                      <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground transition-colors group-hover:text-foreground">
                        <HugeiconsIcon
                          icon={BubbleChatIcon}
                          strokeWidth={2}
                          className="size-4"
                        />
                      </span>
                      <div className="min-w-0">
                        <p className="truncate font-medium">{row.title}</p>
                        <p className="text-xs text-muted-foreground">
                          {row.kind_label ? `${row.kind_label} · ` : ""}
                          {row.status_label ? `${row.status_label} · ` : ""}
                          {row.audience_label}
                          {row.created_at
                            ? ` · ${formatDateTime(row.created_at)}`
                            : ""}
                          {row.created_by_name ? ` · ${row.created_by_name}` : ""}
                        </p>
                      </div>
                    </div>
                    <Badge
                      variant={
                        row.recipients_pending === 0 ? "default" : "secondary"
                      }
                      className="shrink-0 font-normal tabular-nums"
                    >
                      {t("broadcast.progress")
                        .replace(":sent", String(row.recipients_sent))
                        .replace(":total", String(row.recipients_total))}
                    </Badge>
                  </div>
                  <Progress value={progress} className="mt-3 h-1.5" />
                </Link>
              </li>
            )
          })}
        </ul>
      )}

      <BroadcastFormDialog
        tenant={tenant}
        open={createOpen}
        onOpenChange={setCreateOpen}
      />
      <BroadcastSettingsDialog
        tenant={tenant}
        open={settingsOpen}
        onOpenChange={setSettingsOpen}
      />
      <AutoReminderDialog
        tenant={tenant}
        open={autoReminderOpen}
        onOpenChange={setAutoReminderOpen}
      />
      <ConnectionDialog tenant={tenant} open={connectionOpen} onOpenChange={setConnectionOpen} />
      <TemplatesDialog tenant={tenant} open={templatesOpen} onOpenChange={setTemplatesOpen} />
      <RemindersDialog tenant={tenant} open={remindersOpen} onOpenChange={setRemindersOpen} />
    </div>
  )
}

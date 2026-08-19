import { useCallback, useEffect } from "react"
import { toast } from "sonner"
import { Copy01Icon } from "@hugeicons/core-free-icons"
import { HugeiconsIcon } from "@hugeicons/react"

import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import { Separator } from "#/components/ui/separator.tsx"
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "#/components/ui/sheet.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { formatDateTime } from "#/lib/format.ts"
import type { ActivityRow } from "#/hooks/use-activity-log.ts"

/**
 * Rincian satu baris log: narasi, pelakunya, lalu nilai sebelum-sesudah.
 *
 * Nilai ditaruh berdampingan dalam satu baris supaya mata cukup bergerak
 * mendatar untuk membandingkan — bukan dua blok JSON yang harus diadu.
 */
export function ActivityDetailSheet({
  activity,
  onOpenChange,
}: {
  activity: ActivityRow | null
  onOpenChange: (open: boolean) => void
}) {
  const { t } = useTrans()

  const copy = useCallback(() => {
    if (!activity) return

    void navigator.clipboard
      .writeText(JSON.stringify(activity, null, 2))
      .then(() => toast.success(t("activity_log.copied")))
      .catch(() => toast.error(t("general.error")))
  }, [activity, t])

  // `c` tanpa modifier: panel ini tidak punya isian teks, jadi tidak ada
  // yang bisa tertimpa selain saat fokus sedang di tombol tutup.
  useEffect(() => {
    if (activity === null) return

    const onKey = (event: KeyboardEvent) => {
      if (event.key === "c" && !event.metaKey && !event.ctrlKey && !event.altKey) {
        copy()
      }
    }

    window.addEventListener("keydown", onKey)

    return () => window.removeEventListener("keydown", onKey)
  }, [activity, copy])

  return (
    <Sheet open={activity !== null} onOpenChange={onOpenChange}>
      <SheetContent className="w-full gap-0 overflow-y-auto sm:max-w-lg">
        {activity ? (
          <>
            <SheetHeader className="gap-1.5">
              <div className="flex flex-wrap items-center gap-2">
                <Badge variant="secondary" className="font-normal">
                  {activity.module_label}
                </Badge>
                <Badge variant="outline" className="font-normal">
                  {activity.event_label}
                </Badge>
              </div>
              <SheetTitle className="text-base leading-snug text-pretty">
                {activity.description ?? t("activity_log.detail")}
              </SheetTitle>
              <SheetDescription>
                {t("activity_log.detail_desc")}
              </SheetDescription>
            </SheetHeader>

            <div className="space-y-4 px-4 pb-6">
              <dl className="grid grid-cols-[7rem_1fr] gap-x-3 gap-y-2 text-sm">
                <MetaRow label={t("activity_log.logged_at")}>
                  <span className="tabular-nums">
                    {formatDateTime(activity.logged_at)}
                  </span>
                </MetaRow>
                <MetaRow label={t("activity_log.causer")}>
                  {activity.causer_name ? (
                    <span>
                      {activity.causer_name}
                      {activity.causer_email ? (
                        <span className="block text-xs text-muted-foreground">
                          {activity.causer_email}
                        </span>
                      ) : null}
                    </span>
                  ) : (
                    <span className="text-muted-foreground">
                      {t("activity_log.system")}
                    </span>
                  )}
                </MetaRow>
                <MetaRow label={t("activity_log.subject")}>
                  <SubjectValue activity={activity} />
                </MetaRow>
                <MetaRow label={t("activity_log.event")}>
                  <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">
                    {activity.event ?? "-"}
                  </code>
                </MetaRow>
              </dl>

              <Separator />

              {activity.changes.length > 0 ? (
                <section className="space-y-2">
                  <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    {t("activity_log.changes")}
                  </h3>
                  <div className="overflow-hidden rounded-md border border-border/60">
                    <table className="w-full text-sm">
                      <thead>
                        <tr className="border-b border-border/60 bg-muted/40 text-xs text-muted-foreground">
                          <th className="px-2.5 py-1.5 text-left font-medium">
                            {t("activity_log.attributes")}
                          </th>
                          <th className="px-2.5 py-1.5 text-left font-medium">
                            {t("activity_log.before")}
                          </th>
                          <th className="px-2.5 py-1.5 text-left font-medium">
                            {t("activity_log.after")}
                          </th>
                        </tr>
                      </thead>
                      <tbody>
                        {activity.changes.map((change) => (
                          <tr
                            key={change.field}
                            className="border-b border-border/40 last:border-0"
                          >
                            <td className="px-2.5 py-1.5 align-top font-medium">
                              {change.label}
                            </td>
                            <td className="px-2.5 py-1.5 align-top text-muted-foreground">
                              <Value value={change.old} />
                            </td>
                            <td className="px-2.5 py-1.5 align-top">
                              <Value value={change.new} />
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </section>
              ) : null}

              {Object.keys(activity.context).length > 0 ? (
                <section className="space-y-2">
                  <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    {t("activity_log.attributes")}
                  </h3>
                  <dl className="grid grid-cols-[7rem_1fr] gap-x-3 gap-y-1.5 text-sm">
                    {Object.entries(activity.context).map(([key, value]) => (
                      <MetaRow key={key} label={key}>
                        <Value value={value} />
                      </MetaRow>
                    ))}
                  </dl>
                </section>
              ) : null}

              {!activity.has_details ? (
                <p className="rounded-md border border-dashed border-border/60 px-3 py-6 text-center text-sm text-muted-foreground">
                  {t("activity_log.no_properties")}
                </p>
              ) : null}

              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={copy}
                    className="w-full transition-colors"
                  >
                    <HugeiconsIcon icon={Copy01Icon} className="size-4" />
                    {t("activity_log.copy")}
                  </Button>
                </TooltipTrigger>
                <TooltipContent className="flex items-center gap-2">
                  {t("activity_log.copy")}
                  <Kbd>c</Kbd>
                </TooltipContent>
              </Tooltip>
            </div>
          </>
        ) : null}
      </SheetContent>
    </Sheet>
  )
}

function MetaRow({
  label,
  children,
}: {
  label: string
  children: React.ReactNode
}) {
  return (
    <>
      <dt className="truncate text-xs text-muted-foreground">{label}</dt>
      <dd className="min-w-0 break-words">{children}</dd>
    </>
  )
}

function SubjectValue({ activity }: { activity: ActivityRow }) {
  const { t } = useTrans()

  if (activity.subject_type === null) {
    return (
      <span className="text-muted-foreground">
        {t("activity_log.no_subject")}
      </span>
    )
  }

  return (
    <span>
      {activity.subject_name ?? (
        <span className="text-muted-foreground italic">
          {t("activity_log.unknown_subject")}
        </span>
      )}
      <span className="block text-xs text-muted-foreground">
        {activity.subject_type_label}
        {activity.subject_id ? ` #${activity.subject_id}` : ""}
      </span>
    </span>
  )
}

/** Nilai apa pun jadi teks yang bisa dibaca; objek tetap ditampilkan utuh. */
function Value({ value }: { value: unknown }) {
  if (value === null || value === undefined || value === "") {
    return <span className="text-muted-foreground">—</span>
  }

  if (typeof value === "boolean") {
    return <span className="tabular-nums">{value ? "Ya" : "Tidak"}</span>
  }

  if (typeof value === "object") {
    return (
      <pre className="max-w-full overflow-x-auto rounded bg-muted/60 p-1.5 font-mono text-xs">
        {JSON.stringify(value, null, 2)}
      </pre>
    )
  }

  return <span className="tabular-nums">{String(value)}</span>
}

import { useQuery } from "@tanstack/react-query"
import { HugeiconsIcon } from "@hugeicons/react"
import {
  Alert01Icon,
  CheckmarkCircle02Icon,
  RefreshIcon,
} from "@hugeicons/core-free-icons"

import { Button } from "#/components/ui/button.tsx"
import { Card, CardContent } from "#/components/ui/card.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import { formatDateTime } from "#/lib/format.ts"
import { cn } from "#/lib/utils.ts"

interface DiagnosticCheck {
  key: string
  ok: boolean
  /** Langkah berikutnya; hanya ikut saat mata rantainya putus. */
  hint: string | null
}

interface Diagnostics {
  healthy: boolean
  checks: DiagnosticCheck[]
  last_inbound_at: string | null
  last_reply_at: string | null
}

/**
 * Kesehatan tiap mata rantai yang membuat chatbot bisa menjawab.
 *
 * Ketika chatbot berhenti membalas, yang putus hampir selalu di luar kode —
 * saklarnya mati, kunci AI belum dipasang, gateway belum disetel, sesinya
 * terputus, atau webhook tidak pernah sampai. Sebelum ini tidak satu pun
 * terlihat dari layar mana pun, dan satu-satunya cara mengetahuinya adalah
 * membaca log server yang tidak bisa diakses klinik.
 *
 * Yang ditampilkan bukan sekadar sehat atau tidak, melainkan langkah
 * berikutnya untuk tiap mata rantai yang putus: pemberitahuan yang tidak
 * memberi tahu apa yang harus dilakukan hanya memindahkan kebingungan.
 */
export function ChatbotDiagnostics({ tenant }: { tenant: string }) {
  const { t } = useTrans()

  const query = useQuery({
    queryKey: ["chatbot-diagnostics", tenant],
    queryFn: () =>
      apiGet<{ data: Diagnostics }>(`/${tenant}/clinic/chatbot/diagnostics`),
  })

  const data = query.data?.data

  return (
    <Card className="border-border/50 shadow-sm">
      <CardContent className="space-y-3 p-4">
        <div className="flex flex-wrap items-start justify-between gap-2">
          <div className="min-w-0">
            <h2 className="text-base font-semibold">
              {t("chatbot.diagnostics.title")}
            </h2>
            <p className="mt-0.5 text-xs text-pretty text-muted-foreground">
              {t("chatbot.diagnostics.desc")}
            </p>
          </div>
          <Button
            variant="outline"
            size="sm"
            className="gap-1.5"
            disabled={query.isFetching}
            onClick={() => void query.refetch()}
          >
            <HugeiconsIcon
              icon={RefreshIcon}
              className={cn("size-3.5", query.isFetching && "animate-spin")}
            />
            {t("chatbot.diagnostics.recheck")}
          </Button>
        </div>

        {query.isLoading || !data ? (
          <div className="space-y-1.5">
            <Skeleton className="h-8 w-full" />
            <Skeleton className="h-8 w-full" />
            <Skeleton className="h-8 w-full" />
          </div>
        ) : (
          <>
            <p
              className={cn(
                "rounded-md border px-3 py-2 text-xs",
                data.healthy
                  ? "border-emerald-500/40 bg-emerald-500/5"
                  : "border-destructive/40 bg-destructive/5 font-medium",
              )}
            >
              {data.healthy
                ? t("chatbot.diagnostics.healthy")
                : t("chatbot.diagnostics.unhealthy")}
            </p>

            <ul className="divide-y divide-border/40 rounded-md border border-border/60">
              {data.checks.map((check) => (
                <li
                  key={check.key}
                  className="flex items-start gap-2.5 px-3 py-2"
                >
                  <HugeiconsIcon
                    icon={check.ok ? CheckmarkCircle02Icon : Alert01Icon}
                    strokeWidth={2}
                    className={cn(
                      "mt-0.5 size-4 shrink-0",
                      check.ok ? "text-emerald-600" : "text-destructive",
                    )}
                  />
                  <div className="min-w-0 space-y-0.5">
                    <p className="text-sm">
                      {t(`chatbot.diagnostics.label.${check.key}`)}
                    </p>
                    {/* Petunjuknya hanya muncul saat memang ada yang perlu
                        dikerjakan; yang sehat tidak perlu penjelasan. */}
                    {check.hint ? (
                      <p className="text-xs text-pretty text-muted-foreground">
                        {check.hint}
                      </p>
                    ) : null}
                  </div>
                </li>
              ))}
            </ul>

            <dl className="flex flex-wrap gap-x-6 gap-y-1 text-xs">
              <div className="flex items-baseline gap-1.5">
                <dt className="text-muted-foreground">
                  {t("chatbot.diagnostics.last_inbound")}
                </dt>
                <dd className="tabular-nums">
                  {data.last_inbound_at
                    ? formatDateTime(data.last_inbound_at)
                    : t("chatbot.diagnostics.never")}
                </dd>
              </div>
              <div className="flex items-baseline gap-1.5">
                <dt className="text-muted-foreground">
                  {t("chatbot.diagnostics.last_reply")}
                </dt>
                <dd className="tabular-nums">
                  {data.last_reply_at
                    ? formatDateTime(data.last_reply_at)
                    : t("chatbot.diagnostics.never")}
                </dd>
              </div>
            </dl>
          </>
        )}
      </CardContent>
    </Card>
  )
}

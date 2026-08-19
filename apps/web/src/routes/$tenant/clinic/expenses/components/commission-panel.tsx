import { useState } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { HugeiconsIcon } from "@hugeicons/react"
import { Calculator01Icon, PlusSignIcon, Delete02Icon } from "@hugeicons/core-free-icons"

import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Input } from "#/components/ui/input.tsx"
import { Label } from "#/components/ui/label.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { Switch } from "#/components/ui/switch.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiDelete, apiGet, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { formatCurrency, formatDateTime } from "#/lib/format.ts"
import { CommissionRuleDialog, type CommissionRule } from "./commission-rule-dialog.tsx"

interface CommissionLine {
  rule_name: string
  type: string
  basis: number
  amount: number
}

interface CommissionRow {
  therapist_id: number
  therapist_name: string
  revenue: number
  visits: number
  new_patients: number
  lines: CommissionLine[]
  total: number
}

interface CommissionPanelProps {
  tenant: string
  from: string
  to: string
  /** Membuka form pengeluaran dengan nominal fee yang sudah terisi. */
  onPost: (preset: { amount: number; description: string }) => void
}

/**
 * Aturan fee dan pratinjaunya. Sengaja tidak menyimpan apa pun sendiri:
 * angkanya ditinjau dulu, lalu dibukukan lewat form pengeluaran biasa
 * sehingga tetap satu pintu masuk ke buku kas.
 */
export function CommissionPanel({ tenant, from, to, onPost }: CommissionPanelProps) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const [ruleOpen, setRuleOpen] = useState(false)
  const [editing, setEditing] = useState<CommissionRule | undefined>()

  const rules = useQuery({
    queryKey: ["commission-rules", tenant],
    queryFn: () =>
      apiGet<{ data: CommissionRule[] }>(`/${tenant}/clinic/commission-rules`),
  })

  const preview = useQuery({
    queryKey: ["commission-preview", tenant, from, to],
    queryFn: () =>
      apiGet<{ data: { rows: CommissionRow[]; total: number } }>(
        `/${tenant}/clinic/commission-rules/calculate`,
        { from, to },
      ),
    enabled: Boolean(from && to),
  })

  const toggle = useMutation({
    mutationFn: (rule: CommissionRule) =>
      apiPut(`/${tenant}/clinic/commission-rules/${rule.id}`, {
        ...rule,
        is_active: !rule.is_active,
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["commission-rules"] })
      qc.invalidateQueries({ queryKey: ["commission-preview"] })
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  const remove = useMutation({
    mutationFn: (id: number) =>
      apiDelete(`/${tenant}/clinic/commission-rules/${id}`),
    onSuccess: () => {
      toast.success(t("commission.deleted"))
      qc.invalidateQueries({ queryKey: ["commission-rules"] })
      qc.invalidateQueries({ queryKey: ["commission-preview"] })
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  const rows = preview.data?.data.rows ?? []

  return (
    <section className="space-y-4 rounded-lg border border-border/50 bg-card p-4">
      <header className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <h2 className="flex items-center gap-2 text-sm font-semibold">
            <HugeiconsIcon
              icon={Calculator01Icon}
              strokeWidth={2}
              className="size-4 text-muted-foreground"
            />
            {t("commission.title")}
          </h2>
          <p className="mt-0.5 text-xs text-muted-foreground">
            {t("commission.description")}
          </p>
        </div>
        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              size="sm"
              variant="outline"
              className="gap-1.5"
              onClick={() => {
                setEditing(undefined)
                setRuleOpen(true)
              }}
            >
              <HugeiconsIcon
                icon={PlusSignIcon}
                strokeWidth={2}
                className="size-3.5"
              />
              {t("commission.add")}
            </Button>
          </TooltipTrigger>
          <TooltipContent>{t("commission.add")}</TooltipContent>
        </Tooltip>
      </header>

      {rules.isLoading ? (
        <Skeleton className="h-24 w-full" />
      ) : (rules.data?.data.length ?? 0) === 0 ? (
        <p className="rounded-md border border-dashed border-border/60 px-3 py-4 text-center text-xs text-muted-foreground">
          {t("commission.empty_desc")}
        </p>
      ) : (
        <ul className="divide-y divide-border/50 rounded-md border border-border/50">
          {rules.data?.data.map((rule) => (
            <li
              key={rule.id}
              className="flex items-center gap-3 px-3 py-2 transition-colors hover:bg-muted/40"
            >
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium">{rule.name}</p>
                <p className="text-xs text-muted-foreground tabular-nums">
                  {rule.type === "revenue_percent"
                    ? `${Number(rule.percent)}% · ${t("commission.min_revenue")} ${formatCurrency(Number(rule.min_revenue))}`
                    : formatCurrency(Number(rule.amount))}
                </p>
                {/* Tarif yang pernah diganti punya titik mulai; yang perdana
                    tidak, dan menampilkannya sebagai tanggal justru mengarang
                    kapan kebijakannya dimulai. */}
                {rule.effective_from ? (
                  <p className="text-2xs text-muted-foreground/80">
                    {t("commission.effective_since").replace(
                      ":date",
                      formatDateTime(rule.effective_from),
                    )}
                  </p>
                ) : null}
              </div>
              <Badge variant="outline" className="shrink-0 font-normal">
                {rule.type_label}
              </Badge>
              {/* Aturan yang dibatasi ke satu terapis harus terlihat bedanya —
                  kalau tidak, orang mengira semua terapis kecipratan. */}
              <Badge
                variant={rule.therapist_name ? "default" : "secondary"}
                className="shrink-0 font-normal"
              >
                {rule.therapist_name ?? t("commission.all_therapists")}
              </Badge>
              <Tooltip>
                <TooltipTrigger asChild>
                  <span>
                    <Switch
                      checked={rule.is_active}
                      onCheckedChange={() => toggle.mutate(rule)}
                      aria-label={t("commission.is_active")}
                    />
                  </span>
                </TooltipTrigger>
                <TooltipContent>{t("commission.is_active")}</TooltipContent>
              </Tooltip>
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="icon"
                    className="size-7 text-muted-foreground hover:text-foreground"
                    aria-label={t("general.edit")}
                    onClick={() => {
                      setEditing(rule)
                      setRuleOpen(true)
                    }}
                  >
                    <HugeiconsIcon
                      icon={Calculator01Icon}
                      strokeWidth={2}
                      className="size-3.5"
                    />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>{t("general.edit")}</TooltipContent>
              </Tooltip>
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="icon"
                    className="size-7 text-muted-foreground hover:text-destructive"
                    aria-label={t("general.delete")}
                    onClick={() => remove.mutate(rule.id)}
                  >
                    <HugeiconsIcon
                      icon={Delete02Icon}
                      strokeWidth={2}
                      className="size-3.5"
                    />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>{t("general.delete")}</TooltipContent>
              </Tooltip>
            </li>
          ))}
        </ul>
      )}

      <p className="text-xs text-muted-foreground">{t("commission.tier_note")}</p>

      <div className="space-y-2 border-t border-border/50 pt-4">
        <div className="flex items-baseline justify-between gap-2">
          <h3 className="text-sm font-semibold">{t("commission.preview")}</h3>
          <span className="text-xs text-muted-foreground">
            {t("commission.preview_hint")}
          </span>
        </div>

        {preview.isLoading ? (
          <Skeleton className="h-20 w-full" />
        ) : rows.length === 0 ? (
          <p className="rounded-md border border-dashed border-border/60 px-3 py-4 text-center text-xs text-muted-foreground">
            {t("commission.no_result")}
          </p>
        ) : (
          <>
            <ul className="divide-y divide-border/50 rounded-md border border-border/50">
              {rows.map((row) => (
                <li key={row.therapist_id} className="px-3 py-2">
                  <div className="flex items-baseline justify-between gap-2">
                    <p className="truncate text-sm font-medium">
                      {row.therapist_name}
                    </p>
                    <p className="shrink-0 text-sm font-semibold tabular-nums">
                      {formatCurrency(row.total)}
                    </p>
                  </div>
                  <p className="text-xs text-muted-foreground tabular-nums">
                    {/* "Penjualan" kerap dikira omzet klinik; keterangannya
                        ditempelkan di angkanya, bukan disembunyikan di bantuan. */}
                    <Tooltip>
                      <TooltipTrigger asChild>
                        <span className="cursor-help underline decoration-dotted underline-offset-2">
                          {t("commission.revenue")}
                        </span>
                      </TooltipTrigger>
                      <TooltipContent className="max-w-64">
                        {t("commission.revenue_hint")}
                      </TooltipContent>
                    </Tooltip>{" "}
                    {formatCurrency(row.revenue)} ·{" "}
                    {row.visits} {t("commission.visits").toLowerCase()} ·{" "}
                    {row.new_patients} {t("commission.new_patients").toLowerCase()}
                  </p>
                  <ul className="mt-1 space-y-0.5">
                    {row.lines.map((line) => (
                      <li
                        key={line.rule_name}
                        className="flex items-baseline justify-between gap-2 text-xs text-muted-foreground"
                      >
                        <span className="truncate">{line.rule_name}</span>
                        <span className="shrink-0 tabular-nums">
                          {formatCurrency(line.amount)}
                        </span>
                      </li>
                    ))}
                  </ul>
                </li>
              ))}
            </ul>

            <div className="flex flex-wrap items-center justify-between gap-2 rounded-md bg-muted/50 px-3 py-2">
              <span className="text-sm font-medium">
                {t("commission.total")}
              </span>
              <div className="flex items-center gap-3">
                <span className="text-base font-semibold tabular-nums">
                  {formatCurrency(preview.data?.data.total ?? 0)}
                </span>
                <Button
                  size="sm"
                  onClick={() =>
                    onPost({
                      amount: preview.data?.data.total ?? 0,
                      description: `${t("commission.title")} ${from} — ${to}`,
                    })
                  }
                >
                  {t("commission.post_as_expense")}
                </Button>
              </div>
            </div>
          </>
        )}
      </div>

      <CommissionRuleDialog
        tenant={tenant}
        rule={editing}
        open={ruleOpen}
        onOpenChange={setRuleOpen}
      />
    </section>
  )
}

/** Dipakai halaman induk untuk label periode. */
export function PeriodFields({
  from,
  to,
  onFrom,
  onTo,
}: {
  from: string
  to: string
  onFrom: (value: string) => void
  onTo: (value: string) => void
}) {
  const { t } = useTrans()

  return (
    <div className="flex flex-wrap items-end gap-2">
      <div className="space-y-1">
        <Label htmlFor="expense-from" className="text-xs">
          {t("promo.starts_at")}
        </Label>
        <Input
          id="expense-from"
          type="date"
          value={from}
          onChange={(event) => onFrom(event.target.value)}
          className="h-8 w-40 tabular-nums"
        />
      </div>
      <div className="space-y-1">
        <Label htmlFor="expense-to" className="text-xs">
          {t("promo.ends_at")}
        </Label>
        <Input
          id="expense-to"
          type="date"
          value={to}
          onChange={(event) => onTo(event.target.value)}
          className="h-8 w-40 tabular-nums"
        />
      </div>
    </div>
  )
}

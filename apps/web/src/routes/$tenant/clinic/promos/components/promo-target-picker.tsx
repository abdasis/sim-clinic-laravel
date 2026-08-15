import { useMemo, useState } from "react"
import { useQuery } from "@tanstack/react-query"
import { HugeiconsIcon } from "@hugeicons/react"
import { Cancel01Icon, Search01Icon } from "@hugeicons/core-free-icons"

import { Badge } from "#/components/ui/badge.tsx"
import { Checkbox } from "#/components/ui/checkbox.tsx"
import { Input } from "#/components/ui/input.tsx"
import { ScrollArea } from "#/components/ui/scroll-area.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { Tabs, TabsList, TabsTrigger } from "#/components/ui/tabs.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import { formatCurrency } from "#/lib/format.ts"
import { cn } from "#/lib/utils.ts"

export interface PromoTarget {
  type: "service" | "product"
  id: number
  name?: string | null
}

interface CatalogRow {
  id: number
  name: string
  price: string
}

interface PromoTargetPickerProps {
  tenant: string
  value: PromoTarget[]
  onChange: (targets: PromoTarget[]) => void
  /** Pesan galat dari server/skema, ditampilkan di bawah daftar. */
  error?: string
}

const keyOf = (target: Pick<PromoTarget, "type" | "id">) =>
  `${target.type}:${target.id}`

/**
 * Pemilih sasaran promo. Layanan dan produk dipisah lewat tab karena keduanya
 * bisa panjang, tapi pilihannya satu keranjang — promo memang boleh memuat
 * campuran keduanya.
 */
export function PromoTargetPicker({
  tenant,
  value,
  onChange,
  error,
}: PromoTargetPickerProps) {
  const { t } = useTrans()
  const [tab, setTab] = useState<"service" | "product">("service")
  const [search, setSearch] = useState("")

  const query = useQuery({
    queryKey: ["promo-targets", tenant, tab],
    queryFn: () =>
      apiGet<{ data: CatalogRow[] }>(
        `/${tenant}/clinic/${tab === "service" ? "services" : "products"}`,
        { per_page: 200 },
      ),
  })

  const rows = useMemo(() => {
    const keyword = search.trim().toLowerCase()
    const all = query.data?.data ?? []

    return keyword === ""
      ? all
      : all.filter((row) => row.name.toLowerCase().includes(keyword))
  }, [query.data, search])

  const selected = useMemo(
    () => new Set(value.map((target) => keyOf(target))),
    [value],
  )

  const toggle = (row: CatalogRow) => {
    const target: PromoTarget = { type: tab, id: row.id, name: row.name }

    onChange(
      selected.has(keyOf(target))
        ? value.filter((item) => keyOf(item) !== keyOf(target))
        : [...value, target],
    )
  }

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between gap-2">
        <p className="text-sm font-medium">{t("promo.targets")}</p>
        <span className="text-xs text-muted-foreground tabular-nums">
          {t("promo.targets_count").replace(":count", String(value.length))}
        </span>
      </div>

      {/* Yang sudah dipilih tetap terlihat walau tabnya berganti — kalau tidak,
          pilihan di tab lain seolah hilang. */}
      {value.length > 0 ? (
        <div className="flex flex-wrap gap-1.5 rounded-md border border-border/50 bg-muted/30 p-2">
          {value.map((target) => (
            <Badge
              key={keyOf(target)}
              variant="secondary"
              className="gap-1 pr-1 font-normal"
            >
              {target.name ?? `#${target.id}`}
              <Tooltip>
                <TooltipTrigger asChild>
                  <button
                    type="button"
                    aria-label={t("general.delete")}
                    className="rounded-sm p-0.5 text-muted-foreground transition-colors hover:bg-background hover:text-destructive"
                    onClick={() =>
                      onChange(
                        value.filter((item) => keyOf(item) !== keyOf(target)),
                      )
                    }
                  >
                    <HugeiconsIcon
                      icon={Cancel01Icon}
                      strokeWidth={2}
                      className="size-3"
                    />
                  </button>
                </TooltipTrigger>
                <TooltipContent>{t("general.delete")}</TooltipContent>
              </Tooltip>
            </Badge>
          ))}
        </div>
      ) : (
        <p className="rounded-md border border-dashed border-border/60 px-3 py-2 text-xs text-muted-foreground">
          {t("promo.no_targets")}
        </p>
      )}

      <Tabs value={tab} onValueChange={(next) => setTab(next as typeof tab)}>
        <TabsList className="h-8">
          <TabsTrigger value="service" className="text-xs">
            {t("promo.services")}
          </TabsTrigger>
          <TabsTrigger value="product" className="text-xs">
            {t("promo.products")}
          </TabsTrigger>
        </TabsList>
      </Tabs>

      <div className="relative">
        <HugeiconsIcon
          icon={Search01Icon}
          strokeWidth={2}
          className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <Input
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          placeholder={t("promo.pick_targets")}
          aria-label={t("promo.pick_targets")}
          className="h-8 pl-8"
        />
      </div>

      <ScrollArea className="h-48 rounded-md border border-border/50">
        {query.isLoading ? (
          <div className="space-y-1.5 p-2">
            {Array.from({ length: 5 }, (_, index) => (
              <Skeleton key={index} className="h-8 w-full" />
            ))}
          </div>
        ) : rows.length === 0 ? (
          <p className="p-4 text-center text-xs text-muted-foreground">
            {t("general.no_data")}
          </p>
        ) : (
          <ul className="p-1">
            {rows.map((row) => {
              const checked = selected.has(keyOf({ type: tab, id: row.id }))

              return (
                <li key={row.id}>
                  <label
                    className={cn(
                      "flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-muted/60",
                      checked && "bg-muted/70",
                    )}
                  >
                    <Checkbox
                      checked={checked}
                      onCheckedChange={() => toggle(row)}
                    />
                    <span className="min-w-0 flex-1 truncate">{row.name}</span>
                    <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                      {formatCurrency(Number(row.price))}
                    </span>
                  </label>
                </li>
              )
            })}
          </ul>
        )}
      </ScrollArea>

      {error ? <p className="text-xs text-destructive">{error}</p> : null}
    </div>
  )
}

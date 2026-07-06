import { useEffect, useMemo, useState } from "react"
import { useQuery } from "@tanstack/react-query"
import { Trash2Icon } from "lucide-react"
import { Button } from "#/components/ui/button.tsx"
import { Input } from "#/components/ui/input.tsx"
import {
  NativeSelect,
  NativeSelectOptGroup,
  NativeSelectOption,
} from "#/components/ui/native-select.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import { formatCurrency } from "./format.ts"

interface CatalogRow {
  id: number
  name: string
  price: string
  stock_balance?: number | string
}

export interface LineItem {
  key: string
  kind: "service" | "product"
  refId: number
  name: string
  unitPrice: number
  qty: number
  stock: number | null
}

interface TransactionItemListProps {
  tenant: string
  onChange: (items: LineItem[], total: number) => void
}

function toNumber(value: unknown): number {
  const n = Number(value)
  return Number.isFinite(n) ? n : 0
}

let counter = 0
function nextKey(): string {
  counter += 1
  return `row-${counter}`
}

export function TransactionItemList({ tenant, onChange }: TransactionItemListProps) {
  const { t } = useTrans()
  const [rows, setRows] = useState<LineItem[]>([])

  const services = useQuery({
    queryKey: ["services", tenant, "catalog"],
    queryFn: () =>
      apiGet<{ data: CatalogRow[] }>(`/${tenant}/clinic/services`, { per_page: 100 }),
  })
  const products = useQuery({
    queryKey: ["products", tenant, "catalog"],
    queryFn: () =>
      apiGet<{ data: CatalogRow[] }>(`/${tenant}/clinic/products`, { per_page: 100 }),
  })

  const total = useMemo(
    () => rows.reduce((sum, r) => sum + r.unitPrice * r.qty, 0),
    [rows],
  )

  useEffect(() => {
    onChange(rows, total)
  }, [rows, total, onChange])

  function addRow() {
    setRows((prev) => [
      ...prev,
      { key: nextKey(), kind: "service", refId: 0, name: "", unitPrice: 0, qty: 1, stock: null },
    ])
  }

  function removeRow(key: string) {
    setRows((prev) => prev.filter((r) => r.key !== key))
  }

  function selectRef(key: string, encoded: string) {
    const [kind, idStr] = encoded.split(":")
    const id = Number(idStr)
    const source = kind === "product" ? products.data?.data : services.data?.data
    const found = source?.find((c) => c.id === id)
    setRows((prev) =>
      prev.map((r) =>
        r.key === key
          ? {
              ...r,
              kind: kind === "product" ? "product" : "service",
              refId: id,
              name: found?.name ?? "",
              unitPrice: toNumber(found?.price),
              stock: kind === "product" ? toNumber(found?.stock_balance) : null,
            }
          : r,
      ),
    )
  }

  function setQty(key: string, qty: number) {
    setRows((prev) =>
      prev.map((r) => (r.key === key ? { ...r, qty: qty < 1 ? 1 : qty } : r)),
    )
  }

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-semibold">{t("pos.items")}</h2>
        <Button type="button" size="sm" variant="outline" onClick={addRow}>
          {t("general.create")}
        </Button>
      </div>

      <div className="space-y-2">
        {rows.map((row) => {
          const overStock =
            row.stock !== null && row.qty > row.stock
          return (
            <div key={row.key} className="flex items-end gap-2">
              <div className="flex-1">
                <NativeSelect
                  className="w-full"
                  value={row.refId ? `${row.kind}:${row.refId}` : ""}
                  onChange={(e) => selectRef(row.key, e.target.value)}
                >
                  <NativeSelectOption value="">{t("pos.item")}</NativeSelectOption>
                  <NativeSelectOptGroup label={t("service.title")}>
                    {services.data?.data.map((s) => (
                      <NativeSelectOption key={`service:${s.id}`} value={`service:${s.id}`}>
                        {s.name}
                      </NativeSelectOption>
                    ))}
                  </NativeSelectOptGroup>
                  <NativeSelectOptGroup label={t("product.title")}>
                    {products.data?.data.map((p) => (
                      <NativeSelectOption key={`product:${p.id}`} value={`product:${p.id}`}>
                        {p.name}
                      </NativeSelectOption>
                    ))}
                  </NativeSelectOptGroup>
                </NativeSelect>
                {overStock ? (
                  <p className="mt-1 text-xs text-destructive">
                    {t("pos.insufficient_stock")}
                  </p>
                ) : null}
              </div>
              <div className="w-20">
                <Input
                  type="number"
                  min={1}
                  value={row.qty}
                  aria-label={t("pos.qty")}
                  onChange={(e) => setQty(row.key, Number(e.target.value))}
                />
              </div>
              <div className="w-28 text-right text-sm tabular-nums">
                {formatCurrency(row.unitPrice * row.qty)}
              </div>
              <Button
                type="button"
                size="icon"
                variant="ghost"
                aria-label={t("general.delete")}
                onClick={() => removeRow(row.key)}
              >
                <Trash2Icon />
              </Button>
            </div>
          )
        })}
        {rows.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t("general.no_data")}</p>
        ) : null}
      </div>

      <div className="flex items-center justify-between border-t pt-3">
        <Badge variant="secondary">{t("pos.total")}</Badge>
        <span className="text-lg font-semibold tabular-nums">{formatCurrency(total)}</span>
      </div>
    </div>
  )
}

import { useCallback, useMemo, useState } from "react"

import type { CatalogEntry } from "./use-pos-catalog.ts"

export interface LineItem {
  key: string
  kind: "service" | "product"
  refId: number
  name: string
  unitPrice: number
  qty: number
  /** null untuk layanan — layanan tidak terbatas stok. */
  stock: number | null
}

/** Satu entri katalog hanya boleh muncul sekali; menambah lagi menaikkan qty. */
function keyOf(entry: Pick<CatalogEntry, "kind" | "id">): string {
  return `${entry.kind}:${entry.id}`
}

export function usePosCart() {
  const [items, setItems] = useState<LineItem[]>([])

  const add = useCallback((entry: CatalogEntry) => {
    const key = keyOf(entry)

    setItems((prev) => {
      const existing = prev.find((item) => item.key === key)

      if (existing) {
        return prev.map((item) =>
          item.key === key ? { ...item, qty: item.qty + 1 } : item,
        )
      }

      return [
        ...prev,
        {
          key,
          kind: entry.kind,
          refId: entry.id,
          name: entry.name,
          unitPrice: entry.price,
          qty: 1,
          stock: entry.stock,
        },
      ]
    })
  }, [])

  /** Turun ke nol berarti barisnya dicabut — kasir tidak perlu dua langkah. */
  const setQty = useCallback((key: string, qty: number) => {
    setItems((prev) =>
      qty < 1
        ? prev.filter((item) => item.key !== key)
        : prev.map((item) => (item.key === key ? { ...item, qty } : item)),
    )
  }, [])

  const step = useCallback(
    (key: string, delta: number) => {
      setItems((prev) => {
        const target = prev.find((item) => item.key === key)
        if (!target) return prev

        const next = target.qty + delta

        return next < 1
          ? prev.filter((item) => item.key !== key)
          : prev.map((item) => (item.key === key ? { ...item, qty: next } : item))
      })
    },
    [],
  )

  const remove = useCallback((key: string) => {
    setItems((prev) => prev.filter((item) => item.key !== key))
  }, [])

  const clear = useCallback(() => setItems([]), [])

  const total = useMemo(
    () => items.reduce((sum, item) => sum + item.unitPrice * item.qty, 0),
    [items],
  )

  // Stok yang kurang tidak menghalangi penyusunan keranjang, hanya penyimpanan
  // — kasir tetap boleh melihat totalnya sambil mencari jalan keluar.
  const hasStockIssue = useMemo(
    () => items.some((item) => item.stock !== null && item.qty > item.stock),
    [items],
  )

  return {
    items,
    total,
    hasStockIssue,
    isEmpty: items.length === 0,
    add,
    setQty,
    step,
    remove,
    clear,
  }
}

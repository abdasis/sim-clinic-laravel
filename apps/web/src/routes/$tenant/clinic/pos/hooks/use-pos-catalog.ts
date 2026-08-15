import { useMemo, useState } from "react"
import { useQuery } from "@tanstack/react-query"

import { apiGet } from "#/lib/api.ts"

/** Bentuk mentah dari endpoint layanan/produk; keduanya cukup mirip. */
interface CatalogRow {
  id: number
  name: string
  price: string
  promo?: { id: number; name: string; price: string } | null
  status?: string
  unit?: string
  stock_balance?: number | string
  min_threshold?: number | string
}

export type StockStatus = "available" | "low" | "out" | "n/a"

export interface CatalogEntry {
  kind: "service" | "product"
  id: number
  name: string
  /** Harga yang ditagihkan — sudah dipotong promo bila ada. */
  price: number
  /** Harga sebelum potongan; null berarti tidak sedang promo. */
  basePrice: number | null
  promoName: string | null
  unit: string | null
  stock: number | null
  minThreshold: number | null
  stockStatus: StockStatus
}

export type TypeFilter = "all" | "service" | "product"
export type StockFilter = "all" | StockStatus
export type SortKey = "name" | "price_asc" | "price_desc"

export interface CatalogFilters {
  search: string
  type: TypeFilter
  stock: StockFilter
  sort: SortKey
}

const INITIAL: CatalogFilters = {
  search: "",
  type: "all",
  stock: "all",
  sort: "name",
}

function toNumber(value: unknown): number {
  const parsed = Number(value)

  return Number.isFinite(parsed) ? parsed : 0
}

/**
 * Status stok diturunkan dari saldo terhadap ambang minimum, bukan dari flag
 * bawaan — supaya ambang yang diubah admin langsung terasa di kasir.
 */
export function stockStatusOf(entry: {
  kind: CatalogEntry["kind"]
  stock: number | null
  minThreshold: number | null
}): StockStatus {
  if (entry.kind === "service" || entry.stock === null) return "n/a"
  if (entry.stock <= 0) return "out"
  if (entry.minThreshold !== null && entry.stock <= entry.minThreshold) return "low"

  return "available"
}

function toEntry(row: CatalogRow, kind: CatalogEntry["kind"]): CatalogEntry {
  const stock = kind === "product" ? toNumber(row.stock_balance) : null
  const minThreshold =
    kind === "product" && row.min_threshold !== undefined
      ? toNumber(row.min_threshold)
      : null

  // Harga promo dihitung server; kasir memakai angka itu apa adanya supaya
  // yang tampil, yang masuk keranjang, dan yang tersimpan tidak pernah beda.
  const basePrice = toNumber(row.price)
  const promoPrice = row.promo ? toNumber(row.promo.price) : null

  return {
    kind,
    id: row.id,
    name: row.name,
    price: promoPrice ?? basePrice,
    basePrice: promoPrice === null ? null : basePrice,
    promoName: row.promo?.name ?? null,
    unit: row.unit ?? null,
    stock,
    minThreshold,
    stockStatus: stockStatusOf({ kind, stock, minThreshold }),
  }
}

/** Penyaringan murni supaya mudah dibaca dan tidak bergantung pada React. */
export function applyFilters(
  entries: CatalogEntry[],
  filters: CatalogFilters,
): CatalogEntry[] {
  const needle = filters.search.trim().toLowerCase()

  const filtered = entries.filter((entry) => {
    if (needle && !entry.name.toLowerCase().includes(needle)) return false
    if (filters.type !== "all" && entry.kind !== filters.type) return false
    // Layanan tidak punya stok; menyaringnya dengan status stok justru
    // menghilangkannya tanpa alasan yang bisa dijelaskan ke kasir.
    if (filters.stock !== "all") {
      if (entry.kind === "service") return false
      if (entry.stockStatus !== filters.stock) return false
    }

    return true
  })

  return filtered.sort((a, b) => {
    if (filters.sort === "price_asc") return a.price - b.price
    if (filters.sort === "price_desc") return b.price - a.price

    return a.name.localeCompare(b.name, "id")
  })
}

/**
 * Katalog kasir: layanan dan produk aktif dalam satu daftar, plus penyaringnya.
 * Kunci query-nya sama dengan pemakai lain supaya cache-nya ikut terpakai.
 */
export function usePosCatalog(tenant: string) {
  const [filters, setFilters] = useState<CatalogFilters>(INITIAL)

  const services = useQuery({
    queryKey: ["services", tenant, "catalog"],
    queryFn: () =>
      apiGet<{ data: CatalogRow[] }>(`/${tenant}/clinic/services`, {
        per_page: 100,
      }),
  })

  const products = useQuery({
    queryKey: ["products", tenant, "catalog"],
    queryFn: () =>
      apiGet<{ data: CatalogRow[] }>(`/${tenant}/clinic/products`, {
        per_page: 100,
      }),
  })

  const entries = useMemo(() => {
    const active = (rows: CatalogRow[] | undefined) =>
      (rows ?? []).filter((row) => (row.status ?? "active") === "active")

    return [
      ...active(services.data?.data).map((row) => toEntry(row, "service")),
      ...active(products.data?.data).map((row) => toEntry(row, "product")),
    ]
  }, [services.data, products.data])

  const visible = useMemo(() => applyFilters(entries, filters), [entries, filters])

  return {
    entries,
    visible,
    filters,
    setFilters,
    resetFilters: () => setFilters(INITIAL),
    isLoading: services.isLoading || products.isLoading,
    isError: services.isError || products.isError,
  }
}

import { useMemo } from "react"

import {
  Table,
  TableBody,
  TableCell,
  TableFooter,
  TableHead,
  TableHeader,
  TableRow,
} from "#/components/ui/table.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { formatCurrency } from "#/lib/format.ts"
import { cn } from "#/lib/utils.ts"

export interface SalesRow {
  id: number
  name: string
  qty_sold: number
  /** Desimal berpresisi dari server, mis. "1500000.00". */
  revenue: string
}

interface SalesTableProps {
  rows: SalesRow[]
  /** Judul kolom pertama: nama layanan atau nama produk. */
  nameLabel: string
}

/**
 * Peringkat penjualan, dipakai bersama tab treatment dan produk.
 *
 * Sebelumnya dua komponen kembar yang hanya berbeda nama kolom, keduanya
 * menampilkan baris apa adanya: urutan mengikuti kiriman server, angka rata
 * kiri, dan nilai rupiah tercetak mentah sebagai "1500000.00".
 *
 * Yang dijawab tabel ini bukan "apa saja yang terjual" melainkan "mana yang
 * paling menghidupi klinik" — karena itu diurutkan dari omzet terbesar,
 * diberi nomor peringkat, dan porsinya terhadap total digambar sebagai
 * batang di belakang angkanya. Baris total menutup di bawah supaya tidak
 * perlu menjumlah sendiri.
 */
export function SalesTable({ rows, nameLabel }: SalesTableProps) {
  const { t } = useTrans()

  const { ranked, totalRevenue, totalQty, top } = useMemo(() => {
    const ranked = rows
      .map((row) => ({ ...row, amount: toAmount(row.revenue) }))
      .sort((a, b) => b.amount - a.amount)

    return {
      ranked,
      totalRevenue: ranked.reduce((sum, row) => sum + row.amount, 0),
      totalQty: ranked.reduce((sum, row) => sum + row.qty_sold, 0),
      // Batang dibandingkan terhadap baris teratas, bukan terhadap total:
      // dengan puluhan baris, porsi terhadap total selalu tipis dan tidak
      // ada bedanya antara yang kedua dan yang kesepuluh.
      top: ranked[0]?.amount ?? 0,
    }
  }, [rows])

  return (
    <div className="overflow-hidden rounded-lg border border-border/60">
      <Table>
        <TableHeader>
          <TableRow className="hover:bg-transparent">
            <TableHead className="w-10 text-center text-2xs font-semibold tracking-wider text-muted-foreground uppercase">
              #
            </TableHead>
            <TableHead className="text-2xs font-semibold tracking-wider text-muted-foreground uppercase">
              {nameLabel}
            </TableHead>
            <TableHead className="w-28 text-right text-2xs font-semibold tracking-wider text-muted-foreground uppercase">
              {t("report.qty_sold")}
            </TableHead>
            <TableHead className="w-56 text-right text-2xs font-semibold tracking-wider text-muted-foreground uppercase">
              {t("report.revenue")}
            </TableHead>
          </TableRow>
        </TableHeader>

        <TableBody>
          {ranked.map((row, index) => (
            <TableRow key={row.id} className="transition-colors">
              <TableCell className="text-center text-xs tabular-nums text-muted-foreground">
                {index + 1}
              </TableCell>
              <TableCell className="font-medium">{row.name}</TableCell>
              <TableCell className="text-right tabular-nums">
                {row.qty_sold}
              </TableCell>
              <TableCell className="text-right">
                <span className="flex items-center justify-end gap-2">
                  <span
                    aria-hidden
                    className="hidden h-1.5 flex-1 overflow-hidden rounded-full bg-muted sm:block"
                  >
                    <span
                      className={cn(
                        "block h-full rounded-full transition-[width] duration-300",
                        index === 0 ? "bg-primary" : "bg-primary/45",
                      )}
                      style={{ width: share(row.amount, top) }}
                    />
                  </span>
                  <span className="shrink-0 tabular-nums">
                    {formatCurrency(row.amount)}
                  </span>
                </span>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>

        <TableFooter>
          <TableRow className="hover:bg-transparent">
            <TableCell />
            <TableCell className="text-2xs font-semibold tracking-wider text-muted-foreground uppercase">
              {t("report.total")}
            </TableCell>
            <TableCell className="text-right font-medium tabular-nums">
              {totalQty}
            </TableCell>
            <TableCell className="text-right font-semibold tabular-nums">
              {formatCurrency(totalRevenue)}
            </TableCell>
          </TableRow>
        </TableFooter>
      </Table>
    </div>
  )
}

/** Nilai uang datang sebagai string; yang tidak terbaca dihitung nol. */
function toAmount(value: string): number {
  const amount = Number(value)

  return Number.isFinite(amount) ? amount : 0
}

function share(value: number, top: number): string {
  if (top <= 0) return "0%"

  // Batang paling tipis tetap terlihat: nol lebar terbaca sebagai "tidak ada
  // data", padahal barisnya memang ada dan nilainya kecil.
  return `${Math.max((value / top) * 100, 2)}%`
}

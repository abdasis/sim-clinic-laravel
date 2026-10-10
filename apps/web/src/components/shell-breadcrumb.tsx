import { Fragment } from "react"
import { Link } from "@tanstack/react-router"

import {
  Breadcrumb,
  BreadcrumbEllipsis,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "#/components/ui/breadcrumb.tsx"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "#/components/ui/dropdown-menu.tsx"
import { isNarrowTier, useLayoutTier } from "#/hooks/use-mobile.ts"
import { cn } from "#/lib/utils.ts"

export interface ShellCrumb {
  label: string
  /** Tanpa `to`, ruas ini teks biasa — dipakai untuk pengelompokan. */
  to?: string
  params?: Record<string, string>
}

/**
 * Berapa ruas yang ditampilkan utuh di layar sempit.
 *
 * Induk langsung dan halaman aktif: dua itu yang menjawab "saya di mana" dan
 * "ke mana kembali". Sisanya tetap ada, tapi di balik menu.
 */
const MOBILE_VISIBLE = 2

/**
 * Breadcrumb di bar header shell. Berbeda dari versi lama yang tinggal di
 * dalam konten: tingginya mengikuti bar `h-12`, jadi tanpa jarak bawah dan
 * berukuran kecil.
 *
 * Ruas terakhir selalu jadi halaman aktif — bahkan bila `to`-nya diisi —
 * karena menautkan halaman ke dirinya sendiri tidak membawa pembaca ke mana
 * pun.
 *
 * Di layar sempit, jenjang dalam seperti `Klinik > Kasir > Transaksi > Nota`
 * memakan seluruh lebar header: ruas depan tidak boleh menyusut (kalau tidak,
 * nama klinik jadi "m..") sehingga yang terdorong keluar justru halaman aktif
 * di ujung. Karena itu ruas tengah dilipat ke menu, bukan dibiarkan terpotong
 * mentah di tepi.
 */
export function ShellBreadcrumb({
  items,
  className,
}: {
  items: ShellCrumb[]
  className?: string
}) {
  const tier = useLayoutTier()

  if (items.length === 0) return null

  const collapsed =
    isNarrowTier(tier) && items.length > MOBILE_VISIBLE
      ? items.slice(0, items.length - MOBILE_VISIBLE)
      : []

  const visible = collapsed.length > 0 ? items.slice(-MOBILE_VISIBLE) : items

  return (
    <Breadcrumb className={cn("min-w-0", className)}>
      {/* `overflow-hidden`: kalau ruang benar-benar habis, breadcrumb-nya
          terpotong di ujung alih-alih mendorong isi header keluar. */}
      <BreadcrumbList className="flex-nowrap gap-1 overflow-hidden text-sm sm:gap-1.5">
        {collapsed.length > 0 ? (
          <>
            <BreadcrumbItem className="shrink-0">
              <DropdownMenu>
                <DropdownMenuTrigger
                  aria-label="Ruas sebelumnya"
                  className="flex size-6 items-center justify-center rounded-sm text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                  <BreadcrumbEllipsis className="size-4" />
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" className="min-w-40">
                  {collapsed.map((item, index) => (
                    <DropdownMenuItem key={`${item.label}-${index}`} asChild={!!item.to}>
                      {item.to ? (
                        <Link to={item.to} params={item.params}>
                          {item.label}
                        </Link>
                      ) : (
                        <span className="text-muted-foreground">{item.label}</span>
                      )}
                    </DropdownMenuItem>
                  ))}
                </DropdownMenuContent>
              </DropdownMenu>
            </BreadcrumbItem>
            <BreadcrumbSeparator className="shrink-0" />
          </>
        ) : null}

        {visible.map((item, index) => {
          const isLast = index === visible.length - 1

          return (
            <Fragment key={`${item.label}-${index}`}>
              {/* Ruas depan tidak menyusut sampai nol: kalau semuanya ikut
                  mengecil, nama klinik jadi "m.." dan modulnya "K.." — yang
                  tersisa terbaca justru bukan yang dicari pembaca. Yang
                  dipakai batas lebar, bukan larangan memotong, supaya satu
                  ruas bernama panjang tidak menghabiskan barisnya sendiri. */}
              <BreadcrumbItem className={isLast ? "min-w-0" : "min-w-0 shrink"}>
                {isLast ? (
                  <BreadcrumbPage className="truncate font-medium">
                    {item.label}
                  </BreadcrumbPage>
                ) : item.to ? (
                  <Link
                    to={item.to}
                    params={item.params}
                    className="block max-w-32 truncate text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:max-w-none sm:whitespace-nowrap"
                  >
                    {item.label}
                  </Link>
                ) : (
                  // Ruas tanpa tautan tetap diredupkan: yang menonjol hanya
                  // halaman aktif di ujung.
                  <span className="block max-w-32 truncate text-muted-foreground sm:max-w-none sm:whitespace-nowrap">
                    {item.label}
                  </span>
                )}
              </BreadcrumbItem>
              {!isLast ? <BreadcrumbSeparator className="shrink-0" /> : null}
            </Fragment>
          )
        })}
      </BreadcrumbList>
    </Breadcrumb>
  )
}

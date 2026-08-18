import { Fragment } from "react"
import { Link } from "@tanstack/react-router"

import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "#/components/ui/breadcrumb.tsx"
import { cn } from "#/lib/utils.ts"

export interface ShellCrumb {
  label: string
  /** Tanpa `to`, ruas ini teks biasa — dipakai untuk pengelompokan. */
  to?: string
  params?: Record<string, string>
}

/**
 * Breadcrumb di bar header shell. Berbeda dari versi lama yang tinggal di
 * dalam konten: tingginya mengikuti bar `h-12`, jadi tanpa jarak bawah dan
 * berukuran kecil.
 *
 * Ruas terakhir selalu jadi halaman aktif — bahkan bila `to`-nya diisi —
 * karena menautkan halaman ke dirinya sendiri tidak membawa pembaca ke mana
 * pun.
 */
export function ShellBreadcrumb({
  items,
  className,
}: {
  items: ShellCrumb[]
  className?: string
}) {
  if (items.length === 0) return null

  return (
    <Breadcrumb className={cn("min-w-0", className)}>
      {/* `overflow-hidden`: kalau ruang benar-benar habis, breadcrumb-nya
          terpotong di ujung alih-alih mendorong isi header keluar. */}
      <BreadcrumbList className="flex-nowrap gap-1 overflow-hidden text-sm sm:gap-1.5">
        {items.map((item, index) => {
          const isLast = index === items.length - 1

          return (
            <Fragment key={`${item.label}-${index}`}>
              {/* Hanya ruas terakhir yang boleh terpotong. Kalau semuanya
                  ikut menyusut, nama klinik jadi "m.." dan modulnya "K.." --
                  yang tersisa terbaca justru bukan yang dicari pembaca. */}
              <BreadcrumbItem className={isLast ? "min-w-0" : "shrink-0"}>
                {isLast ? (
                  <BreadcrumbPage className="truncate font-medium">
                    {item.label}
                  </BreadcrumbPage>
                ) : item.to ? (
                  <Link
                    to={item.to}
                    params={item.params}
                    className="whitespace-nowrap text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                  >
                    {item.label}
                  </Link>
                ) : (
                  // Ruas tanpa tautan tetap diredupkan: yang menonjol hanya
                  // halaman aktif di ujung.
                  <span className="whitespace-nowrap text-muted-foreground">
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

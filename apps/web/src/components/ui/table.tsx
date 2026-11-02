import * as React from "react"

import { cn } from "#/lib/utils.ts"

/**
 * Apakah masih ada kolom di luar pandangan, di kiri atau di kanan.
 *
 * Tabel yang tergulir mendatar tanpa penanda terbaca seperti tabel yang
 * memang hanya punya kolom sebanyak itu — kolom aksi di ujung kanan tidak
 * pernah terlihat, dan tidak ada apa pun yang memberi tahu bahwa ia ada.
 */
function useOverflowEdges<T extends HTMLElement>() {
  const ref = React.useRef<T>(null)
  const [edges, setEdges] = React.useState({ start: false, end: false })

  React.useEffect(() => {
    const element = ref.current

    if (!element) return

    const read = () => {
      // Toleransi 1px: pembulatan sub-piksel membuat tabel yang sudah
      // tergulir habis tetap melaporkan sisa beberapa persepuluh piksel,
      // dan bayangannya tidak pernah hilang.
      const sisaKanan =
        element.scrollWidth - element.clientWidth - element.scrollLeft

      setEdges({ start: element.scrollLeft > 1, end: sisaKanan > 1 })
    }

    element.addEventListener("scroll", read, { passive: true })
    read()

    if (typeof ResizeObserver === "undefined") {
      return () => element.removeEventListener("scroll", read)
    }

    const observer = new ResizeObserver(read)

    observer.observe(element)

    return () => {
      element.removeEventListener("scroll", read)
      observer.disconnect()
    }
  }, [])

  return [ref, edges] as const
}

function Table({ className, ...props }: React.ComponentProps<"table">) {
  const [ref, edges] = useOverflowEdges<HTMLDivElement>()

  return (
    // Bayangannya di luar elemen yang menggulir: anak dengan posisi absolut
    // di dalam penggulir ikut tergeser bersama isinya, jadi penandanya justru
    // hilang dari pandangan tepat saat dibutuhkan.
    <div className="relative w-full">
      <div
        ref={ref}
        data-slot="table-container"
        data-overflow-start={edges.start || undefined}
        data-overflow-end={edges.end || undefined}
        className="w-full overflow-x-auto"
      >
        <table
          data-slot="table"
          className={cn("w-full caption-bottom text-sm", className)}
          {...props}
        />
      </div>
      <TableScrollHint side="start" active={edges.start} />
      <TableScrollHint side="end" active={edges.end} />
    </div>
  )
}

/**
 * Bayangan tipis di tepi tabel yang masih menyimpan kolom di luar pandangan.
 *
 * Sengaja bayangan, bukan tombol panah: ia tidak merebut ruang, tidak perlu
 * target sentuh, dan hilang sendiri begitu tergulir habis.
 */
function TableScrollHint({
  side,
  active,
}: {
  side: "start" | "end"
  active: boolean
}) {
  return (
    <span
      aria-hidden="true"
      data-slot="table-scroll-hint"
      data-side={side}
      className={cn(
        "pointer-events-none absolute inset-y-0 w-6 transition-opacity duration-200",
        side === "start"
          ? "start-0 bg-gradient-to-r"
          : "end-0 bg-gradient-to-l",
        "from-background to-transparent",
        active ? "opacity-100" : "opacity-0",
      )}
    />
  )
}

function TableHeader({ className, ...props }: React.ComponentProps<"thead">) {
  return (
    <thead
      data-slot="table-header"
      className={cn("[&_tr]:border-b", className)}
      {...props}
    />
  )
}

function TableBody({ className, ...props }: React.ComponentProps<"tbody">) {
  return (
    <tbody
      data-slot="table-body"
      className={cn("[&_tr:last-child]:border-0", className)}
      {...props}
    />
  )
}

function TableFooter({ className, ...props }: React.ComponentProps<"tfoot">) {
  return (
    <tfoot
      data-slot="table-footer"
      className={cn(
        "border-t bg-muted/50 font-medium [&>tr]:last:border-b-0",
        className
      )}
      {...props}
    />
  )
}

function TableRow({ className, ...props }: React.ComponentProps<"tr">) {
  return (
    <tr
      data-slot="table-row"
      className={cn(
        "border-b transition-colors hover:bg-muted/50 has-aria-expanded:bg-muted/50 data-[state=selected]:bg-muted",
        className
      )}
      {...props}
    />
  )
}

function TableHead({ className, ...props }: React.ComponentProps<"th">) {
  return (
    <th
      data-slot="table-head"
      className={cn(
        "h-10 px-2 text-left align-middle font-medium whitespace-nowrap text-foreground [&:has([role=checkbox])]:pr-0",
        className
      )}
      {...props}
    />
  )
}

function TableCell({ className, ...props }: React.ComponentProps<"td">) {
  return (
    <td
      data-slot="table-cell"
      className={cn(
        "p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0",
        className
      )}
      {...props}
    />
  )
}

function TableCaption({
  className,
  ...props
}: React.ComponentProps<"caption">) {
  return (
    <caption
      data-slot="table-caption"
      className={cn("mt-4 text-sm text-muted-foreground", className)}
      {...props}
    />
  )
}

export {
  Table,
  TableHeader,
  TableBody,
  TableFooter,
  TableHead,
  TableRow,
  TableCell,
  TableCaption,
}

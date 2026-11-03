"use client"

import * as React from "react"
import { Dialog as DialogPrimitive } from "radix-ui"

import { cn } from "#/lib/utils.ts"
import { Button } from "#/components/ui/button.tsx"
import { HugeiconsIcon } from "@hugeicons/react"
import { Cancel01Icon } from "@hugeicons/core-free-icons"

function Dialog({
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Root>) {
  return <DialogPrimitive.Root data-slot="dialog" {...props} />
}

function DialogTrigger({
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Trigger>) {
  return <DialogPrimitive.Trigger data-slot="dialog-trigger" {...props} />
}

function DialogPortal({
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Portal>) {
  return <DialogPrimitive.Portal data-slot="dialog-portal" {...props} />
}

function DialogClose({
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Close>) {
  return <DialogPrimitive.Close data-slot="dialog-close" {...props} />
}

function DialogOverlay({
  className,
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Overlay>) {
  return (
    <DialogPrimitive.Overlay
      data-slot="dialog-overlay"
      // Tirainya ditulis dengan rgb() biasa, bukan `bg-black/10`: Tailwind v4
      // mengompilasi tiap kelas beropasitas jadi oklab()/color-mix(), dan
      // browser yang belum mengenalnya membuang deklarasi itu — tirainya
      // hilang seluruhnya dan dialog terbaca sebagai teks yang menumpuk di
      // atas halaman. Pekatnya juga dinaikkan: 10% tidak terlihat sama sekali
      // di layar tablet yang mengkilap.
      className={cn(
        "fixed inset-0 isolate z-50 bg-[rgb(0_0_0/0.32)] duration-100 supports-backdrop-filter:backdrop-blur-xs data-open:animate-in data-open:fade-in-0 data-closed:animate-out data-closed:fade-out-0",
        className
      )}
      {...props}
    />
  )
}

/**
 * Bentuk dialog. `form` untuk dialog berisi formulir; sisanya tetap seperti
 * semula supaya dialog konfirmasi tidak ikut berubah.
 *
 * Yang dijawab `form` tiga hal yang semuanya menggigit di ponsel:
 *
 * - **Tinggi.** Bentuk bawaan `h-fit` tanpa batas atas, jadi formulir panjang
 *   tumbuh melewati layar dan tombol simpannya terdorong keluar — tidak
 *   tergulir, hilang. `form` memberi batas setinggi layar dan memindahkan
 *   gulirnya ke isi formulirnya (`DialogBody`), sehingga kepala dan kaki
 *   dialog tetap di tempatnya.
 * - **Papan ketik.** Batasnya memakai `dvh`, bukan `vh`: papan ketik ponsel
 *   mengecilkan viewport dinamis tapi tidak mengubah `vh`, jadi dengan `vh`
 *   field yang sedang diisi justru tertutup papan ketiknya sendiri.
 * - **Ruang.** Di bawah 768px dialognya memenuhi layar — formulir tujuh field
 *   di dalam kotak mengambang selebar 390px cuma menyisakan dua baris yang
 *   terlihat sekaligus.
 */
type DialogLayout = "default" | "form"

function DialogContent({
  className,
  children,
  showCloseButton = true,
  layout = "default",
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Content> & {
  showCloseButton?: boolean
  layout?: DialogLayout
}) {
  return (
    <DialogPortal>
      <DialogOverlay />
      <DialogPrimitive.Content
        data-slot="dialog-content"
        // Dipusatkan dengan inset + margin auto, bukan translate.
        // `-translate-x-1/2` dikompilasi Tailwind v4 jadi properti `translate`
        // yang berdiri sendiri — baru ada sejak Chrome 104. Di browser yang
        // lebih tua deklarasinya dibuang, dan sudut kiri-atas dialog
        // tertinggal tepat di titik tengah layar lalu menjulur ke kanan bawah.
        // Pemusatan lewat kotak tidak butuh transform sama sekali.
        data-layout={layout}
        className={cn(
          "fixed inset-0 z-50 m-auto w-full max-w-[calc(100%-2rem)] gap-4 rounded-xl bg-popover p-4 text-sm text-popover-foreground ring-1 ring-foreground/10 duration-100 outline-none sm:max-w-sm data-open:animate-in data-open:fade-in-0 data-open:zoom-in-95 data-closed:animate-out data-closed:fade-out-0 data-closed:zoom-out-95",
          // Kolom lentur, bukan grid berbaris tetap: jumlah anak dialog
          // berbeda-beda — sebagian membungkus isi dan kakinya dalam satu
          // <form>, sebagian tidak — jadi baris grid yang dipatok akan
          // menempatkan kakinya di baris yang salah pada separuh dialog.
          layout === "form"
            ? "flex max-h-[calc(100dvh-2rem)] flex-col overflow-hidden max-md:h-dvh max-md:max-h-dvh max-md:max-w-none max-md:rounded-none"
            : "grid h-fit",
          className
        )}
        {...props}
      >
        {children}
        {showCloseButton && (
          <DialogPrimitive.Close data-slot="dialog-close" asChild>
            <Button
              variant="ghost"
              className="absolute top-2 right-2"
              size="icon-sm"
            >
              <HugeiconsIcon icon={Cancel01Icon} strokeWidth={2} />
              <span className="sr-only">Close</span>
            </Button>
          </DialogPrimitive.Close>
        )}
      </DialogPrimitive.Content>
    </DialogPortal>
  )
}

/**
 * Isi formulir di dalam dialog — satu-satunya bagian yang menggulir.
 *
 * `min-h-0` wajib: baris grid `1fr` tetap melebar mengikuti isinya kalau
 * tinggi minimumnya tidak dinolkan, dan `overflow-y-auto` pada elemen yang
 * tidak pernah lebih pendek dari isinya tidak menggulir apa pun.
 *
 * Padding mendatarnya dibatalkan lalu dipasang ulang di dalam supaya bayangan
 * fokus field tidak terpotong tepi area gulirnya.
 */
function DialogBody({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="dialog-body"
      className={cn("-mx-4 min-h-0 flex-1 overflow-y-auto px-4", className)}
      {...props}
    />
  )
}

function DialogHeader({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="dialog-header"
      className={cn("flex flex-col gap-2", className)}
      {...props}
    />
  )
}

function DialogFooter({
  className,
  showCloseButton = false,
  children,
  ...props
}: React.ComponentProps<"div"> & {
  showCloseButton?: boolean
}) {
  return (
    <div
      data-slot="dialog-footer"
      className={cn(
        "-mx-4 -mb-4 flex flex-col-reverse gap-2 rounded-b-xl border-t bg-muted/50 p-4 sm:flex-row sm:justify-end",
        className
      )}
      {...props}
    >
      {children}
      {showCloseButton && (
        <DialogPrimitive.Close asChild>
          <Button variant="outline">Close</Button>
        </DialogPrimitive.Close>
      )}
    </div>
  )
}

function DialogTitle({
  className,
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Title>) {
  return (
    <DialogPrimitive.Title
      data-slot="dialog-title"
      className={cn(
        "font-heading text-base leading-none font-medium",
        className
      )}
      {...props}
    />
  )
}

function DialogDescription({
  className,
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Description>) {
  return (
    <DialogPrimitive.Description
      data-slot="dialog-description"
      className={cn(
        "text-sm text-muted-foreground *:[a]:underline *:[a]:underline-offset-3 *:[a]:hover:text-foreground",
        className
      )}
      {...props}
    />
  )
}

export {
  Dialog,
  DialogBody,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogOverlay,
  DialogPortal,
  DialogTitle,
  DialogTrigger,
}

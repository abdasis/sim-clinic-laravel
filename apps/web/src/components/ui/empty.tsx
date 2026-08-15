import { cva, type VariantProps } from "class-variance-authority"
import { motion, useReducedMotion } from "motion/react"

import { cn } from "#/lib/utils.ts"

/**
 * Permukaan tunggal untuk semua keadaan kosong.
 *
 * Root-nya menyalakan varian `hover`, dan motion menurunkannya ke seluruh
 * `motion.*` di dalamnya — jadi ilustrasi ikut hidup saat kartunya disentuh,
 * di mana pun letak kursornya. Saat pengguna meminta gerak seminimal mungkin,
 * varian itu tidak pernah dipasang sehingga gambarnya diam.
 */
function Empty({
  className,
  ...props
}: React.ComponentProps<typeof motion.div>) {
  const reduced = useReducedMotion()

  return (
    <motion.div
      data-slot="empty"
      initial="rest"
      animate="rest"
      whileHover={reduced ? undefined : "hover"}
      className={cn(
        "group flex w-full min-w-0 flex-1 flex-col items-center justify-center gap-4 rounded-xl border-dashed p-6 text-center text-balance",
        className
      )}
      {...props}
    />
  )
}

function EmptyHeader({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="empty-header"
      className={cn("flex max-w-sm flex-col items-center gap-2", className)}
      {...props}
    />
  )
}

const emptyMediaVariants = cva(
  "mb-2 flex shrink-0 items-center justify-center [&_svg]:pointer-events-none [&_svg]:shrink-0",
  {
    variants: {
      variant: {
        default: "bg-transparent",
        icon: "flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted text-foreground [&_svg:not([class*='size-'])]:size-4",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

function EmptyMedia({
  className,
  variant = "default",
  ...props
}: React.ComponentProps<"div"> & VariantProps<typeof emptyMediaVariants>) {
  return (
    <div
      data-slot="empty-icon"
      data-variant={variant}
      className={cn(emptyMediaVariants({ variant, className }))}
      {...props}
    />
  )
}

function EmptyTitle({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="empty-title"
      className={cn(
        "font-heading text-sm font-medium tracking-tight",
        className
      )}
      {...props}
    />
  )
}

function EmptyDescription({ className, ...props }: React.ComponentProps<"p">) {
  return (
    <div
      data-slot="empty-description"
      className={cn(
        "text-sm/relaxed text-muted-foreground [&>a]:underline [&>a]:underline-offset-4 [&>a:hover]:text-primary",
        className
      )}
      {...props}
    />
  )
}

function EmptyContent({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="empty-content"
      className={cn(
        "flex w-full max-w-sm min-w-0 flex-col items-center gap-2.5 text-sm text-balance",
        className
      )}
      {...props}
    />
  )
}

/** Baris aksi opsional di bawah deskripsi, mis. tombol "Tambah pasien". */
function EmptyAction({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="empty-action"
      className={cn("flex items-center justify-center gap-2 pt-1", className)}
      {...props}
    />
  )
}

export {
  Empty,
  EmptyAction,
  EmptyHeader,
  EmptyTitle,
  EmptyDescription,
  EmptyContent,
  EmptyMedia,
}

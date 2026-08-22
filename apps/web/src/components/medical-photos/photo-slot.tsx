import { ImageOff, ImagePlus, Maximize2, Trash2 } from "lucide-react"

import { Button } from "#/components/ui/button.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { Spinner } from "#/components/ui/spinner.tsx"
import { cn } from "#/lib/utils.ts"
import { photoSrc, PHOTO_ACCEPT, type PhotoRow } from "./photo-types.ts"

const SLOT_BOX =
  "flex aspect-4/3 w-full flex-col items-center justify-center gap-1.5 rounded-md border border-dashed border-border/70 bg-muted/20"

interface ColumnHeadingProps {
  label: string
  count: number
  addLabel: string
  onAdd?: () => void
  busy: boolean
}

export function ColumnHeading({
  label,
  count,
  addLabel,
  onAdd,
  busy,
}: ColumnHeadingProps) {
  return (
    <div className="flex min-w-0 items-center justify-between gap-2 border-b border-border/50 pb-1.5">
      <span className="truncate text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
        {label}
        <span className="ml-1.5 text-muted-foreground/60 tabular-nums">
          {count}
        </span>
      </span>
      {onAdd ? (
        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              type="button"
              size="icon-xs"
              variant="ghost"
              disabled={busy}
              aria-label={addLabel}
              onClick={onAdd}
            >
              {busy ? <Spinner /> : <ImagePlus />}
            </Button>
          </TooltipTrigger>
          <TooltipContent>{addLabel}</TooltipContent>
        </Tooltip>
      ) : null}
    </div>
  )
}

interface PhotoSlotProps {
  photo?: PhotoRow
  fallbackLabel: string
  addLabel: string
  emptyLabel: string
  openLabel: string
  deleteLabel: string
  onAdd?: () => void
  onOpen: () => void
  onDelete?: () => void
  busy: boolean
  removing: boolean
}

export function PhotoSlot({
  photo,
  fallbackLabel,
  addLabel,
  emptyLabel,
  openLabel,
  deleteLabel,
  onAdd,
  onOpen,
  onDelete,
  busy,
  removing,
}: PhotoSlotProps) {
  if (!photo) {
    // Kotak kosongnya sendiri yang jadi tombol tambah: sasarannya selebar
    // foto, dan letaknya persis di tempat foto yang memang belum ada.
    if (onAdd) {
      return (
        <button
          type="button"
          disabled={busy}
          onClick={onAdd}
          className={cn(
            SLOT_BOX,
            "text-muted-foreground/70 transition-colors outline-none hover:border-border hover:bg-muted/40 hover:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50 disabled:opacity-50",
          )}
        >
          <ImagePlus className="size-4" />
          <span className="px-2 text-center text-xs">{addLabel}</span>
        </button>
      )
    }

    return (
      <div className={cn(SLOT_BOX, "text-muted-foreground/60")}>
        <ImageOff className="size-4" />
        <span className="text-xs">{emptyLabel}</span>
      </div>
    )
  }

  return (
    <figure className="group relative">
      <button
        type="button"
        onClick={onOpen}
        aria-label={openLabel}
        className="relative block w-full overflow-hidden rounded-md border border-border/60 bg-muted/30 transition-colors outline-none hover:border-border focus-visible:ring-2 focus-visible:ring-ring/50"
      >
        <img
          src={photoSrc(photo)}
          alt={photo.type_label ?? fallbackLabel}
          loading="lazy"
          className={cn(
            "aspect-4/3 w-full object-cover transition-[transform,opacity] duration-200 group-hover:scale-[1.015]",
            removing && "opacity-40",
          )}
        />
        <span className="pointer-events-none absolute inset-0 flex items-center justify-center bg-foreground/25 opacity-0 transition-opacity duration-150 group-focus-within:opacity-100 group-hover:opacity-100">
          <Maximize2 className="size-4 text-background" />
        </span>
      </button>

      {onDelete ? (
        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              type="button"
              size="icon-xs"
              variant="destructive"
              disabled={removing}
              aria-label={deleteLabel}
              className="absolute top-1.5 right-1.5 opacity-0 shadow-sm transition-opacity group-focus-within:opacity-100 group-hover:opacity-100 focus-visible:opacity-100"
              onClick={onDelete}
            >
              {removing ? <Spinner /> : <Trash2 />}
            </Button>
          </TooltipTrigger>
          <TooltipContent>{deleteLabel}</TooltipContent>
        </Tooltip>
      ) : null}
    </figure>
  )
}

/**
 * Satu input berkas dipakai bergantian oleh kedua kolom; jenis fotonya
 * ditentukan tombol yang membukanya, bukan pilihan terpisah setelahnya.
 */
export function PhotoFileInput({
  inputRef,
  onFiles,
}: {
  inputRef: React.RefObject<HTMLInputElement | null>
  onFiles: (files: FileList) => void
}) {
  return (
    <input
      ref={inputRef}
      type="file"
      accept={PHOTO_ACCEPT}
      multiple
      className="hidden"
      onChange={(event) => {
        if (event.target.files?.length) onFiles(event.target.files)
        event.target.value = ""
      }}
    />
  )
}

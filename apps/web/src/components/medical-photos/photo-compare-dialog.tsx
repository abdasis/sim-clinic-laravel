import { useEffect } from "react"
import { ChevronLeft, ChevronRight } from "lucide-react"

import { Button } from "#/components/ui/button.tsx"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "#/components/ui/dialog.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { photoSrc, type PhotoPair } from "./photo-types.ts"

interface PhotoCompareDialogProps {
  pairs: PhotoPair[]
  /** Indeks pasangan yang dibuka; null berarti dialognya tertutup. */
  index: number | null
  onIndexChange: (index: number | null) => void
}

function ComparePane({
  label,
  src,
  empty,
}: {
  label: string
  src?: string
  empty: string
}) {
  return (
    <figure className="flex min-w-0 flex-col gap-2">
      <figcaption className="text-2xs font-medium tracking-wide text-muted-foreground uppercase">
        {label}
      </figcaption>
      {src ? (
        <img
          src={src}
          alt={label}
          className="max-h-[60vh] w-full rounded-md border border-border/60 bg-muted/30 object-contain"
        />
      ) : (
        <div className="flex h-40 items-center justify-center rounded-md border border-dashed border-border/70 bg-muted/20 text-sm text-muted-foreground/70">
          {empty}
        </div>
      )}
    </figure>
  )
}

/**
 * Perbandingan satu pasangan foto dalam ukuran besar.
 *
 * Keduanya ditaruh berdampingan dengan tinggi yang sama dan `object-contain`,
 * bukan dipotong: pada foto klinis, bagian yang terpotong justru sering
 * bagian yang mau dilihat. Panah kiri/kanan memindah pasangan supaya
 * riwayat panjang bisa ditelusuri tanpa menutup dialognya dulu.
 */
export function PhotoCompareDialog({
  pairs,
  index,
  onIndexChange,
}: PhotoCompareDialogProps) {
  const { t } = useTrans()
  const open = index !== null && pairs.length > 0
  const current = index === null ? undefined : pairs[index]

  useEffect(() => {
    if (!open || index === null) return

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "ArrowLeft" && index > 0) {
        event.preventDefault()
        onIndexChange(index - 1)
      }
      if (event.key === "ArrowRight" && index < pairs.length - 1) {
        event.preventDefault()
        onIndexChange(index + 1)
      }
    }

    window.addEventListener("keydown", onKeyDown)

    return () => window.removeEventListener("keydown", onKeyDown)
  }, [open, index, pairs.length, onIndexChange])

  if (!current || index === null) return null

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) onIndexChange(null)
      }}
    >
      <DialogContent className="sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle className="text-base">
            {t("medical_record.photos_before_after")}
          </DialogTitle>
          <DialogDescription className="tabular-nums">
            {t("medical_record.photo_pair")
              .replace(":current", String(index + 1))
              .replace(":total", String(pairs.length))}
          </DialogDescription>
        </DialogHeader>

        <div className="grid gap-4 sm:grid-cols-2">
          <ComparePane
            label={t("clinic.medical_photo_type.before")}
            src={current.before ? photoSrc(current.before) : undefined}
            empty={t("medical_record.photo_slot_empty")}
          />
          <ComparePane
            label={t("clinic.medical_photo_type.after")}
            src={current.after ? photoSrc(current.after) : undefined}
            empty={t("medical_record.photo_slot_empty")}
          />
        </div>

        {pairs.length > 1 ? (
          <TooltipProvider>
            <div className="flex items-center justify-center gap-2">
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="outline"
                    size="icon-sm"
                    disabled={index === 0}
                    aria-label={t("medical_record.photo_prev")}
                    onClick={() => onIndexChange(index - 1)}
                  >
                    <ChevronLeft />
                  </Button>
                </TooltipTrigger>
                <TooltipContent className="flex items-center gap-2">
                  {t("medical_record.photo_prev")}
                  <Kbd>←</Kbd>
                </TooltipContent>
              </Tooltip>
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="outline"
                    size="icon-sm"
                    disabled={index === pairs.length - 1}
                    aria-label={t("medical_record.photo_next")}
                    onClick={() => onIndexChange(index + 1)}
                  >
                    <ChevronRight />
                  </Button>
                </TooltipTrigger>
                <TooltipContent className="flex items-center gap-2">
                  {t("medical_record.photo_next")}
                  <Kbd>→</Kbd>
                </TooltipContent>
              </Tooltip>
            </div>
          </TooltipProvider>
        ) : null}
      </DialogContent>
    </Dialog>
  )
}

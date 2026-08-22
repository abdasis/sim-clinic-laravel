import { useEffect, useRef, useState } from "react"
import { toast } from "sonner"
import { ImagePlus, XIcon } from "lucide-react"

import { Button } from "#/components/ui/button.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { PhotoFileInput } from "./photo-slot.tsx"
import { photoRejection, type MedicalPhotoType } from "./photo-types.ts"

export interface SelectedPhoto {
  id: string
  file: File
  type: MedicalPhotoType
  url: string
}

interface UploaderColumnProps {
  label: string
  addLabel: string
  removeLabel: string
  photos: SelectedPhoto[]
  onAdd: () => void
  onRemove: (id: string) => void
}

function UploaderColumn({
  label,
  addLabel,
  removeLabel,
  photos,
  onAdd,
  onRemove,
}: UploaderColumnProps) {
  return (
    <div className="min-w-0 space-y-2">
      <div className="flex items-center justify-between gap-2 border-b border-border/50 pb-1.5">
        <span className="truncate text-2xs font-medium tracking-wide text-muted-foreground uppercase">
          {label}
          <span className="ml-1.5 text-muted-foreground/60 tabular-nums">
            {photos.length}
          </span>
        </span>
      </div>

      <div className="space-y-2">
        {photos.map((photo) => (
          <figure key={photo.id} className="group relative">
            <img
              src={photo.url}
              alt={photo.file.name}
              className="aspect-4/3 w-full rounded-md border border-border/60 object-cover"
            />
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  type="button"
                  size="icon-xs"
                  variant="destructive"
                  aria-label={removeLabel}
                  className="absolute top-1.5 right-1.5 opacity-0 shadow-sm transition-opacity group-focus-within:opacity-100 group-hover:opacity-100 focus-visible:opacity-100"
                  onClick={() => onRemove(photo.id)}
                >
                  <XIcon />
                </Button>
              </TooltipTrigger>
              <TooltipContent>{removeLabel}</TooltipContent>
            </Tooltip>
            <figcaption className="mt-1 truncate text-xs text-muted-foreground/70">
              {photo.file.name}
            </figcaption>
          </figure>
        ))}

        <button
          type="button"
          onClick={onAdd}
          className="flex aspect-4/3 w-full flex-col items-center justify-center gap-1.5 rounded-md border border-dashed border-border/70 bg-muted/20 text-muted-foreground/70 transition-colors outline-none hover:border-border hover:bg-muted/40 hover:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50"
        >
          <ImagePlus className="size-4" />
          <span className="px-2 text-center text-xs">{addLabel}</span>
        </button>
      </div>
    </div>
  )
}

/**
 * Pemilih foto sebelum/sesudah untuk rekam medis yang belum tersimpan.
 *
 * Berkasnya ditahan di sisi web sampai rekam medisnya punya id — foto tidak
 * bisa dititipkan ke catatan yang belum ada. Jenis fotonya ditentukan kolom
 * tempat berkasnya dimasukkan, bukan pilihan terpisah sesudahnya: bentuk
 * lama memberi semua berkas jenis "sebelum" lebih dulu, dan satu kolom yang
 * lupa diubah menghasilkan riwayat yang salah baca tanpa gejala apa pun.
 */
export function PhotoUploader({
  onChange,
}: {
  onChange?: (photos: SelectedPhoto[]) => void
}) {
  const { t } = useTrans()
  const [photos, setPhotos] = useState<SelectedPhoto[]>([])
  const inputRef = useRef<HTMLInputElement>(null)
  const pendingType = useRef<MedicalPhotoType>("before")
  const seq = useRef(0)

  useEffect(() => {
    onChange?.(photos)
  }, [photos, onChange])

  // Object URL yang tidak dibebaskan menahan berkasnya di memori selama tab
  // masih hidup; halaman ini kerap ditinggalkan tanpa disimpan.
  const live = useRef<SelectedPhoto[]>([])
  live.current = photos
  useEffect(
    () => () => {
      live.current.forEach((photo) => URL.revokeObjectURL(photo.url))
    },
    [],
  )

  function addFiles(files: FileList, type: MedicalPhotoType) {
    const next: SelectedPhoto[] = []

    for (const file of Array.from(files)) {
      const rejection = photoRejection(file)

      if (rejection === "type") {
        toast.error(t("medical_record.photo_invalid_type"))
        continue
      }
      if (rejection === "size") {
        toast.error(
          t("medical_record.photo_too_large").replace(":name", file.name),
        )
        continue
      }

      seq.current += 1
      next.push({
        id: `photo-${seq.current}`,
        file,
        type,
        url: URL.createObjectURL(file),
      })
    }

    if (next.length) setPhotos((prev) => [...prev, ...next])
  }

  function remove(id: string) {
    setPhotos((prev) => {
      const found = prev.find((photo) => photo.id === id)
      if (found) URL.revokeObjectURL(found.url)

      return prev.filter((photo) => photo.id !== id)
    })
  }

  const labels: Record<MedicalPhotoType, string> = {
    before: t("clinic.medical_photo_type.before"),
    after: t("clinic.medical_photo_type.after"),
  }

  const addLabels: Record<MedicalPhotoType, string> = {
    before: t("medical_record.photo_add_before"),
    after: t("medical_record.photo_add_after"),
  }

  return (
    <TooltipProvider>
      <div className="space-y-3">
        <div className="grid gap-3 sm:grid-cols-2 sm:gap-4">
          {(["before", "after"] as const).map((type) => (
            <UploaderColumn
              key={type}
              label={labels[type]}
              addLabel={addLabels[type]}
              removeLabel={t("general.remove")}
              photos={photos.filter((photo) => photo.type === type)}
              onAdd={() => {
                pendingType.current = type
                inputRef.current?.click()
              }}
              onRemove={remove}
            />
          ))}
        </div>

        <p className="text-xs text-muted-foreground/70">
          {t("medical_record.photo_rules")} {t("medical_record.photos_hint")}
        </p>
      </div>

      <PhotoFileInput
        inputRef={inputRef}
        onFiles={(files) => addFiles(files, pendingType.current)}
      />
    </TooltipProvider>
  )
}

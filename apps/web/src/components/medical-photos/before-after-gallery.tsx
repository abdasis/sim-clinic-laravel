import { Fragment, useMemo, useRef, useState } from "react"
import { ImagePlus } from "lucide-react"

import { Button } from "#/components/ui/button.tsx"
import { TooltipProvider } from "#/components/ui/tooltip.tsx"
import { EmptyState } from "#/components/ui/empty-state.tsx"
import { Spinner } from "#/components/ui/spinner.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { PhotoCompareDialog } from "./photo-compare-dialog.tsx"
import {
  ColumnHeading,
  PhotoFileInput,
  PhotoSlot,
} from "./photo-slot.tsx"
import {
  countByType,
  pairPhotos,
  type MedicalPhotoType,
  type PhotoRow,
} from "./photo-types.ts"

interface BeforeAfterGalleryProps {
  photos?: PhotoRow[]
  /** Bila diisi, tiap kolom menyediakan jalur unggah untuk jenisnya sendiri. */
  onPick?: (files: FileList, type: MedicalPhotoType) => void
  onDelete?: (photo: PhotoRow) => void
  uploading?: boolean
  /** Foto yang sedang dihapus; tombolnya dikunci selama proses berjalan. */
  deletingId?: number | null
}

/**
 * Foto klinis dalam dua kolom sejajar: sebelum di kiri, sesudah di kanan.
 *
 * Bentuk berkolom dipilih karena begitulah fotonya dibaca — yang dinilai
 * dokter adalah selisihnya, bukan tiap foto sendiri-sendiri. Sebelumnya
 * keduanya ditumpuk dalam satu kisi bercampur berlabel kecil, dan
 * membandingkan satu tindakan berarti mencari pasangannya dengan mata dulu.
 */
export function BeforeAfterGallery({
  photos = [],
  onPick,
  onDelete,
  uploading = false,
  deletingId = null,
}: BeforeAfterGalleryProps) {
  const { t } = useTrans()
  const [openPair, setOpenPair] = useState<number | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)
  const pendingType = useRef<MedicalPhotoType>("before")

  const pairs = useMemo(() => pairPhotos(photos), [photos])
  const counts = useMemo(() => countByType(photos), [photos])

  function pick(type: MedicalPhotoType) {
    pendingType.current = type
    inputRef.current?.click()
  }

  const labels: Record<MedicalPhotoType, string> = {
    before: t("clinic.medical_photo_type.before"),
    after: t("clinic.medical_photo_type.after"),
  }

  const addLabels: Record<MedicalPhotoType, string> = {
    before: t("medical_record.photo_add_before"),
    after: t("medical_record.photo_add_after"),
  }

  // Hanya ada saat memang bisa mengunggah: pembaca yang tidak menyunting
  // tidak perlu membawa kontrol berkas tersembunyi di halamannya.
  const fileInput = onPick ? (
    <PhotoFileInput
      inputRef={inputRef}
      onFiles={(files) => onPick(files, pendingType.current)}
    />
  ) : null

  if (pairs.length === 0) {
    return (
      <>
        <EmptyState
          illustration="medical-records"
          title={t("medical_record.no_photos")}
          description={t("medical_record.photos_empty_desc")}
          action={
            onPick ? (
              <Button
                type="button"
                size="sm"
                variant="outline"
                disabled={uploading}
                onClick={() => pick("before")}
              >
                {uploading ? <Spinner /> : <ImagePlus />}
                {addLabels.before}
              </Button>
            ) : undefined
          }
        />
        {fileInput}
      </>
    )
  }

  return (
    <TooltipProvider>
      <div className="space-y-3">
        <div className="grid grid-cols-[1.25rem_minmax(0,1fr)_minmax(0,1fr)] gap-x-3 gap-y-2.5 sm:gap-x-4">
          <div aria-hidden />
          {(["before", "after"] as const).map((type) => (
            <ColumnHeading
              key={type}
              label={labels[type]}
              count={counts[type]}
              addLabel={addLabels[type]}
              busy={uploading}
              onAdd={onPick ? () => pick(type) : undefined}
            />
          ))}

          {pairs.map((pair, index) => (
            <Fragment key={index}>
              {/* Nomor pasangan jadi jangkar baris: lewat dua pasangan, mata
                  butuh pegangan untuk yakin kiri dan kanan ini memang satu
                  tindakan yang sama. */}
              <div className="pt-1 text-right text-xs text-muted-foreground/50 tabular-nums">
                {index + 1}
              </div>
              {(["before", "after"] as const).map((type) => (
                <PhotoSlot
                  key={type}
                  photo={pair[type]}
                  fallbackLabel={labels[type]}
                  addLabel={addLabels[type]}
                  emptyLabel={t("medical_record.photo_slot_empty")}
                  openLabel={t("medical_record.photo_open")}
                  deleteLabel={t("medical_record.photo_delete")}
                  busy={uploading}
                  removing={deletingId === pair[type]?.id}
                  onAdd={onPick ? () => pick(type) : undefined}
                  onOpen={() => setOpenPair(index)}
                  onDelete={
                    onDelete && pair[type]
                      ? () => onDelete(pair[type] as PhotoRow)
                      : undefined
                  }
                />
              ))}
            </Fragment>
          ))}
        </div>

        <p className="text-xs text-muted-foreground/70">
          {t("medical_record.photos_hint")}
        </p>
      </div>

      {fileInput}

      <PhotoCompareDialog
        pairs={pairs}
        index={openPair}
        onIndexChange={setOpenPair}
      />
    </TooltipProvider>
  )
}

import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "#/components/ui/card.tsx"
import { BeforeAfterGallery } from "#/components/medical-photos/before-after-gallery.tsx"
import type {
  MedicalPhotoType,
  PhotoRow,
} from "#/components/medical-photos/photo-types.ts"
import { useTrans } from "#/hooks/use-trans.ts"

export interface TreatmentRow {
  id: number
  service_name?: string | null
  notes?: string | null
}

export type { PhotoRow }

interface MedicalRecordAttachmentsProps {
  treatments?: TreatmentRow[]
  photos?: PhotoRow[]
  /** Diisi hanya bila pembacanya juga boleh mengubah rekam medis ini. */
  onPickPhotos?: (files: FileList, type: MedicalPhotoType) => void
  onDeletePhoto?: (photo: PhotoRow) => void
  uploading?: boolean
  deletingPhotoId?: number | null
}

/**
 * Treatment dan foto yang menempel pada satu rekam medis. Keduanya tetap
 * tersimpan walau rekam medisnya dihapus, jadi ditampilkan apa adanya.
 */
export function MedicalRecordAttachments({
  treatments,
  photos,
  onPickPhotos,
  onDeletePhoto,
  uploading = false,
  deletingPhotoId = null,
}: MedicalRecordAttachmentsProps) {
  const { t } = useTrans()

  return (
    <>
      <Card>
        <CardHeader>
          <CardTitle className="text-base">
            {t("medical_record.treatments")}
          </CardTitle>
        </CardHeader>
        <CardContent>
          {treatments?.length ? (
            <ul className="divide-y divide-border/50 text-sm">
              {treatments.map((treatment) => (
                <li key={treatment.id} className="py-2 first:pt-0 last:pb-0">
                  <span className="font-medium">
                    {treatment.service_name ?? "-"}
                  </span>
                  {treatment.notes ? (
                    <span className="text-muted-foreground">
                      {" "}
                      — {treatment.notes}
                    </span>
                  ) : null}
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-sm text-muted-foreground">
              {t("medical_record.no_treatments")}
            </p>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">
            {t("medical_record.photos_before_after")}
          </CardTitle>
          <CardDescription>{t("medical_record.photo_rules")}</CardDescription>
        </CardHeader>
        <CardContent>
          <BeforeAfterGallery
            photos={photos}
            onPick={onPickPhotos}
            onDelete={onDeletePhoto}
            uploading={uploading}
            deletingId={deletingPhotoId}
          />
        </CardContent>
      </Card>
    </>
  )
}

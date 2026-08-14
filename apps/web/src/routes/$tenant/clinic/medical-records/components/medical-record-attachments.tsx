import { Badge } from "#/components/ui/badge.tsx"
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "#/components/ui/card.tsx"
import { useTrans } from "#/hooks/use-trans.ts"

export interface TreatmentRow {
  id: number
  service_name?: string | null
  notes?: string | null
}

export interface PhotoRow {
  id: number
  type: string
  type_label?: string | null
  url?: string | null
  path?: string | null
}

interface MedicalRecordAttachmentsProps {
  treatments?: TreatmentRow[]
  photos?: PhotoRow[]
}

/**
 * Treatment dan foto yang menempel pada satu rekam medis. Keduanya tetap
 * tersimpan walau rekam medisnya dihapus, jadi ditampilkan apa adanya.
 */
export function MedicalRecordAttachments({
  treatments,
  photos,
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
            {t("medical_record.photos")}
          </CardTitle>
        </CardHeader>
        <CardContent>
          {photos?.length ? (
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
              {photos.map((photo) => (
                <figure key={photo.id} className="space-y-1.5">
                  <img
                    src={photo.url ?? photo.path ?? ""}
                    alt={photo.type_label ?? photo.type}
                    loading="lazy"
                    className="h-28 w-full rounded-md border border-border/50 object-cover transition-opacity hover:opacity-90"
                  />
                  <figcaption>
                    <Badge variant="secondary">
                      {photo.type_label ?? photo.type}
                    </Badge>
                  </figcaption>
                </figure>
              ))}
            </div>
          ) : (
            <p className="text-sm text-muted-foreground">
              {t("medical_record.no_photos")}
            </p>
          )}
        </CardContent>
      </Card>
    </>
  )
}

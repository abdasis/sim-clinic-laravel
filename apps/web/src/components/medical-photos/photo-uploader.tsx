import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"
import { XIcon } from "lucide-react"
import { Input } from "#/components/ui/input.tsx"
import { Label } from "#/components/ui/label.tsx"
import { Button } from "#/components/ui/button.tsx"
import {
  NativeSelect,
  NativeSelectOption,
} from "#/components/ui/native-select.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiUpload } from "#/lib/api.ts"

const MAX_BYTES = 2 * 1024 * 1024
const ACCEPTED = ["image/jpeg", "image/png"]

export interface SelectedPhoto {
  id: string
  file: File
  type: "before" | "after"
  url: string
}

interface PhotoUploaderProps {
  /** Dipanggil setiap daftar foto berubah (mode tunda-unggah). */
  onChange?: (photos: SelectedPhoto[]) => void
  /** Bila diisi bersama recordId, tombol unggah langsung ditampilkan. */
  tenant?: string
  recordId?: number
  onUploaded?: () => void
}

let seq = 0

export function PhotoUploader({
  onChange,
  tenant,
  recordId,
  onUploaded,
}: PhotoUploaderProps) {
  const { t } = useTrans()
  const [photos, setPhotos] = useState<SelectedPhoto[]>([])
  const [uploading, setUploading] = useState(false)

  useEffect(() => {
    onChange?.(photos)
  }, [photos, onChange])

  const addFiles = useCallback(
    (files: FileList | null) => {
      if (!files) return
      const next: SelectedPhoto[] = []
      for (const file of Array.from(files)) {
        if (!ACCEPTED.includes(file.type)) {
          toast.error(t("general.error"))
          continue
        }
        if (file.size > MAX_BYTES) {
          toast.error(t("general.error"))
          continue
        }
        seq += 1
        next.push({
          id: `photo-${seq}`,
          file,
          type: "before",
          url: URL.createObjectURL(file),
        })
      }
      if (next.length) setPhotos((prev) => [...prev, ...next])
    },
    [t],
  )

  function setType(id: string, type: "before" | "after") {
    setPhotos((prev) => prev.map((p) => (p.id === id ? { ...p, type } : p)))
  }

  function remove(id: string) {
    setPhotos((prev) => {
      const found = prev.find((p) => p.id === id)
      if (found) URL.revokeObjectURL(found.url)
      return prev.filter((p) => p.id !== id)
    })
  }

  async function uploadNow() {
    if (!tenant || !recordId) return
    setUploading(true)
    try {
      for (const photo of photos) {
        const form = new FormData()
        form.append("file", photo.file)
        form.append("type", photo.type)
        await apiUpload(`/${tenant}/clinic/medical-records/${recordId}/photos`, form)
      }
      toast.success(t("medical_record.photo_added"))
      setPhotos([])
      onUploaded?.()
    } catch {
      toast.error(t("general.error"))
    } finally {
      setUploading(false)
    }
  }

  return (
    <div className="space-y-3">
      <div className="space-y-1.5">
        <Label>{t("medical_record.photos")}</Label>
        <Input
          type="file"
          accept="image/jpeg,image/png"
          multiple
          onChange={(e) => {
            addFiles(e.target.files)
            e.target.value = ""
          }}
        />
        <p className="text-xs text-muted-foreground">JPG / PNG, max 2MB</p>
      </div>

      {photos.length > 0 ? (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
          {photos.map((photo) => (
            <div key={photo.id} className="space-y-2 rounded-md border p-2">
              <div className="relative">
                <img
                  src={photo.url}
                  alt={photo.file.name}
                  className="h-28 w-full rounded object-cover"
                />
                <Button
                  type="button"
                  size="icon-xs"
                  variant="destructive"
                  className="absolute top-1 right-1"
                  aria-label={t("general.delete")}
                  onClick={() => remove(photo.id)}
                >
                  <XIcon />
                </Button>
              </div>
              <NativeSelect
                size="sm"
                className="w-full"
                value={photo.type}
                aria-label={t("medical_record.photo_type")}
                onChange={(e) => setType(photo.id, e.target.value as "before" | "after")}
              >
                <NativeSelectOption value="before">
                  {t("clinic.medical_photo_type.before")}
                </NativeSelectOption>
                <NativeSelectOption value="after">
                  {t("clinic.medical_photo_type.after")}
                </NativeSelectOption>
              </NativeSelect>
            </div>
          ))}
        </div>
      ) : null}

      {tenant && recordId ? (
        <Button
          type="button"
          variant="outline"
          disabled={uploading || photos.length === 0}
          onClick={uploadNow}
        >
          {t("general.save")}
        </Button>
      ) : null}
    </div>
  )
}

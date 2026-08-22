import { useState } from "react"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"

import {
  photoRejection,
  type MedicalPhotoType,
  type PhotoRow,
} from "#/components/medical-photos/photo-types.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiDelete, apiUpload } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

/**
 * Unggah dan hapus foto sebelum/sesudah pada satu rekam medis.
 *
 * Dipisah dari halamannya karena keduanya satu urusan yang sama: menyaring
 * berkas, mengirimnya, lalu menyegarkan tampilan yang ikut menampilkan foto
 * itu. Halaman detail tinggal menyambungkannya ke galeri dan dialognya.
 */
export function useRecordPhotos(tenant: string, recordId: string) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const [pendingDelete, setPendingDelete] = useState<PhotoRow | null>(null)

  // Rekam medis ini dan riwayat pasien yang ikut menampilkan fotonya
  // sama-sama basi begitu satu foto masuk atau keluar.
  function refresh() {
    qc.invalidateQueries({ queryKey: ["medical-record", tenant, recordId] })
    qc.invalidateQueries({ queryKey: ["patient-medical-records"] })
  }

  const upload = useMutation({
    mutationFn: async ({
      files,
      type,
    }: {
      files: File[]
      type: MedicalPhotoType
    }) => {
      // Berurutan, bukan serentak: unggahan dari ponsel di jaringan klinik
      // gampang saling menyikut, dan urutan berkasnya yang menentukan
      // pasangan sebelum/sesudah yang terbaca nanti.
      for (const file of files) {
        const form = new FormData()
        form.append("file", file)
        form.append("type", type)
        await apiUpload(
          `/${tenant}/clinic/medical-records/${recordId}/photos`,
          form,
        )
      }
    },
    onSuccess: () => {
      toast.success(t("medical_record.photo_added"))
      refresh()
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  const remove = useMutation({
    mutationFn: (photo: PhotoRow) =>
      apiDelete(
        `/${tenant}/clinic/medical-records/${recordId}/photos/${photo.id}`,
      ),
    onSuccess: () => {
      toast.success(t("medical_record.photo_deleted"))
      refresh()
      setPendingDelete(null)
    },
    onError: (err: ApiError) => {
      toast.error(err.message)
      setPendingDelete(null)
    },
  })

  /** Saring dulu di sini supaya berkas yang pasti ditolak server tidak dikirim. */
  function pick(files: FileList, type: MedicalPhotoType) {
    const accepted: File[] = []

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

      accepted.push(file)
    }

    if (accepted.length) upload.mutate({ files: accepted, type })
  }

  return {
    pick,
    uploading: upload.isPending,
    pendingDelete,
    setPendingDelete,
    deleting: remove.isPending,
    confirmDelete: () => {
      if (pendingDelete) remove.mutate(pendingDelete)
    },
  }
}

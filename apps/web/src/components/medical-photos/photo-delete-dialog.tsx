import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "#/components/ui/alert-dialog.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import type { PhotoRow } from "./photo-types.ts"

interface PhotoDeleteDialogProps {
  /** Foto yang mau dihapus; null berarti dialognya tertutup. */
  photo: PhotoRow | null
  pending: boolean
  onCancel: () => void
  onConfirm: () => void
}

/**
 * Konfirmasi sebelum satu foto klinis dibuang.
 *
 * Dikonfirmasi karena tidak bisa dibatalkan: berkasnya ikut dihapus dari
 * disk, dan foto pasien tidak bisa dipotret ulang di keadaan yang sama.
 */
export function PhotoDeleteDialog({
  photo,
  pending,
  onCancel,
  onConfirm,
}: PhotoDeleteDialogProps) {
  const { t } = useTrans()

  return (
    <AlertDialog
      open={photo !== null}
      onOpenChange={(open) => {
        if (!open && !pending) onCancel()
      }}
    >
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{t("medical_record.photo_delete")}</AlertDialogTitle>
          <AlertDialogDescription>
            {t("medical_record.photo_delete_confirm")}
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel disabled={pending}>
            {t("general.cancel")}
          </AlertDialogCancel>
          <AlertDialogAction
            disabled={pending}
            onClick={(event) => {
              event.preventDefault()
              onConfirm()
            }}
          >
            {pending ? t("general.loading") : t("general.delete")}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}

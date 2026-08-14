import { useMutation, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"

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
import { apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

interface DeactivateStaffDialogProps {
  tenant: string
  staffId: number
  staffName: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Konfirmasi nonaktifkan staf. Dipisah dari menu aksi supaya perilaku
 * konfirmasi + invalidasi query hidup di satu tempat.
 */
export function DeactivateStaffDialog({
  tenant,
  staffId,
  staffName,
  open,
  onOpenChange,
}: DeactivateStaffDialogProps) {
  const { t } = useTrans()
  const qc = useQueryClient()

  const mutation = useMutation({
    mutationFn: () => apiPost(`/${tenant}/clinic/staff/${staffId}/deactivate`),
    onSuccess: () => {
      toast.success(t("staff.deactivated"))
      qc.invalidateQueries({ queryKey: ["staff"] })
      onOpenChange(false)
    },
    onError: (err: ApiError) => {
      toast.error(err.message)
      onOpenChange(false)
    },
  })

  return (
    <AlertDialog open={open} onOpenChange={onOpenChange}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{t("staff.deactivate")}</AlertDialogTitle>
          <AlertDialogDescription>
            {t("staff.deactivate_confirm")}
            {staffName ? ` (${staffName})` : null}
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel disabled={mutation.isPending}>
            {t("general.cancel")}
          </AlertDialogCancel>
          <AlertDialogAction
            disabled={mutation.isPending}
            onClick={(e) => {
              e.preventDefault()
              mutation.mutate()
            }}
          >
            {mutation.isPending ? t("general.loading") : t("staff.deactivate")}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}

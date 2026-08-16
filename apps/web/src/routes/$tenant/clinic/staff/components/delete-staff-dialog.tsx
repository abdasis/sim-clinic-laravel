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
import { apiDelete } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

interface DeleteStaffDialogProps {
  tenant: string
  staffId: number
  staffName: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function DeleteStaffDialog({
  tenant,
  staffId,
  staffName,
  open,
  onOpenChange,
}: DeleteStaffDialogProps) {
  const { t } = useTrans()
  const qc = useQueryClient()

  const mutation = useMutation({
    mutationFn: () => apiDelete(`/${tenant}/clinic/staff/${staffId}`),
    onSuccess: () => {
      toast.success(t("staff.deleted"))
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
          <AlertDialogTitle>
            {t("staff.delete")} — {staffName}
          </AlertDialogTitle>
          <AlertDialogDescription>
            {t("staff.delete_confirm")}
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
            {mutation.isPending ? t("general.loading") : t("staff.delete")}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}

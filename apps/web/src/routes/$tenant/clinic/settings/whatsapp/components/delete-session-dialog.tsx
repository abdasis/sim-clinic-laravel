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

interface DeleteSessionDialogProps {
  tenant: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

/** Hapus sesi di gateway; nomornya harus dipindai ulang setelahnya. */
export function DeleteSessionDialog({
  tenant,
  open,
  onOpenChange,
}: DeleteSessionDialogProps) {
  const { t } = useTrans()
  const qc = useQueryClient()

  const remove = useMutation({
    mutationFn: () => apiDelete(`/${tenant}/clinic/broadcasts/connection`),
    onSuccess: () => {
      onOpenChange(false)
      toast.success(t("broadcast.session_deleted"))
      void qc.invalidateQueries({ queryKey: ["wa-connection", tenant] })
    },
    onError: (err: ApiError) => {
      onOpenChange(false)
      toast.error(err.message)
    },
  })

  return (
    <AlertDialog open={open} onOpenChange={onOpenChange}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{t("broadcast.delete_session")}</AlertDialogTitle>
          <AlertDialogDescription>
            {t("broadcast.delete_session_confirm")}
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel disabled={remove.isPending}>
            {t("general.cancel")}
          </AlertDialogCancel>
          <AlertDialogAction
            disabled={remove.isPending}
            onClick={(event) => {
              event.preventDefault()
              remove.mutate()
            }}
          >
            {remove.isPending ? t("general.loading") : t("general.delete")}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}

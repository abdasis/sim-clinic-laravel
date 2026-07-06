import { useState } from "react"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { Button } from "#/components/ui/button.tsx"
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
import { apiPatch } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

export function TenantStatusToggle({
  id,
  status,
}: {
  id: number
  status: string
}) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const [open, setOpen] = useState(false)
  const next = status === "active" ? "inactive" : "active"

  const mutation = useMutation({
    mutationFn: () =>
      apiPatch(`/central/tenants/${id}/status`, { status: next }),
    onSuccess: () => {
      toast.success(t("tenant.status_changed"))
      qc.invalidateQueries({ queryKey: ["tenants"] })
      setOpen(false)
    },
    onError: (err: ApiError) => {
      toast.error(err.message)
      setOpen(false)
    },
  })

  return (
    <>
      <Button variant="outline" size="sm" onClick={() => setOpen(true)}>
        {t(`tenant.status.${status}`)}
      </Button>
      <AlertDialog open={open} onOpenChange={setOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("general.confirm")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t(`tenant.status.${next}`)}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("general.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              onClick={(e) => {
                e.preventDefault()
                mutation.mutate()
              }}
            >
              {t("general.confirm")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}

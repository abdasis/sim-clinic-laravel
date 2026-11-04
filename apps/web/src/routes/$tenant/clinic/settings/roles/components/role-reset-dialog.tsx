import { useState } from "react"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { RotateCcw } from "lucide-react"
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
import { Button } from "#/components/ui/button.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

/**
 * Kembalikan izin satu peran ke bawaan. Dikonfirmasi karena penyesuaian yang
 * sudah dibuat hilang tanpa bisa dibatalkan.
 */
export function RoleResetDialog({
  tenant,
  role,
  label,
}: {
  tenant: string
  role: string
  label: string
}) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const [open, setOpen] = useState(false)

  const reset = useMutation({
    mutationFn: () => apiPost(`/${tenant}/clinic/roles/${role}/reset`),
    onSuccess: () => {
      toast.success(t("role.reset"))
      qc.invalidateQueries({ queryKey: ["roles", tenant] })
      setOpen(false)
    },
    onError: (err: ApiError) => {
      toast.error(err.message)
      setOpen(false)
    },
  })

  return (
    <>
      <Tooltip>
        <TooltipTrigger asChild>
          <Button
            variant="ghost"
            size="icon"
            className="size-6 text-muted-foreground hover:text-foreground"
            aria-label={`${t("role.reset_button")} — ${label}`}
            onClick={() => setOpen(true)}
          >
            <RotateCcw className="size-3.5" />
          </Button>
        </TooltipTrigger>
        <TooltipContent>{t("role.reset_button")}</TooltipContent>
      </Tooltip>

      <AlertDialog open={open} onOpenChange={setOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {t("role.reset_button")} — {label}
            </AlertDialogTitle>
            <AlertDialogDescription className="text-pretty">
              {t("role.reset_confirm")}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={reset.isPending}>
              {t("general.cancel")}
            </AlertDialogCancel>
            <AlertDialogAction
              disabled={reset.isPending}
              onClick={(event) => {
                event.preventDefault()
                reset.mutate()
              }}
            >
              {reset.isPending ? t("general.loading") : t("role.reset_button")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}

import { useState } from "react"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { MoreHorizontal, Pencil, Trash2 } from "lucide-react"
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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "#/components/ui/dropdown-menu.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiDelete } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { PromoFormDialog, type PromoFormValues } from "./promo-form-dialog.tsx"

export function PromoActionsCell({
  tenant,
  promo,
}: {
  tenant: string
  promo: PromoFormValues
}) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const [editOpen, setEditOpen] = useState(false)
  const [deleteOpen, setDeleteOpen] = useState(false)

  const remove = useMutation({
    mutationFn: () => apiDelete(`/${tenant}/clinic/promos/${promo.id}`),
    onSuccess: () => {
      toast.success(t("promo.deleted"))
      qc.invalidateQueries({ queryKey: ["promos"] })
      qc.invalidateQueries({ queryKey: ["services"] })
      qc.invalidateQueries({ queryKey: ["products"] })
      setDeleteOpen(false)
    },
    onError: (err: ApiError) => {
      toast.error(err.message)
      setDeleteOpen(false)
    },
  })

  return (
    <>
      <DropdownMenu>
        <Tooltip>
          <TooltipTrigger asChild>
            <DropdownMenuTrigger asChild>
              <Button
                variant="ghost"
                size="icon"
                className="size-8"
                aria-label={t("general.actions")}
              >
                <MoreHorizontal className="size-4" />
              </Button>
            </DropdownMenuTrigger>
          </TooltipTrigger>
          <TooltipContent className="flex items-center gap-2">
            {t("general.actions")}
            <Kbd>.</Kbd>
          </TooltipContent>
        </Tooltip>

        <DropdownMenuContent align="end" className="min-w-40">
          <DropdownMenuItem onSelect={() => setEditOpen(true)}>
            <Pencil className="size-4" />
            {t("general.edit")}
            <Kbd className="ml-auto">e</Kbd>
          </DropdownMenuItem>
          <DropdownMenuItem
            variant="destructive"
            onSelect={() => setDeleteOpen(true)}
          >
            <Trash2 className="size-4" />
            {t("general.delete")}
            <Kbd className="ml-auto">d</Kbd>
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <PromoFormDialog
        tenant={tenant}
        promo={promo}
        open={editOpen}
        onOpenChange={setEditOpen}
      />

      <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {t("general.delete")} — {promo.name}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {t("promo.delete_confirm")}
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
    </>
  )
}

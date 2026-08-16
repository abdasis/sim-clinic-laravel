import { useState } from "react"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { useNavigate } from "@tanstack/react-router"
import { Archive, MoreHorizontal, Pencil, Trash2 } from "lucide-react"
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
  TooltipProvider,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiDelete } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

export interface PatientActionsRow {
  id: number
  name: string
  can_delete?: boolean
}

/**
 * Aksi baris pasien. Pasien yang sudah pernah datang tidak dihapus permanen
 * melainkan diarsipkan — dan itu dikatakan lebih dulu di dialognya, bukan
 * baru ketahuan setelah tombol ditekan.
 */
export function PatientActionsCell({
  tenant,
  patient,
}: {
  tenant: string
  patient: PatientActionsRow
}) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const navigate = useNavigate()
  const [confirmOpen, setConfirmOpen] = useState(false)

  const permanent = patient.can_delete !== false

  const mutation = useMutation({
    mutationFn: () => apiDelete(`/${tenant}/clinic/patients/${patient.id}`),
    onSuccess: () => {
      toast.success(permanent ? t("patient.deleted") : t("patient.archived"))
      qc.invalidateQueries({ queryKey: ["patients"] })
      setConfirmOpen(false)
    },
    onError: (err: ApiError) => {
      toast.error(err.message)
      setConfirmOpen(false)
    },
  })

  return (
    <>
      <DropdownMenu>
        <TooltipProvider>
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
        </TooltipProvider>

        <DropdownMenuContent align="end" className="min-w-40">
          <DropdownMenuItem
            onSelect={() =>
              navigate({
                to: "/$tenant/clinic/patients/$id/edit",
                params: { tenant, id: String(patient.id) },
              })
            }
          >
            <Pencil className="size-4" />
            {t("general.edit")}
            <Kbd className="ml-auto">e</Kbd>
          </DropdownMenuItem>
          <DropdownMenuItem
            variant="destructive"
            onSelect={() => setConfirmOpen(true)}
          >
            {permanent ? (
              <Trash2 className="size-4" />
            ) : (
              <Archive className="size-4" />
            )}
            {t("patient.delete")}
            <Kbd className="ml-auto">h</Kbd>
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {t("patient.delete")} — {patient.name}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {permanent
                ? t("patient.delete_confirm")
                : t("patient.archive_confirm")}
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
              {mutation.isPending ? t("general.loading") : t("patient.delete")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}

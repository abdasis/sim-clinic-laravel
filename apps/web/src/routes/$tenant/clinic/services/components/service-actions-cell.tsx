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
import type { ServiceRow } from "./service-form.tsx"

export function ServiceActionsCell({
  tenant,
  service,
}: {
  tenant: string
  service: ServiceRow
}) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const navigate = useNavigate()
  const [archiveOpen, setArchiveOpen] = useState(false)
  const [deleteOpen, setDeleteOpen] = useState(false)

  const archive = useMutation({
    mutationFn: () => apiDelete(`/${tenant}/clinic/services/${service.id}`),
    onSuccess: () => {
      toast.success(t("service.archived"))
      qc.invalidateQueries({ queryKey: ["services"] })
      setArchiveOpen(false)
    },
    onError: (err: ApiError) => {
      toast.error(err.message)
      setArchiveOpen(false)
    },
  })

  // Hapus permanen berbeda dari arsip: barisnya benar-benar hilang, dan
  // server menolaknya bila entrinya sudah pernah terpakai.
  const destroy = useMutation({
    mutationFn: () => apiDelete(`/${tenant}/clinic/services/${service.id}/force`),
    onSuccess: () => {
      toast.success(t("service.deleted"))
      qc.invalidateQueries({ queryKey: ["services"] })
      setDeleteOpen(false)
    },
    onError: (err: ApiError) => {
      toast.error(err.message)
      setDeleteOpen(false)
    },
  })

  const isArchived = service.status === "archived"

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
          <DropdownMenuItem onSelect={() =>
              navigate({
                to: "/$tenant/clinic/services/$id/edit",
                params: { tenant, id: String(service.id) },
              })
            }>
            <Pencil className="size-4" />
            {t("general.edit")}
            <Kbd className="ml-auto">e</Kbd>
          </DropdownMenuItem>
          <DropdownMenuItem
            variant="destructive"
            disabled={isArchived}
            onSelect={() => setArchiveOpen(true)}
          >
            <Archive className="size-4" />
            {t("service.archive")}
            <Kbd className="ml-auto">a</Kbd>
          </DropdownMenuItem>
          {/* Hapus permanen dipisah dari arsip dan diberi ikon berbeda:
              keduanya sama-sama merah, dan yang satu tidak bisa dibatalkan. */}
          <DropdownMenuItem
            variant="destructive"
            onSelect={() => setDeleteOpen(true)}
          >
            <Trash2 className="size-4" />
            {t("service.delete")}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>


      <AlertDialog open={archiveOpen} onOpenChange={setArchiveOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {t("service.archive")} — {service.name}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {t("service.archive_confirm")}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={archive.isPending}>
              {t("general.cancel")}
            </AlertDialogCancel>
            <AlertDialogAction
              disabled={archive.isPending}
              onClick={(e) => {
                e.preventDefault()
                archive.mutate()
              }}
            >
              {archive.isPending ? t("general.loading") : t("service.archive")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {t("service.delete")} — {service.name}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {t("service.delete_confirm")}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={destroy.isPending}>
              {t("general.cancel")}
            </AlertDialogCancel>
            <AlertDialogAction
              disabled={destroy.isPending}
              onClick={(e) => {
                e.preventDefault()
                destroy.mutate()
              }}
            >
              {destroy.isPending ? t("general.loading") : t("service.delete")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}

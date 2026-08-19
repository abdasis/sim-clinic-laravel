import { useState } from "react"
import { useMutation, useQueryClient } from "@tanstack/react-query"
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
import {
  UnitFormDialog,
  type UnitFormValues,
} from "./unit-form-dialog.tsx"

export function UnitActionsCell({
  tenant,
  unit,
  usageCount,
}: {
  tenant: string
  unit: UnitFormValues
  usageCount: number
}) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const [editOpen, setEditOpen] = useState(false)
  const [archiveOpen, setArchiveOpen] = useState(false)
  const [deleteOpen, setDeleteOpen] = useState(false)

  const refresh = () => {
    qc.invalidateQueries({ queryKey: ["units"] })
    qc.invalidateQueries({ queryKey: ["products"] })
  }

  const archive = useMutation({
    mutationFn: () => apiDelete(`/${tenant}/clinic/units/${unit.id}`),
    onSuccess: () => {
      toast.success(t("unit.archived"))
      refresh()
      setArchiveOpen(false)
    },
    onError: (err: ApiError) => {
      toast.error(err.message)
      setArchiveOpen(false)
    },
  })

  // Hapus permanen berbeda dari arsip: barisnya benar-benar hilang, dan
  // server menolaknya selama masih ada item yang memakainya.
  const destroy = useMutation({
    mutationFn: () =>
      apiDelete(`/${tenant}/clinic/units/${unit.id}/force`),
    onSuccess: () => {
      toast.success(t("unit.deleted"))
      refresh()
      setDeleteOpen(false)
    },
    onError: (err: ApiError) => {
      toast.error(err.message)
      setDeleteOpen(false)
    },
  })

  const isArchived = unit.status === "archived"
  const isUsed = usageCount > 0

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

        <DropdownMenuContent align="end" className="min-w-44">
          <DropdownMenuItem onSelect={() => setEditOpen(true)}>
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
          {/* Dimatikan selama masih dipakai: server menolaknya, dan menu yang
              menawarkan aksi yang pasti gagal hanya membuang waktu. */}
          <DropdownMenuItem
            variant="destructive"
            disabled={isUsed}
            onSelect={() => setDeleteOpen(true)}
          >
            <Trash2 className="size-4" />
            {t("service.delete")}
          </DropdownMenuItem>
          {/* Item yang dimatikan tidak bisa memicu tooltip, jadi alasannya
              ditulis langsung di bawahnya — tanpa ini "Hapus" yang abu-abu
              terbaca seperti fitur yang rusak. */}
          {isUsed ? (
            <p className="px-2 pt-1 pb-1.5 text-xs leading-snug text-muted-foreground">
              {t("unit.used_by").replace(":count", String(usageCount))}
            </p>
          ) : null}
        </DropdownMenuContent>
      </DropdownMenu>

      <UnitFormDialog
        tenant={tenant}
        unit={unit}
        open={editOpen}
        onOpenChange={setEditOpen}
      />

      <AlertDialog open={archiveOpen} onOpenChange={setArchiveOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {t("service.archive")} — {unit.name}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {t("unit.archive_confirm")}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={archive.isPending}>
              {t("general.cancel")}
            </AlertDialogCancel>
            <AlertDialogAction
              disabled={archive.isPending}
              onClick={(event) => {
                event.preventDefault()
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
              {t("service.delete")} — {unit.name}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {t("unit.delete_confirm")}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={destroy.isPending}>
              {t("general.cancel")}
            </AlertDialogCancel>
            <AlertDialogAction
              disabled={destroy.isPending}
              onClick={(event) => {
                event.preventDefault()
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

import { useState } from "react"
import { MoreHorizontal, Pencil, Trash2, UserMinus } from "lucide-react"

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
import type { StaffRow } from "../index.tsx"
import { DeactivateStaffDialog } from "./deactivate-staff-dialog.tsx"
import { DeleteStaffDialog } from "./delete-staff-dialog.tsx"
import { StaffEditModal } from "./staff-edit-modal.tsx"

export function StaffActionsCell({
  tenant,
  staff,
}: {
  tenant: string
  staff: StaffRow
}) {
  const { t } = useTrans()
  const [editOpen, setEditOpen] = useState(false)
  const [deactivateOpen, setDeactivateOpen] = useState(false)
  const [deleteOpen, setDeleteOpen] = useState(false)

  // Staf yang sudah mencatat data klinik tidak bisa dihapus permanen —
  // penilaiannya datang dari server, jadi menunya tidak menjanjikan sesuatu
  // yang nanti ditolak.
  const canDelete = staff.can_delete !== false

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
            onSelect={() => setDeactivateOpen(true)}
          >
            <UserMinus className="size-4" />
            {t("staff.deactivate")}
            <Kbd className="ml-auto">d</Kbd>
          </DropdownMenuItem>
          <TooltipProvider>
            <Tooltip>
              <TooltipTrigger asChild>
                {/* Pembungkus span: item yang disabled tidak memicu tooltip,
                    padahal justru di situ alasannya perlu dibaca. */}
                <span className="block">
                  <DropdownMenuItem
                    variant="destructive"
                    disabled={!canDelete}
                    onSelect={() => setDeleteOpen(true)}
                    className="w-full"
                  >
                    <Trash2 className="size-4" />
                    {t("staff.delete")}
                    <Kbd className="ml-auto">h</Kbd>
                  </DropdownMenuItem>
                </span>
              </TooltipTrigger>
              {!canDelete ? (
                <TooltipContent side="left" className="max-w-56">
                  {t("staff.has_history_short")}
                </TooltipContent>
              ) : null}
            </Tooltip>
          </TooltipProvider>
        </DropdownMenuContent>
      </DropdownMenu>

      <StaffEditModal
        tenant={tenant}
        staff={staff}
        open={editOpen}
        onOpenChange={setEditOpen}
      />

      <DeleteStaffDialog
        tenant={tenant}
        staffId={staff.id}
        staffName={staff.name}
        open={deleteOpen}
        onOpenChange={setDeleteOpen}
      />

      <DeactivateStaffDialog
        tenant={tenant}
        staffId={staff.id}
        staffName={staff.name}
        open={deactivateOpen}
        onOpenChange={setDeactivateOpen}
      />
    </>
  )
}

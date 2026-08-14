import { useState } from "react"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { MoreHorizontal, UserCog, UserMinus } from "lucide-react"
import { toast } from "sonner"

import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "#/components/ui/dropdown-menu.tsx"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "#/components/ui/dialog.tsx"
import {
  NativeSelect,
  NativeSelectOption,
} from "#/components/ui/native-select.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPatch } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import type { StaffRow } from "../index.tsx"
import { DeactivateStaffDialog } from "./deactivate-staff-dialog.tsx"

const ROLE_VALUES = ["admin", "doctor", "therapist", "cashier"] as const

export function StaffActionsCell({
  tenant,
  staff,
}: {
  tenant: string
  staff: StaffRow
}) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const [roleOpen, setRoleOpen] = useState(false)
  const [deactivateOpen, setDeactivateOpen] = useState(false)
  const [role, setRole] = useState(staff.clinic_role)

  const roleMutation = useMutation({
    mutationFn: (clinic_role: string) =>
      apiPatch(`/${tenant}/clinic/staff/${staff.id}/role`, { clinic_role }),
    onSuccess: () => {
      toast.success(t("staff.role_changed"))
      qc.invalidateQueries({ queryKey: ["staff"] })
      setRoleOpen(false)
    },
    onError: (err: ApiError) => toast.error(err.message),
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

        <DropdownMenuContent align="end" className="min-w-44">
          <DropdownMenuItem onSelect={() => setRoleOpen(true)}>
            <UserCog className="size-4" />
            {t("staff.change_role")}
            <Kbd className="ml-auto">r</Kbd>
          </DropdownMenuItem>
          <DropdownMenuItem
            variant="destructive"
            onSelect={() => setDeactivateOpen(true)}
          >
            <UserMinus className="size-4" />
            {t("staff.deactivate")}
            <Kbd className="ml-auto">d</Kbd>
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <Dialog open={roleOpen} onOpenChange={setRoleOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("staff.change_role")}</DialogTitle>
          </DialogHeader>
          <NativeSelect value={role} onChange={(e) => setRole(e.target.value)}>
            {ROLE_VALUES.map((value) => (
              <NativeSelectOption key={value} value={value}>
                {t(`clinic.role.${value}`)}
              </NativeSelectOption>
            ))}
          </NativeSelect>
          <DialogFooter>
            <Button
              onClick={() => roleMutation.mutate(role)}
              disabled={roleMutation.isPending}
            >
              {roleMutation.isPending ? t("general.loading") : t("general.save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

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

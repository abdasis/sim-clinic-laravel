import { useState } from "react"
import { useMutation, useQueryClient } from "@tanstack/react-query"
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
  NativeSelect,
  NativeSelectOption,
} from "#/components/ui/native-select.tsx"
import { Button } from "#/components/ui/button.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPatch, apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import type { StaffRow } from "../index.tsx"

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

  const invalidate = () => qc.invalidateQueries({ queryKey: ["staff"] })

  const roleMutation = useMutation({
    mutationFn: (clinic_role: string) =>
      apiPatch(`/${tenant}/clinic/staff/${staff.id}/role`, { clinic_role }),
    onSuccess: () => {
      toast.success(t("staff.role_changed"))
      invalidate()
      setRoleOpen(false)
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  const deactivateMutation = useMutation({
    mutationFn: () =>
      apiPost(`/${tenant}/clinic/staff/${staff.id}/deactivate`),
    onSuccess: () => {
      toast.success(t("staff.deactivated"))
      invalidate()
      setDeactivateOpen(false)
    },
    onError: (err: ApiError) => {
      toast.error(err.message)
      setDeactivateOpen(false)
    },
  })

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="ghost" size="sm">
            {t("general.actions")}
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem onSelect={() => setRoleOpen(true)}>
            {t("staff.change_role")}
          </DropdownMenuItem>
          <DropdownMenuItem
            variant="destructive"
            onSelect={() => setDeactivateOpen(true)}
          >
            {t("staff.deactivate")}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <Dialog open={roleOpen} onOpenChange={setRoleOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("staff.change_role")}</DialogTitle>
          </DialogHeader>
          <NativeSelect
            value={role}
            onChange={(e) => setRole(e.target.value)}
          >
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
              {t("general.save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={deactivateOpen} onOpenChange={setDeactivateOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("staff.deactivate")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("staff.deactivate_confirm")}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("general.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              onClick={(e) => {
                e.preventDefault()
                deactivateMutation.mutate()
              }}
            >
              {t("staff.deactivate")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}

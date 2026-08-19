import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"

import { Checkbox } from "#/components/ui/checkbox.tsx"
import { Label } from "#/components/ui/label.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

const ROLES = ["doctor", "therapist", "cashier", "admin"] as const

interface CommissionEligibleRolesProps {
  tenant: string
}

/**
 * Peran mana yang berhak menerima fee dan komisi.
 *
 * Disimpan sebagai data, bukan ditulis di kode: kasir belum ikut sekarang,
 * tapi ia menawarkan skincare di meja bayar dan suatu saat bisa diikutkan —
 * dan saat itu tiba, yang berubah cukup centang di sini.
 */
export function CommissionEligibleRoles({ tenant }: CommissionEligibleRolesProps) {
  const { t } = useTrans()
  const qc = useQueryClient()

  const setting = useQuery({
    queryKey: ["commission-setting", tenant],
    queryFn: () =>
      apiGet<{ data: { eligible_roles: string[] } }>(
        `/${tenant}/clinic/commission-rules/setting`,
      ),
  })

  const save = useMutation({
    mutationFn: (eligible_roles: string[]) =>
      apiPut(`/${tenant}/clinic/commission-rules/setting`, { eligible_roles }),
    onSuccess: () => {
      toast.success(t("commission.setting_saved"))
      qc.invalidateQueries({ queryKey: ["commission-setting", tenant] })
      // Pratinjau ikut berubah begitu daftarnya berubah.
      qc.invalidateQueries({ queryKey: ["commission-preview"] })
      qc.invalidateQueries({ queryKey: ["staff"] })
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  const selected = setting.data?.data.eligible_roles ?? []

  const toggle = (role: string, checked: boolean) => {
    const next = checked
      ? [...selected, role]
      : selected.filter((value) => value !== role)

    // Tanpa satu pun peran, fee tidak pernah jatuh ke siapa pun — server
    // menolaknya, jadi jangan sampai terkirim dari sini.
    if (next.length === 0) {
      toast.error(t("commission.eligible_roles_required"))

      return
    }

    save.mutate(next)
  }

  return (
    <div className="space-y-2 rounded-md border border-border/50 p-3">
      <div>
        <p className="text-sm font-medium">{t("commission.eligible_roles")}</p>
        <p className="mt-0.5 text-xs text-pretty text-muted-foreground">
          {t("commission.eligible_roles_hint")}
        </p>
      </div>

      {setting.isLoading ? (
        <Skeleton className="h-6 w-full" />
      ) : (
        <div className="flex flex-wrap gap-x-5 gap-y-2">
          {ROLES.map((role) => (
            <div key={role} className="flex items-center gap-2">
              <Checkbox
                id={`commission-role-${role}`}
                checked={selected.includes(role)}
                disabled={save.isPending}
                onCheckedChange={(checked) => toggle(role, checked === true)}
              />
              <Label
                htmlFor={`commission-role-${role}`}
                className="text-sm font-normal"
              >
                {t(`clinic.role.${role}`)}
              </Label>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

import { useMutation, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"

import { Switch } from "#/components/ui/switch.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import type { ClinicRoleKey } from "#/types/clinic-role.ts"
import type {
  ModulePermission,
  RoleMatrixResponse,
} from "#/types/role-matrix.ts"
import { RoleResetDialog } from "./role-reset-dialog.tsx"

interface RoleMatrixGridProps {
  tenant: string
  data: RoleMatrixResponse
}

/**
 * Matriks izin: baris modul, kolom peran. Tiap sel dua saklar — Lihat dan
 * Kelola.
 *
 * Satu saklar mengirim seluruh izin peran itu, bukan satu izin saja: server
 * menyimpannya sebagai satu himpunan, dan mengirim sebagian akan menghapus
 * sisanya.
 */
export function RoleMatrixGrid({ tenant, data }: RoleMatrixGridProps) {
  const { t } = useTrans()
  const qc = useQueryClient()

  const save = useMutation({
    mutationFn: ({
      role,
      modules,
    }: {
      role: string
      modules: Record<string, ModulePermission>
    }) => apiPut(`/${tenant}/clinic/roles/${role}`, { modules }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["roles", tenant] })
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  const toggle = (
    role: ClinicRoleKey,
    module: string,
    field: "view" | "manage",
    next: boolean,
  ) => {
    // Peran yang belum punya izin sama sekali tidak muncul di matriks.
    const current = data.matrix[role] ?? {}
    const cell = current[module] ?? { view: false, manage: false }

    // Kelola butuh Lihat. Dicerminkan di sini supaya saklarnya bergerak
    // seketika, bukan baru terlihat setelah server menolak.
    const updated: ModulePermission =
      field === "manage"
        ? { view: next ? true : cell.view, manage: next }
        : { view: next, manage: next ? cell.manage : false }

    save.mutate({ role, modules: { ...current, [module]: updated } })
  }

  return (
    <div className="overflow-x-auto rounded-lg border border-border/50 bg-card shadow-sm">
      <table className="w-full border-collapse text-sm">
        <thead>
          <tr className="border-b border-border/50">
            <th className="sticky left-0 z-10 bg-card px-3 py-2.5 text-left font-medium">
              {t("role.module")}
            </th>
            {data.roles.map((role) => (
              <th key={role.key} className="px-3 py-2.5 text-center font-medium">
                <div className="flex flex-col items-center gap-1">
                  <span>{role.label}</span>
                  <RoleResetDialog
                    tenant={tenant}
                    role={role.key}
                    label={role.label}
                  />
                </div>
              </th>
            ))}
          </tr>
          <tr className="border-b border-border/50 text-xs text-muted-foreground">
            <th className="sticky left-0 z-10 bg-card px-3 py-1.5" />
            {data.roles.map((role) => (
              <th key={role.key} className="px-3 py-1.5 font-normal">
                <div className="flex items-center justify-center gap-6">
                  <Tooltip>
                    <TooltipTrigger asChild>
                      <span className="cursor-help">{t("role.view")}</span>
                    </TooltipTrigger>
                    <TooltipContent>{t("role.view_hint")}</TooltipContent>
                  </Tooltip>
                  <Tooltip>
                    <TooltipTrigger asChild>
                      <span className="cursor-help">{t("role.manage")}</span>
                    </TooltipTrigger>
                    <TooltipContent>{t("role.manage_hint")}</TooltipContent>
                  </Tooltip>
                </div>
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {data.modules.map((module) => (
            <tr
              key={module.key}
              className="border-b border-border/40 transition-colors last:border-0 hover:bg-muted/40"
            >
              <td className="sticky left-0 z-10 bg-card px-3 py-2 font-medium whitespace-nowrap">
                {module.label}
              </td>
              {data.roles.map((role) => {
                const cell = data.matrix[role.key as ClinicRoleKey]?.[
                  module.key
                ] ?? { view: false, manage: false }

                return (
                  <td key={role.key} className="px-3 py-2">
                    <div className="flex items-center justify-center gap-6">
                      <Switch
                        checked={cell.view}
                        disabled={save.isPending}
                        aria-label={`${role.label} — ${module.label} — ${t("role.view")}`}
                        onCheckedChange={(next) =>
                          toggle(
                            role.key as ClinicRoleKey,
                            module.key,
                            "view",
                            next,
                          )
                        }
                      />
                      <Switch
                        checked={cell.manage}
                        disabled={save.isPending}
                        aria-label={`${role.label} — ${module.label} — ${t("role.manage")}`}
                        onCheckedChange={(next) =>
                          toggle(
                            role.key as ClinicRoleKey,
                            module.key,
                            "manage",
                            next,
                          )
                        }
                      />
                    </div>
                  </td>
                )
              })}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

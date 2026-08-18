import type { ClinicRoleKey } from "#/types/clinic-role.ts"

export interface ModulePermission {
  view: boolean
  manage: boolean
}

export interface RoleMatrixEntry {
  key: string
  label: string
}

/** Bentuk response GET /{tenant}/clinic/roles. */
export interface RoleMatrixResponse {
  roles: RoleMatrixEntry[]
  modules: RoleMatrixEntry[]
  /**
   * peran → modul → izin.
   *
   * Partial dengan sengaja: peran yang belum punya baris izin sama sekali
   * tidak muncul di sini, dan pembacanya sudah memperlakukan itu sebagai
   * "belum diberi izin apa pun".
   */
  matrix: Partial<Record<ClinicRoleKey, Record<string, ModulePermission>>>
}

/** Cocok dengan App\Enums\ClinicRole di backend. */
export type ClinicRoleKey = "admin" | "doctor" | "therapist" | "cashier"

/** Urutannya tetap: dari yang paling luas aksesnya ke yang paling sempit. */
export const CLINIC_ROLES: readonly ClinicRoleKey[] = [
  "admin",
  "doctor",
  "therapist",
  "cashier",
] as const

/**
 * Pilihan peran untuk formulir. Labelnya diambil saat dipanggil, bukan
 * disimpan — terjemahannya baru siap setelah kamus termuat.
 */
export function clinicRoleOptions(t: (key: string) => string) {
  return CLINIC_ROLES.map((value) => ({
    value,
    label: t(`clinic.role.${value}`),
  }))
}

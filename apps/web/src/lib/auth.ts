import { setToken, getToken } from "#/lib/api.ts"
import type { Appearance } from "#/types/appearance.ts"

export interface AuthUser {
  id: number
  name: string
  email: string
  role: string
  clinic_role: string | null
  tenant_id: number
  /** Preferensi tampilan; belum ada untuk akun yang belum mengaturnya. */
  appearance?: Appearance | null
  /**
   * Izin efektif milik akun ini di klinik yang sedang dibuka.
   *
   * Belum ada untuk sesi yang login sebelum ini dipasang; pembacanya harus
   * memperlakukan `undefined` sebagai "belum tahu", bukan "tidak punya
   * apa-apa".
   */
  permissions?: string[]
}

const USER_KEY = "clinic_user"

export function setAuth(token: string, user: AuthUser) {
  setToken(token)
  if (typeof window !== "undefined") {
    window.localStorage.setItem(USER_KEY, JSON.stringify(user))
  }
}

/** Perbarui user tersimpan tanpa menyentuh token — dipakai halaman preferensi. */
export function setAuthUser(user: AuthUser) {
  if (typeof window !== "undefined") {
    window.localStorage.setItem(USER_KEY, JSON.stringify(user))
  }
}

export function getAuthUser(): AuthUser | null {
  if (typeof window === "undefined") return null
  const raw = window.localStorage.getItem(USER_KEY)
  if (!raw) return null
  try {
    return JSON.parse(raw) as AuthUser
  } catch {
    return null
  }
}

export function clearAuth() {
  setToken(null)
  if (typeof window !== "undefined") {
    window.localStorage.removeItem(USER_KEY)
  }
}

export function isAuthenticated(): boolean {
  return getToken() !== null
}

export function hasPlatformRole(): boolean {
  return getAuthUser()?.role === "platform_admin"
}

export function hasClinicRole(...roles: string[]): boolean {
  const user = getAuthUser()
  return user?.clinic_role != null && roles.includes(user.clinic_role)
}

/**
 * Izin efektif akun yang sedang login, atau `null` bila belum diketahui.
 *
 * Dibedakan dari daftar kosong: sesi lama belum menyimpannya, dan
 * memperlakukan itu sebagai "tidak punya izin apa pun" akan mengosongkan
 * seluruh menu sampai /me selesai dimuat.
 */
export function getPermissions(): string[] | null {
  const permissions = getAuthUser()?.permissions

  return Array.isArray(permissions) ? permissions : null
}

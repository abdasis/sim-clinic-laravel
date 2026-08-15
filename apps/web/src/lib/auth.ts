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

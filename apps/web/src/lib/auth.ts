import { setToken, getToken } from "#/lib/api.ts"

export interface AuthUser {
  id: number
  name: string
  email: string
  role: string
  clinic_role: string | null
  tenant_id: number
}

const USER_KEY = "clinic_user"

export function setAuth(token: string, user: AuthUser) {
  setToken(token)
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

export function hasClinicRole(...roles: string[]): boolean {
  const user = getAuthUser()
  return user?.clinic_role != null && roles.includes(user.clinic_role)
}

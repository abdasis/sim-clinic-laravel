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

/**
 * Bentuk yang dikirim `/{tenant}/me`: profil ditambah daftar izin efektif.
 *
 * Izinnya sengaja dipisah dari AuthUser. Yang disimpan di localStorage hanya
 * AuthUser; daftar izin hidup di memori selama halaman terbuka dan diambil
 * ulang dari server tiap kali shell klinik dipasang. Menyimpannya berarti
 * satu skrip asing yang berhasil masuk bisa membaca peta lengkap kemampuan
 * akun ini — dan yang lebih buruk, mengubahnya untuk membuka menu yang
 * seharusnya tertutup.
 */
export interface MeUser extends AuthUser {
  permissions?: string[]
}

const USER_KEY = "clinic_user"

/**
 * Buang apa pun di luar profil sebelum menyentuh localStorage.
 *
 * Disaring di satu pintu, bukan di tiap pemanggil: server boleh saja mulai
 * mengirim field baru kapan saja, dan yang tidak disebut di sini tidak akan
 * pernah ikut tersimpan.
 */
function profileOnly(user: MeUser | AuthUser): AuthUser {
  return {
    id: user.id,
    name: user.name,
    email: user.email,
    role: user.role,
    clinic_role: user.clinic_role,
    tenant_id: user.tenant_id,
    appearance: user.appearance ?? null,
  }
}

export function setAuth(token: string, user: MeUser | AuthUser) {
  setToken(token)
  if (typeof window !== "undefined") {
    window.localStorage.setItem(USER_KEY, JSON.stringify(profileOnly(user)))
  }
}

/** Perbarui user tersimpan tanpa menyentuh token — dipakai halaman preferensi. */
export function setAuthUser(user: MeUser | AuthUser) {
  if (typeof window !== "undefined") {
    window.localStorage.setItem(USER_KEY, JSON.stringify(profileOnly(user)))
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

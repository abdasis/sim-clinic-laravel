const rawBaseUrl = import.meta.env.VITE_API_URL ?? "http://localhost:8123/api"
const baseUrl = rawBaseUrl.endsWith("/api")
  ? rawBaseUrl
  : `${rawBaseUrl.replace(/\/+$/, "")}/api`

const TOKEN_KEY = "clinic_token"

/**
 * Kunci profil pengguna, disalin dari `auth.ts` dengan sengaja.
 *
 * Mengimpor `auth.ts` dari sini melahirkan lingkaran — `auth.ts` sudah
 * mengambil `setToken` dari berkas ini — dan satu string yang digandakan
 * lebih murah daripada memecah kedua modul demi satu nama.
 */
const USER_KEY = "clinic_user"

export function getToken(): string | null {
  if (typeof window === "undefined") return null
  return window.localStorage.getItem(TOKEN_KEY)
}

export function setToken(token: string | null) {
  if (typeof window === "undefined") return
  if (token) window.localStorage.setItem(TOKEN_KEY, token)
  else window.localStorage.removeItem(TOKEN_KEY)
}

export interface ApiError {
  status: number
  message: string
  errors?: Record<string, string[]>
}

/**
 * Pesan dari server untuk sebuah kegagalan, kalau ada.
 *
 * Backend sudah menulis pesannya untuk dibaca manusia — "Anda tidak punya
 * akses", "jalankan php artisan migrate" — dan kalimat umum buatan frontend
 * justru menyembunyikan langkah yang perlu diambil.
 */
export function apiErrorMessage(error: unknown): string | undefined {
  if (typeof error !== "object" || error === null) return undefined

  const message = (error as { message?: unknown }).message

  return typeof message === "string" && message.trim() !== "" ? message : undefined
}

/**
 * Kode HTTP-nya, untuk ditempelkan kecil di bawah pesan.
 *
 * Bukan untuk dibaca staf klinik, melainkan supaya satu foto layar cukup
 * untuk membedakan tidak-punya-akses dari server-yang-bermasalah — tanpa
 * perlu membuka konsol browser di tablet.
 */
export function apiErrorStatus(error: unknown): number | undefined {
  if (typeof error !== "object" || error === null) return undefined

  const status = (error as { status?: unknown }).status

  return typeof status === "number" && status > 0 ? status : undefined
}

function buildUrl(path: string, params?: Record<string, unknown>): URL {
  const url = new URL(baseUrl + path)
  if (params) {
    for (const [key, value] of Object.entries(params)) {
      if (value === null || value === undefined) continue
      if (typeof value === "object") {
        for (const [subKey, subValue] of Object.entries(
          value as Record<string, unknown>,
        )) {
          if (subValue === null || subValue === undefined) continue
          url.searchParams.set(`${key}[${subKey}]`, String(subValue))
        }
        continue
      }
      url.searchParams.set(key, String(value))
    }
  }
  return url
}

function headers(json = true): HeadersInit {
  const h: Record<string, string> = { Accept: "application/json" }
  if (json) h["Content-Type"] = "application/json"
  const token = getToken()
  if (token) h["Authorization"] = `Bearer ${token}`
  return h
}

/**
 * Pesan galat yang aman ditampilkan ke pengguna.
 *
 * Pesan dari server dipakai apa adanya selama ia memang kalimat untuk dibaca
 * orang — itulah yang membuat galat validasi dan penolakan izin terbaca jelas.
 * Yang disaring adalah pesan yang jelas bukan untuk pengguna: jejak tumpukan,
 * nama kelas berikut namespace-nya, kueri SQL, dan path berkas di server.
 * Ketiganya bocor saat APP_DEBUG menyala di tempat yang salah, dan isinya
 * menceritakan struktur dalam aplikasi kepada siapa pun yang memancingnya.
 *
 * Panjangnya juga dibatasi: kalimat sepanjang layar hampir pasti bukan pesan
 * yang ditulis untuk dibaca.
 */
const INTERNAL_PATTERNS = [
  /\bStack trace\b/i,
  /#\d+\s+\/.+\(\d+\)/,
  /\b[A-Za-z_]+\\[A-Za-z_\\]+::/,
  /\b(SELECT|INSERT|UPDATE|DELETE)\b.+\bFROM\b|\bSQLSTATE\b/i,
  /(\/var\/www|\/home\/|[A-Za-z]:\\\\).*\.php/i,
  /\bin \/.+\.php:\d+/i,
]

function presentableMessage(message: unknown, status: number): string {
  const fallback = `Terjadi kesalahan (${status}). Coba lagi sebentar lagi ya.`

  if (typeof message !== "string") return fallback

  const trimmed = message.trim()

  if (trimmed === "" || trimmed.length > 300) return fallback

  return INTERNAL_PATTERNS.some((pattern) => pattern.test(trimmed))
    ? fallback
    : trimmed
}

/**
 * Halaman masuk yang sesuai untuk sebuah alamat halaman.
 *
 * Tiap klinik punya halamannya sendiri (`/{tenant}/login`), dan admin platform
 * punya `/central/login`. Alamat yang tidak dikenali dikembalikan ke beranda —
 * menebak nama klinik dari alamat asing hanya melahirkan 404 kedua.
 */
export function loginPathFor(pathname: string): string {
  const segmen = pathname.split("/").filter(Boolean)

  if (segmen.length === 0) return "/"
  if (segmen[0] === "central") return "/central/login"

  return `/${segmen[0]}/login`
}

/** Satu pengalihan saja; 401 beruntun tidak boleh saling menimpa. */
let sedangMengalihkan = false

/**
 * Sesi yang ditolak server: bersihkan dan antar kembali ke halaman masuk.
 *
 * Tanpa ini token yang sudah tidak sah — kedaluwarsa, dicabut, atau hilang
 * karena database server disemai ulang — meninggalkan aplikasi dalam keadaan
 * zombi. Halamannya tetap tampil karena nama pengguna dibaca dari
 * localStorage, sementara tiap permintaan ditolak diam-diam. Yang terlihat
 * oleh staf: fiturnya rusak, satu per satu, tanpa pernah disuruh masuk lagi.
 */
function sesiBerakhir(path: string): boolean {
  // Salah kata sandi juga menjawab 401. Itu bukan sesi yang berakhir, dan
  // mengalihkannya ke halaman masuk yang sedang dibuka hanya memuat ulang
  // formulirnya tepat saat pesan galatnya hendak dibaca.
  if (/\/login$/.test(path.split("?")[0])) return false

  return getToken() !== null
}

function pulangKeHalamanMasuk(): void {
  if (typeof window === "undefined" || sedangMengalihkan) return

  sedangMengalihkan = true
  window.localStorage.removeItem(TOKEN_KEY)
  window.localStorage.removeItem(USER_KEY)

  const tujuan = loginPathFor(window.location.pathname)

  // Muat ulang penuh, bukan navigasi router: seluruh cache kueri dan state
  // komponen ikut dibuang, jadi tidak ada sisa data milik sesi yang sudah mati.
  window.location.replace(`${tujuan}?expired=1`)
}

async function handle<T>(res: Response, path: string): Promise<T> {
  if (res.status === 204) return undefined as T
  const body = await res.json().catch(() => ({}))
  if (!res.ok) {
    if (res.status === 401 && sesiBerakhir(path)) pulangKeHalamanMasuk()

    const err: ApiError = {
      status: res.status,
      message: presentableMessage(body.message, res.status),
      errors: body.errors,
    }
    throw err
  }
  return body as T
}

export async function apiGet<T>(
  path: string,
  params?: Record<string, unknown>,
): Promise<T> {
  const res = await fetch(buildUrl(path, params), { headers: headers(false) })
  return handle<T>(res, path)
}

/**
 * Unduh berkas dari endpoint ber-otorisasi. Tautan biasa tidak bisa membawa
 * header Bearer, jadi berkasnya diambil sebagai blob lalu disodorkan lewat
 * object URL sekali pakai.
 */
export async function apiDownload(
  path: string,
  params: Record<string, unknown>,
  filename: string,
): Promise<void> {
  const res = await fetch(buildUrl(path, params), { headers: headers(false) })

  if (!res.ok) {
    const body = (await res.json().catch(() => ({}))) as { message?: string }
    const err: ApiError = {
      status: res.status,
      message: body.message ?? `API ${res.status}`,
    }
    throw err
  }

  const blob = await res.blob()
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement("a")
  anchor.href = url
  anchor.download = filename
  document.body.appendChild(anchor)
  anchor.click()
  anchor.remove()
  URL.revokeObjectURL(url)
}

export async function apiPost<T>(path: string, body?: unknown): Promise<T> {
  const res = await fetch(buildUrl(path), {
    method: "POST",
    headers: headers(),
    body: body === undefined ? undefined : JSON.stringify(body),
  })
  return handle<T>(res, path)
}

export async function apiPut<T>(path: string, body?: unknown): Promise<T> {
  const res = await fetch(buildUrl(path), {
    method: "PUT",
    headers: headers(),
    body: body === undefined ? undefined : JSON.stringify(body),
  })
  return handle<T>(res, path)
}

export async function apiPatch<T>(path: string, body?: unknown): Promise<T> {
  const res = await fetch(buildUrl(path), {
    method: "PATCH",
    headers: headers(),
    body: body === undefined ? undefined : JSON.stringify(body),
  })
  return handle<T>(res, path)
}

export async function apiDelete<T>(path: string): Promise<T> {
  const res = await fetch(buildUrl(path), {
    method: "DELETE",
    headers: headers(),
  })
  return handle<T>(res, path)
}

export async function apiUpload<T>(path: string, form: FormData): Promise<T> {
  const h: Record<string, string> = { Accept: "application/json" }
  const token = getToken()
  if (token) h["Authorization"] = `Bearer ${token}`
  const res = await fetch(buildUrl(path), { method: "POST", headers: h, body: form })
  return handle<T>(res, path)
}

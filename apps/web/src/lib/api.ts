const rawBaseUrl = import.meta.env.VITE_API_URL ?? "http://localhost:8000/api"
const baseUrl = rawBaseUrl.endsWith("/api")
  ? rawBaseUrl
  : `${rawBaseUrl.replace(/\/+$/, "")}/api`

const TOKEN_KEY = "clinic_token"

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

async function handle<T>(res: Response): Promise<T> {
  if (res.status === 204) return undefined as T
  const body = await res.json().catch(() => ({}))
  if (!res.ok) {
    const err: ApiError = {
      status: res.status,
      message: body.message ?? `API ${res.status}`,
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
  return handle<T>(res)
}

export async function apiPost<T>(path: string, body?: unknown): Promise<T> {
  const res = await fetch(buildUrl(path), {
    method: "POST",
    headers: headers(),
    body: body === undefined ? undefined : JSON.stringify(body),
  })
  return handle<T>(res)
}

export async function apiPut<T>(path: string, body?: unknown): Promise<T> {
  const res = await fetch(buildUrl(path), {
    method: "PUT",
    headers: headers(),
    body: body === undefined ? undefined : JSON.stringify(body),
  })
  return handle<T>(res)
}

export async function apiPatch<T>(path: string, body?: unknown): Promise<T> {
  const res = await fetch(buildUrl(path), {
    method: "PATCH",
    headers: headers(),
    body: body === undefined ? undefined : JSON.stringify(body),
  })
  return handle<T>(res)
}

export async function apiDelete<T>(path: string): Promise<T> {
  const res = await fetch(buildUrl(path), {
    method: "DELETE",
    headers: headers(),
  })
  return handle<T>(res)
}

export async function apiUpload<T>(path: string, form: FormData): Promise<T> {
  const h: Record<string, string> = { Accept: "application/json" }
  const token = getToken()
  if (token) h["Authorization"] = `Bearer ${token}`
  const res = await fetch(buildUrl(path), { method: "POST", headers: h, body: form })
  return handle<T>(res)
}

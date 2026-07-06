const baseUrl = import.meta.env.VITE_API_URL ?? "http://localhost:8000"

export async function apiGet<T>(
  path: string,
  params?: Record<string, unknown>,
): Promise<T> {
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

  const res = await fetch(url, { headers: { Accept: "application/json" } })
  if (!res.ok) throw new Error(`API ${res.status}`)
  return (await res.json()) as T
}
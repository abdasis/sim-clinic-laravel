import { useQuery } from "@tanstack/react-query"

import { apiGet } from "#/lib/api.ts"

/**
 * Identitas klinik yang sedang dibuka. Semua field selain `slug` bisa kosong:
 * klinik yang belum pernah mengisi profilnya tetap punya identitas minimum
 * dari data pendaftarannya.
 */
export interface ClinicIdentity {
  slug: string
  name: string
  tagline: string | null
  address: string | null
  phone: string | null
  logo_url: string | null
  receipt_note: string | null
}

/**
 * Dipakai brand sidebar, jadi menempel di setiap halaman klinik. Karena
 * identitas nyaris tidak pernah berubah dalam satu sesi, hasilnya ditahan
 * lima menit dan tidak diambil ulang tiap kali jendela kembali fokus —
 * kalau tidak, pindah tab saja sudah memicu request.
 */
export function useClinicIdentity(tenant: string, enabled = true) {
  return useQuery({
    queryKey: ["clinic-identity", tenant],
    queryFn: () => apiGet<{ data: ClinicIdentity }>(`/${tenant}/clinic/identity`),
    select: (res) => res.data,
    enabled: enabled && Boolean(tenant),
    staleTime: 5 * 60_000,
    refetchOnWindowFocus: false,
  })
}

import { useMe } from "#/hooks/use-appearance.ts"
import { useIsMounted } from "#/hooks/use-is-mounted.ts"

/**
 * Apakah akun yang sedang login memegang izin ini di klinik yang dibuka.
 *
 * Jawabannya diambil dari daftar izin yang dikirim `/{tenant}/me`, bukan dari
 * peta peran di sisi web: matriks peran bisa disunting admin per klinik, jadi
 * peran yang sama tidak selalu berarti kemampuan yang sama. Menebaknya dari
 * `clinic_role` berarti tombol yang tampil lalu ditolak server saat diklik —
 * persis yang sudah dihindari menu sidebar.
 *
 * Selama daftarnya belum sampai, jawabannya `false`. Untuk menu, bawaan itu
 * akan membuat sidebar sempat kosong; untuk tombol aksi justru sebaliknya —
 * aksi yang muncul lalu hilang lebih buruk daripada aksi yang telat muncul.
 * Kuerinya dibagi dengan kerangka klinik dan sudah hangat sejak halaman
 * dibuka, jadi jedanya nyaris tidak terlihat.
 */
export function useCan(tenant: string, permission: string): boolean {
  const mounted = useIsMounted()
  const { data: me } = useMe(tenant, mounted)

  return Array.isArray(me?.permissions)
    ? me.permissions.includes(permission)
    : false
}

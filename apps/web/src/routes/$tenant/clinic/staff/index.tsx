import { createFileRoute, redirect } from "@tanstack/react-router"

/**
 * Alamat lama daftar staf. Buku markah dan tautan lama masih menunjuk ke
 * sini, jadi dialihkan ke rumah barunya di bawah Pengaturan.
 */
export const Route = createFileRoute("/$tenant/clinic/staff/")({
  beforeLoad: ({ params }) => {
    throw redirect({
      to: "/$tenant/clinic/settings/users",
      params: { tenant: params.tenant },
    })
  },
})

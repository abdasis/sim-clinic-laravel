import { createFileRoute, redirect } from "@tanstack/react-router"

/**
 * Alamat lama log aktivitas. Buku markah dan tautan lama masih menunjuk ke
 * sini, jadi dialihkan ke rumah barunya di bawah Pengaturan.
 */
export const Route = createFileRoute("/$tenant/clinic/activity-logs/")({
  beforeLoad: ({ params }) => {
    throw redirect({
      to: "/$tenant/clinic/settings/activity-logs",
      params: { tenant: params.tenant },
    })
  },
})

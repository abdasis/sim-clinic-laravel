import { createFileRoute, redirect } from "@tanstack/react-router"

/** Alamat lama Preferensi; sekarang tinggal di bawah Pengaturan. */
export const Route = createFileRoute("/$tenant/clinic/preferences/")({
  beforeLoad: ({ params }) => {
    throw redirect({
      to: "/$tenant/clinic/settings/preferences",
      params: { tenant: params.tenant },
    })
  },
})

import { createFileRoute, redirect } from "@tanstack/react-router"

/** Alamat lama Pengaturan Chatbot; sekarang tinggal di bawah Pengaturan. */
export const Route = createFileRoute("/$tenant/clinic/chatbot/settings")({
  beforeLoad: ({ params }) => {
    throw redirect({
      to: "/$tenant/clinic/settings/chatbot",
      params: { tenant: params.tenant },
    })
  },
})

import { useQueryClient } from "@tanstack/react-query"
import { useEffect } from "react"

import { useMe } from "#/hooks/use-appearance.ts"
import { useIsMounted } from "#/hooks/use-is-mounted.ts"
import { getAuthUser } from "#/lib/auth.ts"
import { getEcho } from "#/lib/echo.ts"
import { threadKey } from "./use-inbox-thread.ts"

interface InboxMessageEvent {
  conversation_id: number
  message_id: number
  direction: "in" | "out"
}

/**
 * Segarkan kotak masuk saat server mengabarkan pesan baru.
 *
 * Payload hanya dipakai sebagai pemicu: daftar dan utas diambil ulang dari
 * server supaya urutan, lencana, dan status kirim tetap satu sumber kebenaran.
 */
export function useInboxRealtime(tenant: string) {
  const qc = useQueryClient()
  const mounted = useIsMounted()
  // `/me` lebih dipercaya daripada localStorage: sesi lama bisa tidak punya
  // tenant_id, dan shell klinik sudah memuat kueri ini sehingga tidak ada
  // permintaan tambahan.
  const me = useMe(tenant, mounted)
  const tenantId = me.data?.tenant_id ?? getAuthUser()?.tenant_id ?? null

  useEffect(() => {
    if (tenantId === null) return

    let active = true
    let client: Awaited<ReturnType<typeof getEcho>> = null
    const channelName = `tenant.${tenantId}.inbox`

    void getEcho().then((echo) => {
      if (!active || echo === null) return
      client = echo

      echo
        .private(channelName)
        .listen(".inbox.message", (payload: InboxMessageEvent) => {
          if (typeof payload?.conversation_id !== "number") return

          void qc.invalidateQueries({
            queryKey: ["inbox-conversations", tenant],
          })
          void qc.invalidateQueries({
            queryKey: threadKey(tenant, payload.conversation_id),
          })
        })
    })

    return () => {
      active = false
      client?.leave(channelName)
    }
  }, [qc, tenant, tenantId])
}

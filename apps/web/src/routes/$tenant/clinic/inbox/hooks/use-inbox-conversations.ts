import { useQuery } from "@tanstack/react-query"

import { apiGet } from "#/lib/api.ts"
import type { DataTableResponse } from "#/types/data-table.ts"
import type { Conversation } from "../types.ts"

/**
 * Daftar percakapan, terbaru dulu.
 *
 * Satu halaman berisi 50 percakapan: kotak masuk klinik jarang lebih panjang
 * dari itu dalam sehari, dan daftar ini tidak punya kendali halaman sendiri.
 * Kalau suatu klinik benar-benar melewatinya, tambahkan pemuatan bertahap
 * seperti riwayat pembelian pasien.
 */
export function useInboxConversations(tenant: string, search: string) {
  const term = search.trim()

  return useQuery({
    queryKey: ["inbox-conversations", tenant, term],
    queryFn: () =>
      apiGet<DataTableResponse<Conversation>>(
        `/${tenant}/clinic/inbox/conversations`,
        { per_page: 50, search: term || undefined },
      ),
    placeholderData: (previous) => previous,
  })
}

import { QueryClient } from '@tanstack/react-query'

/**
 * Galat 4xx tidak berubah kalau diulang: 403 tetap 403, 404 tetap 404.
 * Mengulanginya hanya menahan tabel di keadaan memuat selama beberapa detik
 * sebelum akhirnya menampilkan pesan — di layar itu terbaca sebagai halaman
 * yang menggantung, bukan sebagai penolakan. Yang layak diulang hanya galat
 * jaringan dan galat server.
 */
function shouldRetry(failureCount: number, error: unknown): boolean {
  const status = (error as { status?: number } | null)?.status

  if (typeof status === 'number' && status >= 400 && status < 500) {
    return false
  }

  return failureCount < 2
}

export function getContext() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: shouldRetry },
    },
  })

  return {
    queryClient,
  }
}
export default function TanstackQueryProvider() {}

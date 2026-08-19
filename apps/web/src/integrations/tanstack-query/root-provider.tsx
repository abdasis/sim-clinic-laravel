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
      queries: {
        retry: shouldRetry,
        /**
         * Semenit dianggap masih segar. Tanpa ini setiap perpindahan halaman
         * yang memakai kueri yang sama menembak ulang ke server, padahal
         * datanya baru saja diambil beberapa detik sebelumnya.
         */
        staleTime: 60_000,
        /**
         * Kembali ke tab tidak lagi memicu pengambilan ulang serentak. Di
         * klinik, jendela aplikasi ditinggal terbuka sepanjang hari dan
         * di-fokus ulang puluhan kali — tiap kali itu berarti satu gelombang
         * permintaan untuk data yang belum tentu berubah.
         *
         * Yang memang perlu segar setelah aksi tetap disegarkan lewat
         * invalidateQueries di mutasinya masing-masing, bukan lewat fokus.
         */
        refetchOnWindowFocus: false,
      },
    },
  })

  return {
    queryClient,
  }
}
export default function TanstackQueryProvider() {}

import { Card, CardContent } from "#/components/ui/card.tsx"
import { EmptyState } from "#/components/ui/empty-state.tsx"
import type { EmptyIllustrationName } from "#/components/ui/empty-illustration.tsx"
import { useTrans } from "#/hooks/use-trans.ts"

interface NotFoundStateProps {
  /** Kalimat yang menjelaskan apa yang dicari dan kenapa mungkin tidak ada. */
  description: string
  illustration?: EmptyIllustrationName
  /** Jalan keluar — biasanya tautan kembali ke daftarnya. */
  action?: React.ReactNode
}

/**
 * Layar untuk data yang dituju tapi tidak ditemukan.
 *
 * Sebelumnya tiap halaman menuliskannya sendiri sebagai satu baris "Tidak
 * ada data" — jalan buntu yang terbaca seperti halaman rusak, dan tidak
 * memberi tahu apakah datanya memang tidak ada, terhapus, atau tautannya
 * yang salah. Bentuknya kini sama dengan keadaan kosong lain di aplikasi,
 * dan menyediakan jalan keluar alih-alih membiarkan penggunanya menekan
 * tombol kembali peramban.
 */
export function NotFoundState({
  description,
  illustration = "default",
  action,
}: NotFoundStateProps) {
  const { t } = useTrans()

  return (
    <Card className="border-dashed">
      <CardContent className="py-10">
        <EmptyState
          illustration={illustration}
          title={t("general.not_found")}
          description={description}
          action={action}
        />
      </CardContent>
    </Card>
  )
}

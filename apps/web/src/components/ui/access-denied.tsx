import { Card, CardContent } from "#/components/ui/card.tsx"
import { EmptyState } from "#/components/ui/empty-state.tsx"
import type { EmptyIllustrationName } from "#/components/ui/empty-illustration.tsx"
import { useTrans } from "#/hooks/use-trans.ts"

interface AccessDeniedProps {
  /** Kalimat yang menjelaskan siapa yang boleh membukanya. */
  description: string
  illustration?: EmptyIllustrationName
}

/**
 * Layar untuk halaman yang tidak boleh dibuka peran ini.
 *
 * Sebelumnya tiap halaman menuliskannya sendiri sebagai satu baris teks abu:
 * layar yang terlihat rusak, bukan layar yang menjelaskan. Bentuknya kini
 * sama dengan keadaan kosong lain di aplikasi, dan kalimatnya menyebut siapa
 * yang perlu dihubungi supaya penggunanya tahu langkah berikutnya.
 */
export function AccessDenied({
  description,
  illustration = "reports",
}: AccessDeniedProps) {
  const { t } = useTrans()

  return (
    <Card>
      <CardContent className="py-10">
        <EmptyState
          illustration={illustration}
          title={t("clinic.forbidden")}
          description={description}
        />
      </CardContent>
    </Card>
  )
}

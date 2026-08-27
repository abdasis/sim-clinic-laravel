import { Skeleton } from "#/components/ui/skeleton.tsx"
import { useTrans } from "#/hooks/use-trans.ts"

export interface PreviewRecipient {
  patient_id: number
  name: string
  phone: string
}

export interface AudiencePreview {
  count: number
  without_phone: number
  opted_out: number
  recipients: PreviewRecipient[]
}

interface RecipientPreviewProps {
  data?: AudiencePreview
  isLoading: boolean
}

/**
 * Daftar penerima sebelum broadcast dikirim.
 *
 * Sebelumnya panel ini hanya menyebut jumlahnya. Server memang mengirim tiga
 * nama contoh, tapi layarnya tidak pernah menampilkannya — jadi admin menekan
 * kirim ke ratusan orang tanpa pernah melihat satu pun nama yang akan
 * menerimanya, dan salah pilih sasaran baru ketahuan setelah pesannya
 * terlanjur berangkat.
 *
 * Yang tidak terjangkau ikut disebut dan dibedakan sebabnya: nomor yang tidak
 * terbaca adalah data yang perlu dibetulkan, sedangkan yang menolak promosi
 * adalah pilihan pasien yang justru harus dihormati.
 *
 * Daftarnya digulir lewat wadah CSS biasa, bukan ScrollArea Radix: viewport
 * ScrollArea memakai height 100% terhadap induk yang tingginya auto, jadi
 * max-height di root-nya tidak mengekang apa pun — daftar panjang meluber
 * keluar dan menimpa seluruh isi dialog.
 */
export function RecipientPreview({ data, isLoading }: RecipientPreviewProps) {
  const { t } = useTrans()

  if (isLoading) {
    return (
      <div className="space-y-1.5 rounded-md border border-border/50 bg-muted/40 p-3">
        <Skeleton className="h-4 w-40" />
        <Skeleton className="h-24 w-full" />
      </div>
    )
  }

  if (!data) return null

  return (
    <div className="space-y-2 rounded-md border border-border/50 bg-muted/40 p-3">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <p className="text-xs font-medium">
          {t("broadcast.preview_count").replace(":count", String(data.count))}
        </p>
        <p className="text-xxs text-muted-foreground">
          {t("broadcast.recipients_hint")}
        </p>
      </div>

      {data.without_phone > 0 ? (
        <p className="text-xs text-muted-foreground">
          {t("broadcast.without_phone").replace(
            ":count",
            String(data.without_phone),
          )}
        </p>
      ) : null}

      {data.opted_out > 0 ? (
        <p className="text-xs text-muted-foreground">
          {t("broadcast.opted_out").replace(":count", String(data.opted_out))}
        </p>
      ) : null}

      {data.recipients.length === 0 ? (
        <p className="py-3 text-center text-xs text-muted-foreground">
          {t("broadcast.no_recipients")}
        </p>
      ) : (
        <div className="max-h-40 overflow-y-auto rounded-sm border border-border/50 bg-background">
          <ul className="divide-y divide-border/40">
            {data.recipients.map((recipient) => (
              <li
                key={recipient.patient_id}
                className="flex items-center justify-between gap-3 px-2.5 py-1.5"
              >
                <span className="min-w-0 truncate text-xs">
                  {recipient.name}
                </span>
                <span className="shrink-0 text-xxs tabular-nums text-muted-foreground">
                  {recipient.phone}
                </span>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  )
}

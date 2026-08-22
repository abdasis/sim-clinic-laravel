import { Link } from "@tanstack/react-router"

import { Button } from "#/components/ui/button.tsx"
import { Card, CardContent } from "#/components/ui/card.tsx"
import { EmptyState } from "#/components/ui/empty-state.tsx"
import { PhotoPairPreview } from "#/components/medical-photos/photo-pair-preview.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "#/components/ui/table.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { formatCurrency, formatDate } from "#/lib/format.ts"
import { productLines, treatmentLines } from "./record-lines.ts"
import { DASH } from "./record-types.ts"
import type { CellLine, RecordRow } from "./record-types.ts"

interface VisitsTableProps {
  tenant: string
  records: RecordRow[]
  isLoading: boolean
  hasNextPage: boolean
  isFetchingNextPage: boolean
  onLoadMore: () => void
}

/**
 * Tabel riwayat kunjungan: satu baris satu kunjungan, dengan keluhan,
 * tindakan, produk, dan foto sebelum-sesudahnya berjajar.
 *
 * Dipisah dari halamannya karena halaman itu kini menaungi dua sisi riwayat
 * — kunjungan dan pembelian — dan menampung keduanya dalam satu berkas
 * membuat keduanya sulit dibaca sendiri-sendiri.
 */
export function VisitsTable({
  tenant,
  records,
  isLoading,
  hasNextPage,
  isFetchingNextPage,
  onLoadMore,
}: VisitsTableProps) {
  const { t } = useTrans()

  return (
    <>
      {isLoading ? (
        <div className="space-y-2">
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-16 w-full" />
          <Skeleton className="h-16 w-full" />
        </div>
      ) : records.length === 0 ? (
        <Card className="border-dashed">
          <CardContent className="py-10">
            <EmptyState
              illustration="medical-records"
              title={t("medical_record.empty_patient")}
              description={t("medical_record.empty_patient_desc")}
            />
          </CardContent>
        </Card>
      ) : (
        <>
          {/* Tanpa div pembungkus tambahan: komponen Table sudah membawa
              wadah overflow-x sendiri, dan menumpuk dua scroller membuat
              kolom terakhir tidak bisa dicapai di layar sempit. */}
          <Card className="overflow-hidden py-0">
            <Table>
              <TableHeader>
                <TableRow className="hover:bg-transparent">
                  <TableHead className="w-28 text-2xs font-medium tracking-wide uppercase">
                    {t("medical_record.date")}
                  </TableHead>
                  <TableHead className="w-[24%] text-2xs font-medium tracking-wide uppercase">
                    {t("medical_record.complaint")}
                  </TableHead>
                  <TableHead className="w-[24%] text-2xs font-medium tracking-wide uppercase">
                    {t("medical_record.action")}
                  </TableHead>
                  <TableHead className="w-[26%] text-2xs font-medium tracking-wide uppercase">
                    {t("medical_record.obt_hcp")}
                  </TableHead>
                  <TableHead className="w-24 text-2xs font-medium tracking-wide uppercase">
                    {t("medical_record.photos")}
                  </TableHead>
                  <TableHead className="w-44 text-2xs font-medium tracking-wide uppercase">
                    {t("medical_record.doctor")}
                  </TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {records.map((record) => {
                  const performers = (record.transaction?.performers ?? [])
                    .map((performer) => performer.name)
                    .join(", ")

                  return (
                    <TableRow
                      key={record.id}
                      className="relative cursor-pointer align-top transition-colors"
                    >
                      {/* Link dipasang di sel pertama, bukan onClick di
                          barisnya: dengan begini baris tetap bisa dibuka di
                          tab baru dan terbaca pembaca layar sebagai tautan. */}
                      <TableCell className="py-3 font-medium tabular-nums whitespace-nowrap">
                        <Link
                          to="/$tenant/clinic/medical-records/$recordId"
                          params={{ tenant, recordId: String(record.id) }}
                          className="after:absolute after:inset-0 hover:underline"
                        >
                          {formatDate(
                            record.booking?.start_at ?? record.created_at,
                          )}
                        </Link>
                      </TableCell>
                      <TableCell className="py-3 text-sm">
                        {record.anamnesis ? (
                          <span className="line-clamp-2">
                            {record.anamnesis}
                          </span>
                        ) : (
                          <span className="text-muted-foreground/60">
                            {DASH}
                          </span>
                        )}
                      </TableCell>
                      <TableCell className="py-3 text-sm">
                        <StackedCell lines={treatmentLines(record)} />
                      </TableCell>
                      <TableCell className="py-3 text-sm">
                        <StackedCell lines={productLines(record)} />
                      </TableCell>
                      {/* Sebelum/sesudah ikut terbaca dari riwayat: itu yang
                          dicari pertama kali di klinik kecantikan, dan
                          fotonya memang sudah ikut termuat bersama barisnya. */}
                      <TableCell className="py-3 text-sm">
                        <PhotoPairPreview
                          photos={record.photos}
                          labels={{
                            before: t("clinic.medical_photo_type.before"),
                            after: t("clinic.medical_photo_type.after"),
                            none: DASH,
                          }}
                        />
                      </TableCell>
                      <TableCell className="py-3 text-sm">
                        {record.author_name || performers || (
                          <span className="text-muted-foreground/60">
                            {DASH}
                          </span>
                        )}
                      </TableCell>
                    </TableRow>
                  )
                })}
              </TableBody>
            </Table>
          </Card>

          {/* Tombolnya hanya muncul saat memang masih ada yang tersisa —
              tombol mati di ujung riwayat cuma bikin orang menebak apakah
              catatannya habis atau gagal dimuat. */}
          {hasNextPage ? (
            <div className="flex justify-center pt-3">
              <Button
                variant="outline"
                onClick={onLoadMore}
                disabled={isFetchingNextPage}
              >
                {isFetchingNextPage
                  ? t("general.loading")
                  : t("medical_record.load_older")}
              </Button>
            </div>
          ) : null}
        </>
      )}
    </>
  )
}

/**
 * Satu butir per baris, dengan harganya sebagai elemen tersendiri.
 *
 * Jumlah dan harga ditaruh dalam satu potongan yang tidak boleh dipenggal,
 * terpisah dari nama produknya. Kalau ketiganya jadi satu teks, nama yang
 * panjang mendorong "×2" ke baris berikutnya dan angka itu terbaca seperti
 * bagian dari harga.
 */
function StackedCell({ lines }: { lines: CellLine[] }) {
  if (lines.length === 0) {
    return <span className="text-muted-foreground/60">{DASH}</span>
  }

  return (
    <div className="space-y-1">
      {lines.map((line, index) => (
        <div key={index}>
          <span className="text-pretty">{line.label}</span>
          {line.amount !== undefined ? (
            <span className="ml-1.5 text-xs whitespace-nowrap text-muted-foreground tabular-nums">
              {line.qty ? `×${line.qty} · ` : null}
              {formatCurrency(line.amount)}
            </span>
          ) : null}
        </div>
      ))}
    </div>
  )
}

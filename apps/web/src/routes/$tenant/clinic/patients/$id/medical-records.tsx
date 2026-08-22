import { createFileRoute, Link, useParams } from "@tanstack/react-router"
import { useInfiniteQuery, useQuery } from "@tanstack/react-query"
import { Plus } from "lucide-react"

import { Button } from "#/components/ui/button.tsx"
import { Card, CardContent } from "#/components/ui/card.tsx"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "#/components/ui/table.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { useBreadcrumbTail } from "#/components/breadcrumb-tail.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { EmptyState } from "#/components/ui/empty-state.tsx"
import { apiGet } from "#/lib/api.ts"
import { formatCurrency, formatDate } from "#/lib/format.ts"
import { PhotoPairPreview } from "#/components/medical-photos/photo-pair-preview.tsx"
import type { PhotoRow } from "#/components/medical-photos/photo-types.ts"
import type { TreatmentRow } from "../../medical-records/components/medical-record-attachments.tsx"

export const Route = createFileRoute(
  "/$tenant/clinic/patients/$id/medical-records",
)({
  component: PatientMedicalRecordsPage,
})

interface TransactionItemRow {
  name: string
  kind: string
  unit_price: number
  qty: number
  subtotal: number
}

interface RecordRow {
  id: number
  created_at?: string | null
  booking?: { id: number; status?: string; start_at?: string | null } | null
  patient_name?: string | null
  author_name?: string | null
  anamnesis?: string | null
  treatments?: TreatmentRow[]
  photos?: PhotoRow[]
  transaction?: {
    id: number
    performers: { name: string }[]
    items: TransactionItemRow[]
  } | null
}

interface PatientRow {
  id: number
  name: string
  address?: string | null
  birth_date?: string | null
  whatsapp?: string | null
}

interface RecordsResponse {
  data: RecordRow[]
  meta: { current_page: number; last_page: number; total: number }
}

const DASH = "—"

/**
 * Usia dalam tahun dan bulan, bukan tahun saja.
 *
 * Di klinik kulit, selisih beberapa bulan masih terbaca pada pasien remaja
 * dan pasca-perawatan, jadi pembulatan ke tahun menghilangkan yang justru
 * dilihat dokter.
 */
function formatAge(birthDate?: string | null): string {
  if (!birthDate) return DASH

  const born = new Date(birthDate)
  if (Number.isNaN(born.getTime())) return DASH

  const now = new Date()
  let months =
    (now.getFullYear() - born.getFullYear()) * 12 +
    (now.getMonth() - born.getMonth())

  if (now.getDate() < born.getDate()) months -= 1
  if (months < 0) return DASH

  const years = Math.floor(months / 12)
  const rest = months % 12

  return years === 0 ? `${rest} bl` : `${years} th ${rest} bl`
}

interface CellLine {
  label: string
  amount?: number
  /** Hanya diisi bila lebih dari satu; menempel ke harganya, bukan ke nama. */
  qty?: number
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

function IdentityItem({ label, value }: { label: string; value: string }) {
  return (
    <div className="min-w-0 space-y-0.5">
      <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
        {label}
      </p>
      <p className="truncate text-sm">{value}</p>
    </div>
  )
}

/**
 * Tindakan pada satu kunjungan datang dari dua tempat: catatan klinis yang
 * ditulis dokter, dan baris layanan di nota. Dokter membaca satu kunjungan
 * sebagai satu peristiwa, jadi keduanya digabung jadi satu daftar.
 *
 * Yang bernama sama disatukan, bukan dibuang salah satunya: nyaris semua
 * tindakan tercatat di dua tempat dengan nama yang sama, dan membuang baris
 * notanya berarti kolom ini tidak pernah menampilkan harga sama sekali —
 * padahal harga itulah yang dibaca dari laporan klinik.
 */
function treatmentLines(record: RecordRow): CellLine[] {
  const billed = (record.transaction?.items ?? []).filter(
    (item) => item.kind === "service",
  )
  const used = new Set<string>()

  const clinical = (record.treatments ?? [])
    .map((treatment) => treatment.service_name)
    .filter((name): name is string => Boolean(name))
    .map((name): CellLine => {
      const match = billed.find(
        (item) => item.name === name && !used.has(item.name),
      )

      if (!match) return { label: name }

      used.add(match.name)

      return { label: name, amount: match.subtotal }
    })

  const rest = billed
    .filter((item) => !used.has(item.name))
    .map((item): CellLine => ({ label: item.name, amount: item.subtotal }))

  return [...clinical, ...rest]
}

function productLines(record: RecordRow): CellLine[] {
  return (record.transaction?.items ?? [])
    .filter((item) => item.kind === "product")
    .map((item): CellLine => ({
      label: item.name,
      amount: item.subtotal,
      qty: item.qty > 1 ? item.qty : undefined,
    }))
}

function PatientMedicalRecordsPage() {
  const { tenant, id } = useParams({
    from: "/$tenant/clinic/patients/$id/medical-records",
  })
  const { t } = useTrans()

  const patient = useQuery({
    queryKey: ["patients", tenant, id],
    queryFn: () =>
      apiGet<{ data: PatientRow }>(`/${tenant}/clinic/patients/${id}`),
  })

  // Riwayat panjang datang bertahap: server memotongnya per 20 catatan
  // supaya foto dan tindakan tidak ikut terangkut sekaligus. Urutannya tetap
  // dari kunjungan terlama, jadi halaman berikutnya cukup disambung di
  // belakang tanpa perlu diurut ulang.
  const { data, isLoading, fetchNextPage, hasNextPage, isFetchingNextPage } =
    useInfiniteQuery({
      queryKey: ["patient-medical-records", tenant, id],
      initialPageParam: 1,
      queryFn: ({ pageParam }) =>
        apiGet<RecordsResponse>(
          `/${tenant}/clinic/patients/${id}/medical-records`,
          { page: pageParam },
        ),
      getNextPageParam: (last) =>
        last.meta.current_page < last.meta.last_page
          ? last.meta.current_page + 1
          : undefined,
    })

  const records = data?.pages.flatMap((page) => page.data) ?? []
  const profile = patient.data?.data
  const patientName =
    profile?.name ?? records[0]?.patient_name ?? t("patient.title")
  useBreadcrumbTail(patientName)

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <h1 className="text-xl font-semibold tracking-tight">
            {t("medical_record.history")}
          </h1>
          <p className="text-sm text-muted-foreground">{patientName}</p>
        </div>
        <Button asChild size="sm" className="gap-1.5">
          <Link
            to="/$tenant/clinic/medical-records/new"
            params={{ tenant }}
            search={{ patient: id }}
          >
            <Plus className="size-4" />
            {t("medical_record.add_visit")}
          </Link>
        </Button>
      </div>

      <Card className="py-0">
        <CardContent className="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
          {patient.isLoading ? (
            <>
              <Skeleton className="h-9" />
              <Skeleton className="h-9" />
              <Skeleton className="h-9" />
              <Skeleton className="h-9" />
            </>
          ) : (
            <>
              <IdentityItem label={t("patient.name")} value={patientName} />
              <IdentityItem
                label={t("patient.address")}
                value={profile?.address || DASH}
              />
              <IdentityItem
                label={t("medical_record.age")}
                value={formatAge(profile?.birth_date)}
              />
              <IdentityItem
                label={t("patient.whatsapp")}
                value={profile?.whatsapp || DASH}
              />
            </>
          )}
        </CardContent>
      </Card>

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
                  <TableHead className="w-28 text-[11px] font-medium tracking-wide uppercase">
                    {t("medical_record.date")}
                  </TableHead>
                  <TableHead className="w-[24%] text-[11px] font-medium tracking-wide uppercase">
                    {t("medical_record.complaint")}
                  </TableHead>
                  <TableHead className="w-[24%] text-[11px] font-medium tracking-wide uppercase">
                    {t("medical_record.action")}
                  </TableHead>
                  <TableHead className="w-[26%] text-[11px] font-medium tracking-wide uppercase">
                    {t("medical_record.obt_hcp")}
                  </TableHead>
                  <TableHead className="w-24 text-[11px] font-medium tracking-wide uppercase">
                    {t("medical_record.photos")}
                  </TableHead>
                  <TableHead className="w-44 text-[11px] font-medium tracking-wide uppercase">
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
            <div className="flex justify-center pt-1">
              <Button
                variant="outline"
                onClick={() => fetchNextPage()}
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
    </div>
  )
}

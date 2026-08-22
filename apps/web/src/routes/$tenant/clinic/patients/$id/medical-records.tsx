import { createFileRoute, Link, useParams } from "@tanstack/react-router"
import { useMemo, useState } from "react"
import { useInfiniteQuery, useQuery } from "@tanstack/react-query"
import { Plus } from "lucide-react"

import { Button } from "#/components/ui/button.tsx"
import { Card, CardContent } from "#/components/ui/card.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "#/components/ui/tabs.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useBreadcrumbTail } from "#/components/breadcrumb-tail.tsx"
import { useDigitShortcut } from "#/hooks/use-go-to-shortcut.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import { PatientClinicalSummary } from "./components/patient-clinical-summary.tsx"
import {
  PurchaseHistory,
  usePatientPurchases,
} from "./components/purchase-history.tsx"
import { DASH } from "./components/record-types.ts"
import type { RecordRow } from "./components/record-types.ts"
import { VisitsTable } from "./components/visits-table.tsx"

export const Route = createFileRoute(
  "/$tenant/clinic/patients/$id/medical-records",
)({
  component: PatientMedicalRecordsPage,
})

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

type HistoryTab = "visits" | "purchases"

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

  return rest === 0 ? `${years} th` : `${years} th ${rest} bl`
}

function IdentityItem({ label, value }: { label: string; value: string }) {
  return (
    <div className="min-w-0 space-y-0.5">
      <p className="text-2xs font-medium tracking-wide text-muted-foreground uppercase">
        {label}
      </p>
      <p className="truncate text-sm">{value}</p>
    </div>
  )
}

function PatientMedicalRecordsPage() {
  const { tenant, id } = useParams({
    from: "/$tenant/clinic/patients/$id/medical-records",
  })
  const { t } = useTrans()
  const [tab, setTab] = useState<HistoryTab>("visits")

  useDigitShortcut("1", () => setTab("visits"))
  useDigitShortcut("2", () => setTab("purchases"))

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

  const purchases = usePatientPurchases(tenant, id)

  const records = data?.pages.flatMap((page) => page.data) ?? []
  const purchaseRows = purchases.data?.pages.flatMap((page) => page.data) ?? []

  // Ringkasan produk dihitung dari riwayat pembelian, bukan dari baris
  // kunjungan: pembelian yang tidak berpapasan dengan kunjungan mana pun
  // tetap terhitung sebagai yang dipakai pasien di rumah.
  const productTotals = useMemo(() => {
    const items = purchaseRows.flatMap((row) => row.items)

    return {
      spend: items.reduce((sum, item) => sum + item.subtotal, 0),
      count: items.reduce((sum, item) => sum + item.qty, 0),
    }
  }, [purchaseRows])

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
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          {[0, 1, 2, 3].map((i) => (
            <Skeleton key={i} className="h-24 rounded-lg" />
          ))}
        </div>
      ) : (
        <PatientClinicalSummary
          records={records}
          productSpend={productTotals.spend}
          productCount={productTotals.count}
        />
      )}

      <Tabs value={tab} onValueChange={(v) => setTab(v as HistoryTab)}>
        <TabsList>
          <HistoryTabTrigger
            value="visits"
            label={t("medical_record.tab_visits")}
            hint="1"
            count={records.length}
          />
          <HistoryTabTrigger
            value="purchases"
            label={t("medical_record.tab_purchases")}
            hint="2"
            count={purchaseRows.length}
          />
        </TabsList>

        <TabsContent value="visits" className="mt-4">
          <VisitsTable
            tenant={tenant}
            records={records}
            isLoading={isLoading}
            hasNextPage={!!hasNextPage}
            isFetchingNextPage={isFetchingNextPage}
            onLoadMore={() => fetchNextPage()}
          />
        </TabsContent>

        <TabsContent value="purchases" className="mt-4">
          <PurchaseHistory
            rows={purchaseRows}
            isLoading={purchases.isLoading}
            hasNextPage={!!purchases.hasNextPage}
            isFetchingNextPage={purchases.isFetchingNextPage}
            onLoadMore={() => purchases.fetchNextPage()}
          />
        </TabsContent>
      </Tabs>
    </div>
  )
}

function HistoryTabTrigger({
  value,
  label,
  hint,
  count,
}: {
  value: HistoryTab
  label: string
  hint: string
  count: number
}) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <TabsTrigger value={value} className="gap-1.5">
          {label}
          {count > 0 ? (
            <span className="text-xs tabular-nums text-muted-foreground">
              {count}
            </span>
          ) : null}
        </TabsTrigger>
      </TooltipTrigger>
      <TooltipContent className="flex items-center gap-2">
        {label}
        <Kbd>{hint}</Kbd>
      </TooltipContent>
    </Tooltip>
  )
}

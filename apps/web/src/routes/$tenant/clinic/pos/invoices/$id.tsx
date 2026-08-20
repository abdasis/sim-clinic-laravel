import {
  createFileRoute,
  Link,
  useNavigate,
  useParams,
} from "@tanstack/react-router"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { useEffect, useMemo, useRef } from "react"
import { toast } from "sonner"
import { HugeiconsIcon } from "@hugeicons/react"
import { PrinterIcon, ShoppingCart01Icon } from "@hugeicons/core-free-icons"

import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import { EmptyState } from "#/components/ui/empty-state.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPost } from "#/lib/api.ts"
import { formatDateTime } from "#/lib/format.ts"
import {
  Receipt,
  type ReceiptClinic,
  type ReceiptData,
} from "../components/receipt.tsx"

export const Route = createFileRoute("/$tenant/clinic/pos/invoices/$id")({
  /**
   * `autoprint` dipasang kasir POS saat transaksinya baru saja tersimpan.
   * Sekali pakai: penandanya dibuang begitu dialog cetak terbuka, supaya
   * memuat ulang halaman atau menekan tombol kembali tidak mencetak lagi
   * dan tidak menambah nomor cetakan.
   */
  validateSearch: (search: Record<string, unknown>): { autoprint?: boolean } =>
    search.autoprint === true || search.autoprint === "true"
      ? { autoprint: true }
      : {},
  component: InvoicePage,
})

function InvoicePage() {
  const { tenant, id } = useParams({ from: "/$tenant/clinic/pos/invoices/$id" })
  const { autoprint } = Route.useSearch()
  const navigate = useNavigate()
  const { t } = useTrans()
  const qc = useQueryClient()
  const queryKey = ["transactions", tenant, id]

  const { data, isLoading, isError } = useQuery({
    queryKey,
    queryFn: () =>
      apiGet<{ data: ReceiptData; meta: { clinic?: ReceiptClinic | null } }>(
        `/${tenant}/clinic/transactions/${id}`,
      ),
  })

  const invoice = data?.data

  // Dikunci sekali agar waktu cetak tidak bergeser tiap render — nota yang
  // jamnya berubah sendiri saat dipandangi bukan dokumen yang meyakinkan.
  const printedAt = useMemo(() => formatDateTime(new Date().toISOString()), [])

  // Cetakan dicatat lebih dulu supaya nomor yang tertera di kertas sama dengan
  // yang tersimpan; gagal mencatat tidak boleh menahan kasir mencetak.
  const print = useMutation({
    mutationFn: () =>
      apiPost<{ data: { print_count: number } }>(
        `/${tenant}/clinic/transactions/${id}/invoice/print`,
      ),
    onSuccess: (res) => {
      qc.setQueryData<{ data: ReceiptData; meta: { clinic?: ReceiptClinic | null } }>(
        queryKey,
        (previous) =>
          previous
            ? {
                ...previous,
                data: { ...previous.data, print_count: res.data.print_count },
              }
            : previous,
      )
      // Tunggu satu frame supaya nomor cetakan yang baru ikut terbawa ke kertas.
      requestAnimationFrame(() => window.print())
    },
    onError: () => {
      toast.error(t("invoice.print_not_recorded"))
      window.print()
    },
  })

  // Nota yang baru saja tersimpan langsung membuka dialog cetak. Kasir yang
  // menutup transaksi memang sedang menunggu kertas keluar, bukan sedang
  // memilih-milih menu — jadi langkah itu dihilangkan, bukan disediakan.
  //
  // Ref-nya, bukan state: perubahan state memicu render, dan render ulang di
  // tengah effect yang sama akan menembakkan cetakan kedua.
  const autoPrinted = useRef(false)

  useEffect(() => {
    if (!autoprint || autoPrinted.current || !invoice || print.isPending) {
      return
    }

    autoPrinted.current = true

    // Penandanya dibuang lebih dulu supaya riwayat peramban tidak menyimpan
    // URL yang mencetak sendiri setiap kali dibuka lagi.
    navigate({
      to: "/$tenant/clinic/pos/invoices/$id",
      params: { tenant, id },
      search: {},
      replace: true,
    })
    print.mutate()
  }, [autoprint, id, invoice, navigate, print, tenant])

  // Pintasan huruf tunggal, sepola dengan `s` untuk simpan di halaman lain.
  // Sengaja bukan Mod+P: kombinasi itu milik peramban, dan cetakan lewat
  // jalur peramban tidak melewati pencatatan nomor cetakan — kertas dan
  // catatan jadi berbeda tanpa ada yang tahu.
  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if (event.metaKey || event.ctrlKey || event.altKey) return

      const target = event.target as HTMLElement | null
      if (target?.isContentEditable) return
      if (target && /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName)) return

      if (event.key === "p" && invoice && !print.isPending) {
        event.preventDefault()
        print.mutate()
      }

      if (event.key === "b") {
        event.preventDefault()
        navigate({ to: "/$tenant/clinic/pos", params: { tenant } })
      }
    }

    window.addEventListener("keydown", onKey)

    return () => window.removeEventListener("keydown", onKey)
  }, [invoice, navigate, print, tenant])

  return (
    // Jarak halaman dilepas saat cetak supaya kertasnya tidak dapat margin dobel.
    <div className="h-full overflow-y-auto bg-muted/30 p-4 print:h-auto print:overflow-visible print:bg-white print:p-0">
      <div className="mx-auto max-w-[420px] print:hidden">
        <ClinicBreadcrumb
          items={[
            { label: t("clinic.clinic"), to: "/$tenant/clinic", params: { tenant } },
            { label: t("pos.title"), to: "/$tenant/clinic/pos", params: { tenant } },
            {
              label: t("pos.transactions"),
              to: "/$tenant/clinic/pos/transactions",
              params: { tenant },
            },
            { label: invoice?.invoice_number ?? t("invoice.title") },
          ]}
        />

        <div className="mt-3 mb-4 flex items-center justify-between gap-2">
          <h1 className="text-base font-semibold tracking-tight">
            {t("invoice.title")}
          </h1>

          <div className="flex items-center gap-2">
            {/* Jalan pulang. Tanpa ini kasir yang dibawa ke sini otomatis
                harus menekan tombol kembali peramban untuk melayani antrean
                berikutnya. Sekunder, karena yang dituju halaman ini tetap
                kertasnya. */}
            <Tooltip>
              <TooltipTrigger asChild>
                <Button asChild variant="outline" size="sm" className="gap-2">
                  <Link to="/$tenant/clinic/pos" params={{ tenant }}>
                    <HugeiconsIcon
                      icon={ShoppingCart01Icon}
                      strokeWidth={2}
                      className="size-4"
                    />
                    {t("pos.new_transaction")}
                  </Link>
                </Button>
              </TooltipTrigger>
              <TooltipContent className="flex items-center gap-2">
                {t("pos.new_transaction")}
                <Kbd>b</Kbd>
              </TooltipContent>
            </Tooltip>

            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  size="sm"
                  className="gap-2 transition-transform duration-150 ease-out hover:-translate-y-px"
                  disabled={!invoice || print.isPending}
                  onClick={() => print.mutate()}
                >
                  <HugeiconsIcon
                    icon={PrinterIcon}
                    strokeWidth={2}
                    className="size-4"
                  />
                  {print.isPending ? t("general.loading") : t("invoice.print")}
                </Button>
              </TooltipTrigger>
              <TooltipContent className="flex items-center gap-2">
                {t("invoice.print")}
                <Kbd>p</Kbd>
              </TooltipContent>
            </Tooltip>
          </div>
        </div>
      </div>

      {isLoading ? (
        <div className="mx-auto w-full max-w-[420px] space-y-3 rounded-lg bg-white p-6 ring-1 ring-neutral-200/80">
          <Skeleton className="mx-auto h-6 w-40" />
          <Skeleton className="mx-auto h-3 w-56" />
          <Skeleton className="mt-6 h-24 w-full" />
          <Skeleton className="h-24 w-full" />
        </div>
      ) : isError || !invoice ? (
        <EmptyState
          className="py-16"
          illustration="default"
          title={t("general.no_data")}
          description={t("pos.empty_desc")}
        />
      ) : (
        // Bayangan dan sudutnya milik pratinjau, bukan kertas — semuanya
        // menempel di bingkai supaya nota tetap polos saat dicetak.
        <div
          data-receipt-frame
          className="mx-auto w-fit bg-white shadow-sm ring-1 ring-neutral-200/70 print:shadow-none print:ring-0"
        >
          <Receipt
            data={invoice}
            clinic={data?.meta?.clinic}
            printedAt={printedAt}
          />
        </div>
      )}
    </div>
  )
}

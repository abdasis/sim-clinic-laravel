import { createFileRoute, useParams } from "@tanstack/react-router"
import { useQuery } from "@tanstack/react-query"
import { useMemo } from "react"
import { HugeiconsIcon } from "@hugeicons/react"
import { PrinterIcon } from "@hugeicons/core-free-icons"

import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { Button } from "#/components/ui/button.tsx"
import { EmptyState } from "#/components/ui/empty-state.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import { formatDateTime } from "#/lib/format.ts"
import {
  Receipt,
  type ReceiptClinic,
  type ReceiptData,
} from "../components/receipt.tsx"

export const Route = createFileRoute("/$tenant/clinic/pos/invoices/$id")({
  component: InvoicePage,
})

function InvoicePage() {
  const { tenant, id } = useParams({ from: "/$tenant/clinic/pos/invoices/$id" })
  const { t } = useTrans()

  const { data, isLoading, isError } = useQuery({
    queryKey: ["transactions", tenant, id],
    queryFn: () =>
      apiGet<{ data: ReceiptData; meta: { clinic?: ReceiptClinic | null } }>(
        `/${tenant}/clinic/transactions/${id}`,
      ),
  })

  const invoice = data?.data

  // Dikunci sekali agar waktu cetak tidak bergeser tiap render — nota yang
  // jamnya berubah sendiri saat dipandangi bukan dokumen yang meyakinkan.
  const printedAt = useMemo(() => formatDateTime(new Date().toISOString()), [])

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
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                size="sm"
                className="gap-2 transition-transform duration-150 ease-out hover:-translate-y-px"
                disabled={!invoice}
                onClick={() => window.print()}
              >
                <HugeiconsIcon
                  icon={PrinterIcon}
                  strokeWidth={2}
                  className="size-4"
                />
                {t("invoice.print")}
              </Button>
            </TooltipTrigger>
            <TooltipContent>{t("invoice.print")}</TooltipContent>
          </Tooltip>
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

import { createFileRoute, Link, useParams } from "@tanstack/react-router"
import { useCallback, useState } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { Card, CardContent, CardHeader, CardTitle } from "#/components/ui/card.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Label } from "#/components/ui/label.tsx"
import {
  NativeSelect,
  NativeSelectOption,
} from "#/components/ui/native-select.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { TransactionItemList, type LineItem } from "./components/transaction-item-list.tsx"
import { PaymentPanel, type PaymentData } from "./components/payment-panel.tsx"

export const Route = createFileRoute("/$tenant/clinic/pos/")({
  component: PosPage,
})

interface PatientRow {
  id: number
  name: string
}

interface CreatedTransaction {
  data: { id: number; invoice_number: string }
}

function PosPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/pos/" })
  const { t } = useTrans()
  const qc = useQueryClient()

  const [patientId, setPatientId] = useState<string>("")
  const [items, setItems] = useState<LineItem[]>([])
  const [total, setTotal] = useState(0)
  const [payment, setPayment] = useState<PaymentData>({
    method: "cash",
    amount: 0,
    covers: false,
  })
  const [created, setCreated] = useState<CreatedTransaction["data"] | null>(null)

  const patients = useQuery({
    queryKey: ["patients", tenant, "options"],
    queryFn: () =>
      apiGet<{ data: PatientRow[] }>(`/${tenant}/clinic/patients`, { per_page: 100 }),
  })

  const handleItems = useCallback((rows: LineItem[], sum: number) => {
    setItems(rows)
    setTotal(sum)
  }, [])
  const handlePayment = useCallback((p: PaymentData) => setPayment(p), [])

  const validItems = items.filter((i) => i.refId > 0)
  const hasStockIssue = validItems.some((i) => i.stock !== null && i.qty > i.stock)
  const canSubmit = validItems.length > 0 && !hasStockIssue

  const mutation = useMutation({
    mutationFn: async () => {
      const res = await apiPost<CreatedTransaction>(`/${tenant}/clinic/transactions`, {
        patient_id: patientId ? Number(patientId) : null,
        booking_id: null,
        items: validItems.map((i) =>
          i.kind === "product"
            ? { product_id: i.refId, qty: i.qty }
            : { service_id: i.refId, qty: i.qty },
        ),
      })
      if (payment.amount > 0) {
        await apiPost(`/${tenant}/clinic/transactions/${res.data.id}/payments`, {
          method: payment.method,
          amount: payment.amount,
          paid_at: new Date().toISOString(),
        }).catch(() => undefined)
      }
      return res
    },
    onSuccess: (res) => {
      toast.success(t("pos.created"))
      setCreated(res.data)
      qc.invalidateQueries({ queryKey: ["transactions"] })
      setItems([])
      setTotal(0)
      setPatientId("")
    },
    onError: (err: ApiError) => {
      toast.error(err.message)
    },
  })

  return (
    <div>
      <ClinicBreadcrumb
        items={[
          { label: tenant, to: "/$tenant/clinic/services", params: { tenant } },
          { label: t("clinic.clinic") },
          { label: t("pos.title") },
        ]}
      />
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-xl font-semibold">{t("pos.title")}</h1>
        <Button asChild variant="outline">
          <Link to="/$tenant/clinic/pos/transactions" params={{ tenant }}>
            {t("pos.transactions")}
          </Link>
        </Button>
      </div>

      {created ? (
        <div className="mb-4 rounded-md border border-primary/40 bg-primary/5 p-4 text-sm">
          <span className="font-medium">{created.invoice_number}</span> —{" "}
          <Link
            to="/$tenant/clinic/pos/invoices/$id"
            params={{ tenant, id: String(created.id) }}
            className="text-primary underline underline-offset-4"
          >
            {t("invoice.title")}
          </Link>
        </div>
      ) : null}

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle className="text-base">{t("pos.add_transaction")}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-5">
            <div className="space-y-1.5">
              <Label>{t("pos.patient")}</Label>
              <NativeSelect
                className="w-full"
                value={patientId}
                onChange={(e) => setPatientId(e.target.value)}
              >
                <NativeSelectOption value="">{t("pos.patient")}</NativeSelectOption>
                {patients.data?.data.map((p) => (
                  <NativeSelectOption key={p.id} value={String(p.id)}>
                    {p.name}
                  </NativeSelectOption>
                ))}
              </NativeSelect>
            </div>
            <TransactionItemList tenant={tenant} onChange={handleItems} />
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t("pos.payment")}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <PaymentPanel total={total} onChange={handlePayment} />
            <Button
              className="w-full"
              disabled={!canSubmit || mutation.isPending}
              onClick={() => mutation.mutate()}
            >
              {t("general.save")}
            </Button>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}

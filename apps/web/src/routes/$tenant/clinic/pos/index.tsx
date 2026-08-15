import { createFileRoute, Link, useParams } from "@tanstack/react-router"
import { z } from "zod"
import { useCallback, useRef, useState } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { HugeiconsIcon } from "@hugeicons/react"
import { KeyboardIcon } from "@hugeicons/core-free-icons"

import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Form } from "#/components/ui/form.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import { ScrollArea } from "#/components/ui/scroll-area.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { FormCombobox } from "#/components/forms/form-combobox.tsx"
import { useForm } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { PaymentPanel, type PaymentData } from "./components/payment-panel.tsx"
import { PosCart } from "./components/pos-cart.tsx"
import { PosShortcutHelp } from "./components/pos-shortcut-help.tsx"
import { ProductCatalog } from "./components/product-catalog.tsx"
import { usePosCart } from "./hooks/use-pos-cart.ts"
import { usePosShortcuts } from "./hooks/use-pos-shortcuts.ts"

const patientSchema = z.object({
  patient_id: z.string().min(1),
})

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

  const searchRef = useRef<HTMLInputElement>(null)
  const [helpOpen, setHelpOpen] = useState(false)
  const [payment, setPayment] = useState<PaymentData>({
    method: "cash",
    amount: 0,
    covers: false,
  })
  const [created, setCreated] = useState<CreatedTransaction["data"] | null>(null)

  const cart = usePosCart()

  // Pasien wajib diisi; validasinya ikut skema supaya pesannya konsisten
  // dengan form lain, bukan alert manual.
  const patientForm = useForm(patientSchema, {
    defaultValues: { patient_id: "" },
  })
  const patientId = patientForm.watch("patient_id")

  const patients = useQuery({
    queryKey: ["patients", tenant, "options"],
    queryFn: () =>
      apiGet<{ data: PatientRow[] }>(`/${tenant}/clinic/patients`, {
        per_page: 100,
      }),
  })

  const handlePayment = useCallback((next: PaymentData) => setPayment(next), [])

  const canSubmit = !cart.isEmpty && !cart.hasStockIssue

  const mutation = useMutation({
    mutationFn: async () => {
      const res = await apiPost<CreatedTransaction>(
        `/${tenant}/clinic/transactions`,
        {
          patient_id: patientId ? Number(patientId) : null,
          booking_id: null,
          items: cart.items.map((item) =>
            item.kind === "product"
              ? { product_id: item.refId, qty: item.qty }
              : { service_id: item.refId, qty: item.qty },
          ),
        },
      )

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
      // Katalog ikut disegarkan supaya saldo stoknya tidak basi setelah jualan.
      qc.invalidateQueries({ queryKey: ["products", tenant, "catalog"] })
      cart.clear()
      patientForm.reset({ patient_id: "" })
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  usePosShortcuts({
    focusSearch: () => searchRef.current?.focus(),
    stepLast: (delta) => {
      const last = cart.items.at(-1)
      if (last) cart.step(last.key, delta)
    },
    clearCart: cart.clear,
    save: () => {
      if (canSubmit && !mutation.isPending) mutation.mutate()
    },
    toggleHelp: () => setHelpOpen((open) => !open),
    canClear: () => !cart.isEmpty,
  })

  return (
    <div className="flex h-full min-h-0">
      <ProductCatalog tenant={tenant} onAdd={cart.add} searchRef={searchRef} />

      <aside className="flex w-[380px] shrink-0 flex-col overflow-hidden bg-background">
        <ScrollArea className="min-h-0 flex-1">
          <div className="space-y-4 p-4">
            <div className="flex items-start justify-between gap-2">
              <ClinicBreadcrumb
                items={[
                  { label: t("clinic.clinic"), to: "/$tenant/clinic", params: { tenant } },
                  { label: t("pos.title"), to: "/$tenant/clinic/pos", params: { tenant } },
                  { label: t("pos.add_transaction") },
                ]}
              />
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-7 shrink-0 text-muted-foreground"
                    aria-label={t("pos.shortcut.open")}
                    onClick={() => setHelpOpen(true)}
                  >
                    <HugeiconsIcon
                      icon={KeyboardIcon}
                      strokeWidth={2}
                      className="size-4"
                    />
                  </Button>
                </TooltipTrigger>
                <TooltipContent className="flex items-center gap-2">
                  {t("pos.shortcut.open")}
                  <Kbd>?</Kbd>
                </TooltipContent>
              </Tooltip>
            </div>

            {created ? (
              <div className="rounded-md border border-primary/40 bg-primary/5 p-3 text-sm">
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

            <Form {...patientForm}>
              <FormCombobox
                control={patientForm.control}
                name="patient_id"
                label={t("pos.patient")}
                placeholder={t("general.search")}
                emptyLabel={t("general.no_data")}
                options={(patients.data?.data ?? []).map((patient) => ({
                  label: patient.name,
                  value: String(patient.id),
                }))}
              />
            </Form>

            <PosCart
              items={cart.items}
              total={cart.total}
              onStep={cart.step}
              onRemove={cart.remove}
              onClear={cart.clear}
            />

            <PaymentPanel total={cart.total} onChange={handlePayment} />
          </div>
        </ScrollArea>

        {/* Tombol simpan menempel di bawah supaya selalu terjangkau walau
            keranjangnya panjang. */}
        <div className="shrink-0 border-t border-border/50 p-4">
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                className="w-full transition-transform duration-150 ease-out hover:-translate-y-px"
                disabled={!canSubmit || mutation.isPending}
                onClick={() => mutation.mutate()}
              >
                {t("general.save")}
              </Button>
            </TooltipTrigger>
            <TooltipContent className="flex items-center gap-2">
              {t("pos.shortcut.save")}
              <span className="flex items-center gap-0.5">
                <Kbd>Mod</Kbd>
                <Kbd>Enter</Kbd>
              </span>
            </TooltipContent>
          </Tooltip>
        </div>
      </aside>

      <PosShortcutHelp open={helpOpen} onOpenChange={setHelpOpen} />
    </div>
  )
}

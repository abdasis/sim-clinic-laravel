import { createFileRoute, useParams } from "@tanstack/react-router"
import { useCallback, useEffect, useRef, useState } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { HugeiconsIcon } from "@hugeicons/react"
import { KeyboardIcon, ShoppingCart01Icon } from "@hugeicons/core-free-icons"

import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { Button } from "#/components/ui/button.tsx"
import {
  Drawer,
  DrawerContent,
  DrawerFooter,
  DrawerHeader,
  DrawerTitle,
} from "#/components/ui/drawer.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import { ScrollArea } from "#/components/ui/scroll-area.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useIsMobile } from "#/hooks/use-mobile.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { formatCurrency, formatDateTime } from "#/lib/format.ts"
import type { PaymentData } from "./components/payment-panel.tsx"
import {
  PosCheckoutPanel,
  patientSchema,
  type CreatedTransaction,
} from "./components/pos-checkout-panel.tsx"
import { PosShortcutHelp } from "./components/pos-shortcut-help.tsx"
import { ProductCatalog } from "./components/product-catalog.tsx"
import { usePosCart } from "./hooks/use-pos-cart.ts"
import { usePosShortcuts } from "./hooks/use-pos-shortcuts.ts"

/**
 * Panel keranjang lebarnya tetap 380px, jadi dua kolom baru muat kalau
 * katalognya masih kebagian ruang layak — di bawah `lg` katalog tergencet
 * sampai nol dan produknya seolah hilang, karena itu panelnya pindah ke drawer.
 */
const SPLIT_BREAKPOINT = 1024

export const Route = createFileRoute("/$tenant/clinic/pos/")({
  component: PosPage,
})

interface PatientRow {
  id: number
  name: string
}

interface BookingRow {
  id: number
  service_name?: string | null
  start_at?: string | null
}

function PosPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/pos/" })
  const { t } = useTrans()
  const qc = useQueryClient()
  const isNarrow = useIsMobile(SPLIT_BREAKPOINT)

  const searchRef = useRef<HTMLInputElement>(null)
  const patientFieldRef = useRef<HTMLDivElement>(null)
  const [helpOpen, setHelpOpen] = useState(false)
  const [cartOpen, setCartOpen] = useState(false)
  // Elemen drawer disimpan sebagai state, bukan ref: daftar pasien perlu
  // di-portal ke dalamnya dan wadah itu baru ada setelah drawer terpasang.
  const [drawerEl, setDrawerEl] = useState<HTMLDivElement | null>(null)
  const [payment, setPayment] = useState<PaymentData>({
    method: "cash",
    amount: 0,
    covers: false,
  })
  const [created, setCreated] = useState<CreatedTransaction | null>(null)

  const cart = usePosCart()

  // Pasien wajib diisi; validasinya ikut skema supaya pesannya konsisten
  // dengan form lain, bukan alert manual.
  const patientForm = useForm(patientSchema, {
    defaultValues: { patient_id: "", booking_id: "" },
  })
  const patientId = patientForm.watch("patient_id")
  const bookingId = patientForm.watch("booking_id")
  // Pelaksana kunjungan; di luar form karena bukan satu nilai teks dan
  // tidak punya aturan validasi sendiri.
  const [performerIds, setPerformerIds] = useState<number[]>([])

  // Hanya peran yang mengerjakan tindakan yang boleh jadi penerima fee.
  const staff = useQuery({
    queryKey: ["staff", tenant, "therapists"],
    queryFn: () =>
      apiGet<{ data: { id: number; name: string; clinic_role: string }[] }>(
        `/${tenant}/clinic/staff`,
        { per_page: 100 },
      ),
  })

  const patients = useQuery({
    queryKey: ["patients", tenant, "options"],
    queryFn: () =>
      apiGet<{ data: PatientRow[] }>(`/${tenant}/clinic/patients`, {
        per_page: 100,
      }),
  })

  // Kunjungan yang bisa ditagih hanya milik pasien yang sedang dilayani, dan
  // hanya yang sudah selesai — server menolak sisanya. Baru diambil setelah
  // pasiennya dipilih supaya tidak menarik daftar yang pasti tidak terpakai.
  const bookings = useQuery({
    enabled: patientId !== "",
    queryKey: ["bookings", tenant, "billable", patientId],
    queryFn: () =>
      apiGet<{ data: BookingRow[] }>(`/${tenant}/clinic/bookings`, {
        per_page: 100,
        filter: { status: "done", patient_id: patientId },
        sort: "start_at",
        direction: "desc",
      }),
  })

  // Kunjungan milik pasien sebelumnya tidak boleh ikut terbawa saat kasir
  // berganti pasien — tagihannya akan menunjuk kunjungan orang lain.
  useEffect(() => {
    patientForm.setValue("booking_id", "")
  }, [patientId, patientForm])

  // Hanya peran yang mengerjakan tindakan yang boleh jadi pelaksana maupun
  // penawar; kasir dan admin tidak masuk hitungan fee.
  const clinicStaff = (staff.data?.data ?? []).filter((member) =>
    ["therapist", "doctor"].includes(member.clinic_role),
  )

  const handlePayment = useCallback((next: PaymentData) => setPayment(next), [])

  const canSubmit = !cart.isEmpty && !cart.hasStockIssue

  const mutation = useMutation({
    mutationFn: async () => {
      const res = await apiPost<{ data: CreatedTransaction }>(
        `/${tenant}/clinic/transactions`,
        {
          patient_id: patientId ? Number(patientId) : null,
          performer_ids: performerIds,
          booking_id: bookingId ? Number(bookingId) : null,
          items: cart.items.map((item) => ({
            ...(item.kind === "product"
              ? { product_id: item.refId }
              : { service_id: item.refId }),
            qty: item.qty,
            offered_by: item.offeredBy,
          })),
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
      setPerformerIds([])
      patientForm.reset({ patient_id: "", booking_id: "" })
    },
    onError: (err: ApiError) => {
      // Pasien wajib diisi di server; tanpa ini tombol simpan terasa mati
      // karena error-nya hanya lewat di toast dan fieldnya tidak ditandai.
      applyServerErrors(patientForm, err.errors)
      toast.error(err.message)

      // Di layar sempit fieldnya ada di dalam drawer — percuma ditandai
      // kalau drawernya sedang tertutup.
      if (isNarrow) setCartOpen(true)

      // Keranjang bisa panjang; tanpa ini tanda merahnya tertinggal di atas
      // layar dan kasir menyangka tombolnya yang tidak berfungsi.
      patientFieldRef.current?.scrollIntoView({
        behavior: "smooth",
        block: "center",
      })
    },
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

  const breadcrumb = (
    <ClinicBreadcrumb
      items={[
        { label: t("clinic.clinic"), to: "/$tenant/clinic", params: { tenant } },
        { label: t("pos.title"), to: "/$tenant/clinic/pos", params: { tenant } },
        { label: t("pos.add_transaction") },
      ]}
    />
  )

  // Hanya satu salinan yang pernah ter-mount: drawer di layar sempit, kolom
  // kanan di layar lebar. Kalau dirender dua-duanya, form pasiennya kembar.
  const checkout = (
    <PosCheckoutPanel
      tenant={tenant}
      form={patientForm}
      patientOptions={(patients.data?.data ?? []).map((patient) => ({
        label: patient.name,
        value: String(patient.id),
      }))}
      staff={clinicStaff}
      staffLoading={staff.isLoading}
      performerIds={performerIds}
      onPerformersChange={setPerformerIds}
      onOfferedBy={cart.setOfferedBy}
      created={created}
      items={cart.items}
      total={cart.total}
      onStep={cart.step}
      onRemove={cart.remove}
      onClear={cart.clear}
      onPaymentChange={handlePayment}
      patientFieldRef={patientFieldRef}
      popupContainer={isNarrow ? drawerEl : undefined}
      bookingOptions={(bookings.data?.data ?? []).map((booking) => ({
        value: String(booking.id),
        label: `${formatDateTime(booking.start_at)} · ${booking.service_name ?? "-"}`,
      }))}
      bookingsLoading={bookings.isLoading}
      bookingsNeedPatient={patientId === ""}
      optionsLoading={patients.isLoading || staff.isLoading}
      optionsError={patients.isError || staff.isError}
    />
  )

  const saveButton = (
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
  )

  return (
    <div className="flex h-full min-h-0 flex-col lg:flex-row">
      {/* Kolom kanan hilang di layar sempit, jadi jejak navigasinya dipindah
          ke bar tipis di atas katalog supaya halaman tetap punya breadcrumb. */}
      <div className="shrink-0 border-b border-border/50 px-3 py-2 lg:hidden">
        {breadcrumb}
      </div>

      <ProductCatalog tenant={tenant} onAdd={cart.add} searchRef={searchRef} />

      <aside className="hidden w-[380px] shrink-0 flex-col overflow-hidden bg-background lg:flex">
        <ScrollArea className="min-h-0 flex-1">
          <div className="space-y-4 p-4">
            <div className="flex items-start justify-between gap-2">
              {breadcrumb}
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

            {isNarrow ? null : checkout}
          </div>
        </ScrollArea>

        {/* Tombol simpan menempel di bawah supaya selalu terjangkau walau
            keranjangnya panjang. */}
        <div className="shrink-0 border-t border-border/50 p-4">{saveButton}</div>
      </aside>

      {/* Ringkasan yang selalu terlihat di layar sempit — kasir tahu isi
          keranjang tanpa harus membukanya dulu. */}
      <div className="flex shrink-0 items-center justify-between gap-3 border-t border-border/50 bg-background p-3 lg:hidden">
        <div className="min-w-0">
          <p className="text-2xs text-muted-foreground">
            {t("pos.cart.items_count").replace(
              ":count",
              String(cart.items.length),
            )}
          </p>
          <p className="truncate text-sm font-semibold tabular-nums">
            {formatCurrency(cart.total)}
          </p>
        </div>
        <Button
          type="button"
          className="shrink-0 gap-2"
          onClick={() => setCartOpen(true)}
        >
          <HugeiconsIcon
            icon={ShoppingCart01Icon}
            strokeWidth={2}
            className="size-4"
          />
          {t("pos.cart.open")}
        </Button>
      </div>

      {isNarrow ? (
        <Drawer open={cartOpen} onOpenChange={setCartOpen}>
          {/* Tingginya dipatok, bukan `max-h`: dengan `h-auto` bawaan drawer,
              `flex-1` di area scroll tidak punya tinggi acuan sehingga isinya
              tidak pernah bisa digulir dan bagian bawah tertimpa tombol. */}
          <DrawerContent ref={setDrawerEl} className="h-[85dvh]">
            <DrawerHeader className="pb-2">
              <DrawerTitle>{t("pos.add_transaction")}</DrawerTitle>
            </DrawerHeader>
            <ScrollArea className="min-h-0 flex-1">
              <div className="px-4 pb-2">{checkout}</div>
            </ScrollArea>
            <DrawerFooter className="border-t border-border/50">
              {saveButton}
            </DrawerFooter>
          </DrawerContent>
        </Drawer>
      ) : null}

      <PosShortcutHelp open={helpOpen} onOpenChange={setHelpOpen} />
    </div>
  )
}

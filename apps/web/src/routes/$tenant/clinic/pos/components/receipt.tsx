import { HugeiconsIcon } from "@hugeicons/react"
import { Call02Icon, Location01Icon } from "@hugeicons/core-free-icons"

import { useTrans } from "#/hooks/use-trans.ts"
import { formatAmount, formatDateTime } from "#/lib/format.ts"

export interface ReceiptItem {
  id: number
  name: string
  kind?: string | null
  unit_price: string
  qty: number
  subtotal: string
}

export interface ReceiptPayment {
  id: number
  method: string
  method_label?: string | null
  amount: string
  paid_at?: string | null
}

export interface ReceiptClinic {
  name?: string | null
  tagline?: string | null
  address?: string | null
  phone?: string | null
  logo_url?: string | null
  receipt_note?: string | null
}

export interface ReceiptData {
  invoice_number: string
  subtotal: string
  paid_amount: string
  outstanding_amount: string
  issued_at?: string | null
  created_at?: string | null
  patient_name?: string | null
  cashier_name?: string | null
  items: ReceiptItem[]
  payments?: ReceiptPayment[]
}

interface ReceiptProps {
  data: ReceiptData
  clinic?: ReceiptClinic | null
  /** Waktu cetak; diterima dari luar supaya tidak berubah tiap render. */
  printedAt: string
}

/** Garis putus-putus khas nota cetak. */
function Perforation() {
  return <div className="my-1.5 border-t border-dashed border-neutral-400" />
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-baseline justify-between gap-2">
      <dt className="shrink-0 text-neutral-500">{label}</dt>
      <dd className="text-right font-medium break-words text-neutral-900">
        {value}
      </dd>
    </div>
  )
}

/**
 * Nota untuk printer thermal 57mm — area cetaknya 48mm, jadi lebarnya dipatok
 * dalam milimeter, bukan piksel: yang tampil di layar adalah ukuran kertas
 * sebenarnya, sehingga pemenggalan barisnya sama persis dengan hasil cetak.
 * Layar hanya memperbesarnya lewat `data-receipt-frame` agar terbaca.
 *
 * Warnanya dikunci ke hitam-putih kertas, bukan token tema, karena dokumen ini
 * harus sama di layar terang, layar gelap, dan di atas kertas.
 */
export function Receipt({ data, clinic, printedAt }: ReceiptProps) {
  const { t } = useTrans()

  const services = data.items.filter((item) => item.kind !== "product")
  const products = data.items.filter((item) => item.kind === "product")
  const groups = [
    { key: "service", label: t("invoice.group_service"), items: services },
    { key: "product", label: t("invoice.group_product"), items: products },
  ].filter((group) => group.items.length > 0)

  const total = Number(data.subtotal)
  const paid = Number(data.paid_amount ?? 0)
  const outstanding = Number(data.outstanding_amount ?? 0)
  const issuedAt = data.issued_at ?? data.created_at

  return (
    <article
      data-receipt
      className="mx-auto w-[48mm] bg-white px-[2mm] pt-[3mm] pb-[4mm] text-xxs leading-snug text-neutral-900"
    >
      <header className="text-center">
        {clinic?.logo_url ? (
          <img
            src={clinic.logo_url}
            alt=""
            className="mx-auto mb-1.5 h-10 w-auto object-contain"
          />
        ) : null}

        <h1 className="text-2xs leading-tight font-bold tracking-[0.18em] uppercase">
          {clinic?.name ?? t("invoice.title")}
        </h1>

        {clinic?.tagline ? (
          <p className="mt-0.5 text-[0.5rem] tracking-[0.16em] text-neutral-600 uppercase">
            {clinic.tagline}
          </p>
        ) : null}

        {clinic?.address ? (
          <p className="mt-1.5 flex items-start justify-center gap-1 text-neutral-700">
            <HugeiconsIcon
              icon={Location01Icon}
              strokeWidth={2}
              className="mt-px size-2.5 shrink-0"
            />
            <span className="text-balance">{clinic.address}</span>
          </p>
        ) : null}

        {clinic?.phone ? (
          <p className="mt-0.5 flex items-center justify-center gap-1 font-medium">
            <HugeiconsIcon
              icon={Call02Icon}
              strokeWidth={2}
              className="size-2.5 shrink-0"
            />
            {clinic.phone}
          </p>
        ) : null}
      </header>

      <Perforation />

      {/* 48mm tidak cukup untuk menyandingkan label dan nomor, jadi judul
          notanya jadi pita selebar kertas dan datanya turun ke bawahnya. */}
      <p className="bg-neutral-900 py-[0.6mm] text-center text-[0.5rem] font-bold tracking-[0.18em] text-white uppercase">
        {t("invoice.receipt")}
      </p>

      <dl className="mt-1.5 space-y-0.5">
        <Row label={t("invoice.number_short")} value={data.invoice_number} />
        <Row label={t("invoice.date")} value={formatDateTime(issuedAt)} />
        <Row label={t("invoice.customer")} value={data.patient_name ?? "-"} />
        <Row label={t("invoice.served_by")} value={data.cashier_name ?? "-"} />
      </dl>

      <Perforation />

      <div className="grid grid-cols-[1.1rem_1fr_auto] items-center gap-x-1 bg-neutral-900 px-1 py-[0.6mm] text-[0.5rem] font-bold tracking-wider text-white uppercase">
        <span>{t("invoice.qty")}</span>
        <span>{t("invoice.description")}</span>
        <span className="text-right">{t("invoice.subtotal")}</span>
      </div>

      {groups.map((group) => (
        <section key={group.key}>
          <h2 className="mt-1.5 text-[0.5rem] font-bold tracking-wider text-neutral-700 uppercase">
            {group.label}
          </h2>
          <ul>
            {group.items.map((item) => (
              <li
                key={item.id}
                className="grid grid-cols-[1.1rem_1fr_auto] items-baseline gap-x-1 border-b border-dotted border-neutral-300 py-1 last:border-b-0"
              >
                <span className="px-1 tabular-nums">{item.qty}</span>
                <span className="leading-tight break-words">
                  {item.name}
                  {/* Harga satuan hanya berarti saat jumlahnya lebih dari satu;
                      selebihnya cuma mengulang subtotal dan memakan kertas. */}
                  {item.qty > 1 ? (
                    <span className="mt-px block text-[0.5rem] text-neutral-500 tabular-nums">
                      @ {formatAmount(Number(item.unit_price))}
                    </span>
                  ) : null}
                </span>
                <span className="text-right font-medium tabular-nums">
                  {formatAmount(Number(item.subtotal))}
                </span>
              </li>
            ))}
          </ul>
        </section>
      ))}

      <div className="mt-1.5 flex items-baseline justify-between gap-2 border-t border-neutral-900 pt-1">
        <span className="text-neutral-600">{t("invoice.item_total")}</span>
        <span className="font-medium tabular-nums">{formatAmount(total)}</span>
      </div>

      <div className="mt-1 flex items-baseline justify-between gap-2 border-t-2 border-neutral-900 pt-1.5">
        <span className="text-2xs font-bold tracking-tight uppercase">
          {t("invoice.grand_total")} (IDR)
        </span>
        <span className="text-sm leading-none font-bold tabular-nums">
          {formatAmount(total)}
        </span>
      </div>

      {data.payments && data.payments.length > 0 ? (
        <dl className="mt-1.5 space-y-0.5 border-t border-dashed border-neutral-400 pt-1.5">
          {data.payments.map((payment) => (
            <Row
              key={payment.id}
              label={payment.method_label ?? payment.method}
              value={formatAmount(Number(payment.amount))}
            />
          ))}
          <Row label={t("invoice.paid_amount")} value={formatAmount(paid)} />
        </dl>
      ) : null}

      {outstanding > 0 ? (
        <div className="mt-1 flex items-baseline justify-between gap-2 border border-neutral-900 px-1 py-0.5 font-bold">
          <span>{t("invoice.outstanding")}</span>
          <span className="tabular-nums">{formatAmount(outstanding)}</span>
        </div>
      ) : null}

      {clinic?.receipt_note ? (
        <p className="mt-1.5 text-center text-[0.5rem] text-neutral-600 italic">
          *{clinic.receipt_note}
        </p>
      ) : null}

      <Perforation />

      <footer className="text-center">
        <p className="text-2xs font-semibold italic">{t("invoice.thank_you")}</p>
        <p className="mt-0.5 text-[0.5rem] leading-snug text-neutral-600">
          {t("invoice.thank_you_sub")}
        </p>
        <p className="mt-1.5 text-[0.5rem] text-neutral-400 tabular-nums">
          {t("invoice.printed_at")}: {printedAt}
        </p>
      </footer>
    </article>
  )
}

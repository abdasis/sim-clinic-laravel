import { useTrans } from "#/hooks/use-trans.ts"
import { formatAmount, formatDateTime } from "#/lib/format.ts"

export interface ReceiptItem {
  id: number
  name: string
  kind?: string | null
  /** Harga normal sebelum promo; null untuk transaksi sebelum kolomnya ada. */
  list_price?: string | null
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

export interface ReceiptPerformer {
  id: number
  name: string
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
  cancelled_at?: string | null
  print_count?: number | null
  patient_name?: string | null
  cashier_name?: string | null
  performers?: ReceiptPerformer[]
  items: ReceiptItem[]
  payments?: ReceiptPayment[]
}

interface ReceiptProps {
  data: ReceiptData
  clinic?: ReceiptClinic | null
  /** Waktu cetak; diterima dari luar supaya tidak berubah tiap render. */
  printedAt: string
}

/** Pemisah antar bagian. Putus-putus, tapi cukup tebal untuk kepala termal. */
function Rule({ solid = false }: { solid?: boolean }) {
  return (
    <div
      aria-hidden="true"
      className={
        solid
          ? "my-[1mm] border-t border-neutral-900"
          : "my-[1mm] border-t border-dashed border-neutral-500"
      }
    />
  )
}

/**
 * Baris berlabel. Kolom labelnya selebar label terpanjang di blok itu, jadi
 * titik duanya sejajar tanpa memaksa label mana pun turun baris — di kertas
 * 48mm satu label yang membungkus langsung merusak seluruh kolomnya.
 */
function MetaRow({ label, value }: { label: string; value: string }) {
  return (
    <>
      <dt className="whitespace-nowrap text-neutral-600">{label}</dt>
      <dd className="text-center text-neutral-600">:</dd>
      <dd className="font-medium break-words text-neutral-900">{value}</dd>
    </>
  )
}

/** Baris nominal: keterangan di kiri, angka rata kanan. */
function AmountRow({
  label,
  value,
  bold = false,
}: {
  label: string
  value: string
  bold?: boolean
}) {
  return (
    <div className="flex items-baseline justify-between gap-2">
      <span className={bold ? "font-bold" : "text-neutral-700"}>{label}</span>
      <span
        className={
          bold
            ? "font-bold tabular-nums"
            : "font-medium tabular-nums text-neutral-900"
        }
      >
        {value}
      </span>
    </div>
  )
}

/** Pita status selebar kertas — dibaca sekilas tanpa perlu mengeja. */
function Band({ children }: { children: React.ReactNode }) {
  return (
    <p className="bg-neutral-900 py-[0.7mm] text-center text-3xs font-bold tracking-[0.18em] text-white uppercase">
      {children}
    </p>
  )
}

/**
 * Nota untuk printer thermal 57mm — area cetaknya 48mm, jadi lebarnya dipatok
 * dalam milimeter, bukan piksel: yang tampil di layar adalah ukuran kertas
 * sebenarnya, sehingga pemenggalan barisnya sama persis dengan hasil cetak.
 * Layar hanya memperbesarnya lewat `data-receipt-frame` agar terbaca.
 *
 * Dua hal yang menentukan bentuknya, dan keduanya milik kepala termal 203dpi:
 * huruf di bawah 9px saling menempel jadi noda, dan garis setipis rambut
 * hilang sama sekali. Karena itu tidak ada teks di bawah `text-3xs`, tidak ada
 * border dotted, dan ikon garis tipis diganti label teks.
 *
 * Barisnya disusun bertingkat (nama di atas, "jumlah x harga" dan nominal di
 * bawahnya) — bukan tiga kolom sejajar. Di kertas 48mm nama treatment yang
 * panjang selalu kalah melawan kolom angka, dan yang terpenggal justru bagian
 * yang paling perlu dibaca pasien.
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
  // Harga normal dipakai hanya bila lebih tinggi dari yang dibayar: baris
  // lama tidak menyimpannya, dan harga yang turun setelah transaksi jadi
  // bukan potongan yang pernah diterima pasien.
  const gross = data.items.reduce((sum, item) => {
    const listPrice = Number(item.list_price ?? 0)
    const unitPrice = Number(item.unit_price)

    return sum + Math.max(listPrice, unitPrice) * Number(item.qty)
  }, 0)
  const discount = Math.max(0, gross - total)
  const paid = Number(data.paid_amount ?? 0)
  const outstanding = Number(data.outstanding_amount ?? 0)
  const change = paid - total
  const issuedAt = data.issued_at ?? data.created_at
  const clinicName = clinic?.name ?? t("invoice.title")
  const performers = data.performers ?? []
  // Hitungan disimpan setelah dicetak, jadi cetakan yang sedang berjalan
  // adalah yang berikutnya — nota tidak boleh mengaku cetakan ke-0.
  const printCount = Math.max(1, Number(data.print_count ?? 0))
  const totalQty = data.items.reduce((sum, item) => sum + Number(item.qty), 0)

  return (
    <article
      data-receipt
      className="mx-auto w-[48mm] bg-white px-[2mm] pt-[3mm] pb-[10mm] text-xxs leading-snug text-neutral-900"
    >
      <header className="text-center">
        {clinic?.logo_url ? (
          <img
            src={clinic.logo_url}
            alt=""
            data-receipt-logo
            className="mx-auto mb-[1.5mm] h-[11mm] w-auto object-contain"
          />
        ) : null}

        <h1 className="text-2xs leading-tight font-bold tracking-[0.14em] uppercase">
          {clinicName}
        </h1>

        {clinic?.tagline ? (
          <p className="mt-[0.5mm] text-3xs tracking-[0.12em] text-neutral-700 uppercase">
            {clinic.tagline}
          </p>
        ) : null}

        {clinic?.address ? (
          <p className="mt-[1mm] text-3xs text-balance text-neutral-700">
            {clinic.address}
          </p>
        ) : null}

        {clinic?.phone ? (
          <p className="mt-[0.5mm] text-3xs font-medium tabular-nums">
            {t("invoice.phone_short")} {clinic.phone}
          </p>
        ) : null}
      </header>

      <Rule />

      {/* 48mm tidak cukup untuk menyandingkan label dan nomor, jadi judul
          notanya jadi pita selebar kertas dan datanya turun ke bawahnya. */}
      <Band>{t("invoice.receipt")}</Band>

      {data.cancelled_at ? (
        <div className="mt-[1mm]">
          <Band>{t("invoice.cancelled")}</Band>
          <p className="mt-[0.5mm] text-center text-3xs font-medium">
            {t("invoice.cancelled_note")}
          </p>
        </div>
      ) : null}

      <dl className="mt-[1.5mm] grid grid-cols-[auto_2mm_1fr] gap-y-[0.4mm]">
        <MetaRow label={t("invoice.number_short")} value={data.invoice_number} />
        <MetaRow label={t("invoice.date")} value={formatDateTime(issuedAt)} />
        <MetaRow
          label={t("invoice.customer")}
          value={data.patient_name ?? "-"}
        />
        <MetaRow
          label={t("invoice.served_by")}
          value={data.cashier_name ?? "-"}
        />
        {performers.length > 0 ? (
          <MetaRow
            label={t("invoice.performers")}
            value={performers.map((staff) => staff.name).join(", ")}
          />
        ) : null}
      </dl>

      <Rule />

      {groups.map((group) => (
        <section key={group.key} className="mb-[1mm] last:mb-0">
          <h2 className="text-3xs font-bold tracking-[0.1em] text-neutral-700 uppercase">
            {group.label}
          </h2>
          <ul className="mt-[0.5mm]">
            {group.items.map((item) => (
              <li
                key={item.id}
                data-receipt-line
                className="mt-[1mm] first:mt-[0.5mm]"
              >
                <p className="leading-tight font-medium break-words">
                  {item.name}
                </p>
                {/* Jumlah dan harga satuan selalu ditulis, juga saat qty 1:
                    pasien membandingkan nota dengan daftar harga, dan angka
                    satuan yang kadang ada kadang hilang membuatnya ragu. */}
                <div className="flex items-baseline justify-between gap-1 tabular-nums">
                  <span className="text-neutral-600">
                    {item.qty} x {formatAmount(Number(item.unit_price))}
                  </span>
                  <span className="font-medium">
                    {formatAmount(Number(item.subtotal))}
                  </span>
                </div>
              </li>
            ))}
          </ul>
        </section>
      ))}

      <Rule solid />

      <div className="space-y-[0.4mm]">
        <AmountRow
          label={`${t("invoice.item_total")} (${t("invoice.item_count").replace(
            ":count",
            String(totalQty),
          )})`}
          value={formatAmount(discount > 0 ? gross : total)}
        />
        {/* Potongan ditulis sebagai barisnya sendiri: pasien yang datang
            karena promo berhak melihat angkanya, bukan cuma harga akhir
            yang kebetulan lebih murah. */}
        {discount > 0 ? (
          <AmountRow
            label={t("invoice.discount")}
            value={`-${formatAmount(discount)}`}
          />
        ) : null}
      </div>

      <div className="mt-[1mm] flex items-baseline justify-between gap-2 border-t-2 border-neutral-900 pt-[1mm]">
        <span className="text-2xs font-bold tracking-tight uppercase">
          {t("invoice.grand_total")} (IDR)
        </span>
        <span className="text-sm leading-none font-bold tabular-nums">
          {formatAmount(total)}
        </span>
      </div>

      {data.payments && data.payments.length > 0 ? (
        <div className="mt-[1.5mm] space-y-[0.4mm] border-t border-dashed border-neutral-500 pt-[1.5mm]">
          {data.payments.map((payment) => (
            <AmountRow
              key={payment.id}
              label={payment.method_label ?? payment.method}
              value={formatAmount(Number(payment.amount))}
            />
          ))}
          {/* Dengan satu metode bayar, "Sudah Dibayar" cuma mengulang baris
              di atasnya. Yang perlu dijumlahkan hanya pembayaran bertahap. */}
          {data.payments.length > 1 ? (
            <AmountRow
              label={t("invoice.paid_amount")}
              value={formatAmount(paid)}
            />
          ) : null}
          {/* Kembalian hanya muncul kalau memang ada uang yang dikembalikan;
              baris "Kembali 0" cuma menambah keraguan di meja kasir. */}
          {change > 0 ? (
            <AmountRow
              label={t("invoice.change")}
              value={formatAmount(change)}
              bold
            />
          ) : null}
        </div>
      ) : null}

      {outstanding > 0 ? (
        <div className="mt-[1mm] flex items-baseline justify-between gap-2 border border-neutral-900 px-[1mm] py-[0.6mm] font-bold">
          <span>{t("invoice.outstanding")}</span>
          <span className="tabular-nums">{formatAmount(outstanding)}</span>
        </div>
      ) : null}

      {clinic?.receipt_note ? (
        <p className="mt-[1.5mm] text-center text-3xs text-neutral-700 italic">
          *{clinic.receipt_note}
        </p>
      ) : null}

      <footer className="mt-[3mm]">
        {/* Garis berornamen, bukan perforasi biasa: bagian ini penutup yang
            personal, jadi pemisahnya pun berbeda dari pemisah data di atas. */}
        <div className="flex items-center gap-[1mm]" aria-hidden="true">
          <span className="h-px flex-1 bg-neutral-800" />
          <span className="text-3xs leading-none">&#10022;</span>
          <span className="text-3xs leading-none">&#9829;</span>
          <span className="h-px flex-1 bg-neutral-800" />
        </div>

        <p className="mt-[1mm] text-center font-script text-xl leading-none">
          {t("invoice.thank_you")}
        </p>
        <p className="mt-[1mm] text-center text-3xs tracking-[0.1em] text-neutral-700 uppercase">
          {t("invoice.thank_you_sub")} {clinicName}
        </p>

        <Rule />

        {/* Cetakan kedua dan seterusnya ditandai jelas: tanpa itu satu
            transaksi bisa beredar sebagai dua bukti bayar yang sama sahnya. */}
        {printCount > 1 ? (
          <p className="mb-[1mm] border border-neutral-900 py-[0.4mm] text-center text-3xs font-bold tracking-[0.14em] uppercase">
            {t("invoice.reprint")} #{printCount}
          </p>
        ) : null}

        {/* Sejajar berlabel seperti nota cetak: label rata kiri, titik dua
            sejajar, nilainya menyusul — mudah dipindai walau kertasnya sempit. */}
        <dl className="grid grid-cols-[auto_2mm_1fr] text-3xs text-neutral-600 tabular-nums">
          <MetaRow label={t("invoice.print_count")} value={String(printCount)} />
          <MetaRow label={t("invoice.printed_at")} value={printedAt} />
        </dl>
      </footer>
    </article>
  )
}

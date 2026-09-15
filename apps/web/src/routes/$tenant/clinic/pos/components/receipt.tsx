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
  /** Potongan keanggotaan, disalin saat nota dibuat. */
  member_tier_name?: string | null
  member_discount_amount?: string | null
  /** Poin loyalitas dari nota ini; nol selama belum lunas. */
  points_earned?: number | null
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

/**
 * Pagar terakhir panjang alamat di kertas 48mm — kira-kira tiga baris pada
 * ukuran huruf terkecil yang masih terbaca.
 *
 * Sengaja longgar. Memotong alamat lebih pendek dari ini menghasilkan
 * penggalan yang tidak menuntun siapa pun ke mana pun ("Blok A 10, Tanjung…"),
 * dan alamat yang salah lebih buruk daripada alamat yang panjang. Alamat yang
 * benar-benar ringkas hanya bisa datang dari orang yang menulisnya di
 * Pengaturan → Profil Perusahaan; angka ini cuma menahan nilai yang benar-benar
 * kebablasan.
 */
const ADDRESS_LIMIT = 90

/**
 * Alamat sebagaimana layak dicetak di nota: tanpa tautan, ringkas, satu
 * paragraf.
 *
 * Tautan peta dibuang apa pun bentuknya. Di layar ia berguna, di atas kertas
 * ia deretan karakter acak yang tidak bisa diklik siapa pun — dua baris kertas
 * terbuang untuk sesuatu yang tidak pernah dibaca. Membuangnya di sini, bukan
 * meminta orang merapikan isi kolomnya, karena kolom alamat akan diisi ulang
 * oleh orang lain lagi nanti dan hasilnya harus tetap benar.
 *
 * Panjangnya hanya dipagari, tidak dirapikan: memendekkan alamat adalah
 * keputusan orang yang tahu tempatnya, bukan yang bisa disimpulkan mesin dari
 * teksnya.
 *
 * ponytail: aturan yang sama juga hidup di `App\Support\ReceiptAddress` untuk
 * nota yang diunduh sebagai PDF. Dua salinan karena memang ada dua perender
 * nota, dan keduanya sudah menduplikasi seluruh tata letaknya sejak awal.
 * Menyatukannya berarti satu perender saja, dan itu pekerjaan tersendiri;
 * sampai saat itu, perubahan di sini wajib diikutkan ke sana.
 */
export function receiptAddress(raw?: string | null): string | null {
  if (!raw) return null

  const cleaned = raw
    // Tautan dengan skema, termasuk yang tercetak terpenggal seperti "os://".
    .replace(/\b[a-z][a-z0-9+.-]*:\/\/\S+/gi, " ")
    // Tautan telanjang: www.contoh.com, maps.app.goo.gl/xxxx.
    .replace(/\b(?:www\.|[a-z0-9-]+\.(?:com|id|co|net|org|goo\.gl|app\.goo\.gl))\/?\S*/gi, " ")
    .replace(/\s+/g, " ")
    // Tanda baca yang menggantung setelah tautannya dibuang.
    .replace(/\s*([,.;|-])\s*$/g, "")
    .trim()

  if (cleaned === "") return null
  if (cleaned.length <= ADDRESS_LIMIT) return cleaned

  // Dipotong di batas kata supaya tidak ada penggalan kata yang menggantung.
  const head = cleaned.slice(0, ADDRESS_LIMIT)
  const cut = Math.max(head.lastIndexOf(" "), head.lastIndexOf(","))

  return `${(cut > 0 ? head.slice(0, cut) : head).replace(/[,.;]$/, "")}\u2026`
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
    <p className="bg-neutral-900 py-[0.7mm] text-center text-xxs font-bold tracking-[0.18em] text-white uppercase">
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
 * Ukuran hurufnya dinaikkan satu tingkat dari rancangan pertama. Batas bawah
 * 9px memang masih terbaca oleh kepala termal, tapi "masih terbaca" bukan
 * ukuran yang tepat untuk kertas yang dibaca sambil berdiri di meja kasir,
 * kerap oleh orang yang matanya tidak semuda perancangnya.
 *
 * Dua hal lain menentukan bentuknya, dan keduanya milik kepala termal 203dpi:
 * garis setipis rambut hilang sama sekali, dan huruf terlalu kecil saling
 * menempel jadi noda. Karena itu tidak ada border dotted dan ikon garis tipis
 * diganti label teks.
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
  // Manfaat keanggotaan dipisahkan dari potongan lain. Pasien membayar di
  // muka untuk jadi member, jadi angkanya berhak berdiri sendiri — tercampur
  // jadi satu dengan promo, tidak ada yang bisa membuktikan kartunya terpakai.
  const memberDiscount = Math.max(0, Number(data.member_discount_amount ?? 0))
  const discount = Math.max(0, gross - total - memberDiscount)
  const paid = Number(data.paid_amount ?? 0)
  const outstanding = Number(data.outstanding_amount ?? 0)
  const change = paid - total
  const issuedAt = data.issued_at ?? data.created_at
  // Cadangan untuk kop nota, bukan judul dokumen: yang dicetak di baris
  // teratas adalah nama klinik, jadi cadangannya juga harus terbaca sebagai
  // nama tempat.
  const clinicName = clinic?.name ?? t("clinic.clinic")
  const performers = data.performers ?? []
  // Hitungan disimpan setelah dicetak, jadi cetakan yang sedang berjalan
  // adalah yang berikutnya — nota tidak boleh mengaku cetakan ke-0.
  const printCount = Math.max(1, Number(data.print_count ?? 0))
  const totalQty = data.items.reduce((sum, item) => sum + Number(item.qty), 0)
  const address = receiptAddress(clinic?.address)
  const pointsEarned = Math.max(0, Number(data.points_earned ?? 0))

  return (
    <article
      data-receipt
      className="mx-auto w-[48mm] bg-white px-[2mm] pt-[2mm] pb-[4mm] text-2xs leading-snug text-neutral-900"
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

        <h1 className="text-xs leading-tight font-bold tracking-[0.14em] uppercase">
          {clinicName}
        </h1>

        {clinic?.tagline ? (
          <p className="mt-[0.5mm] text-xxs tracking-[0.12em] text-neutral-700 uppercase">
            {clinic.tagline}
          </p>
        ) : null}

        {address ? (
          <p className="mt-[0.8mm] text-xxs text-balance text-neutral-700">
            {address}
          </p>
        ) : null}

        {clinic?.phone ? (
          <p className="mt-[0.5mm] text-xxs font-medium tabular-nums">
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
          <p className="mt-[0.5mm] text-center text-xxs font-medium">
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
          <h2 className="text-xxs font-bold tracking-[0.1em] text-neutral-700 uppercase">
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
          value={formatAmount(discount + memberDiscount > 0 ? gross : total)}
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
        {memberDiscount > 0 ? (
          <AmountRow
            label={
              data.member_tier_name
                ? `${t("invoice.member_discount")} (${data.member_tier_name})`
                : t("invoice.member_discount")
            }
            value={`-${formatAmount(memberDiscount)}`}
          />
        ) : null}
      </div>

      <div className="mt-[1mm] flex items-baseline justify-between gap-2 border-t-2 border-neutral-900 pt-[1mm]">
        <span className="text-xs font-bold tracking-tight uppercase">
          {t("invoice.grand_total")} (IDR)
        </span>
        <span className="text-base leading-none font-bold tabular-nums">
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

      {/* Nol tidak pernah dicetak: baris yang selalu ada tapi kadang "+0 poin"
          cuma menambah keraguan tanpa memberi apa-apa. */}
      {pointsEarned > 0 ? (
        <div className="mt-[1mm] flex items-baseline justify-between gap-2 text-xxs text-neutral-700">
          <span>{t("invoice.points_earned")}</span>
          <span className="font-medium tabular-nums text-neutral-900">
            +{pointsEarned} {t("invoice.points_unit")}
          </span>
        </div>
      ) : null}

      {clinic?.receipt_note ? (
        <p className="mt-[1.5mm] text-center text-xxs text-neutral-700 italic">
          *{clinic.receipt_note}
        </p>
      ) : null}

      <footer className="mt-[2mm]">
        {/* Garis berornamen, bukan perforasi biasa: bagian ini penutup yang
            personal, jadi pemisahnya pun berbeda dari pemisah data di atas. */}
        <div className="flex items-center gap-[1mm]" aria-hidden="true">
          <span className="h-px flex-1 bg-neutral-800" />
          <span className="text-xxs leading-none">&#10022;</span>
          <span className="text-xxs leading-none">&#9829;</span>
          <span className="h-px flex-1 bg-neutral-800" />
        </div>

        <p className="mt-[0.8mm] text-center font-script text-lg leading-none">
          {t("invoice.thank_you")}
        </p>
        <p className="mt-[0.5mm] text-center text-xxs tracking-[0.1em] text-neutral-700 uppercase">
          {t("invoice.thank_you_sub")} {clinicName}
        </p>

        {/* Keterangan cetak hanya muncul pada cetakan ulang. Pada cetakan
            pertama ia cuma mengulang tanggal yang sudah ada di kepala nota,
            dan tiga baris tambahan di tiap struk itu gulungan kertas yang
            terbuang tanpa ada yang membacanya.

            Untuk cetakan kedua dan seterusnya keterangan ini justru wajib:
            tanpa penanda, satu transaksi bisa beredar sebagai dua bukti bayar
            yang sama sahnya. Nomor dan waktunya dirapatkan jadi satu baris. */}
        {printCount > 1 ? (
          <p className="mt-[1.5mm] flex flex-wrap items-baseline justify-center gap-x-[1.5mm] border border-neutral-900 px-[1mm] py-[0.4mm] text-center text-xxs">
            <span className="font-bold tracking-[0.12em] uppercase">
              {t("invoice.reprint")} #{printCount}
            </span>
            <span className="text-neutral-600 tabular-nums">{printedAt}</span>
          </p>
        ) : null}
      </footer>
    </article>
  )
}

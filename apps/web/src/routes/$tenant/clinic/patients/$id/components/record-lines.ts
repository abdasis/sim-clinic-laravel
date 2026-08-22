import type { CellLine, RecordRow, TransactionItemRow } from "./record-types.ts"

/**
 * Tindakan pada satu kunjungan datang dari dua tempat: catatan klinis yang
 * ditulis dokter, dan baris layanan di nota kasir. Dokter membaca satu
 * kunjungan sebagai satu peristiwa, jadi keduanya digabung jadi satu daftar.
 *
 * Facial yang dicatat dokter dan facial yang ditagih kasir adalah tindakan
 * yang sama, jadi digabung jadi satu baris berikut harganya — bukan dua
 * baris kembar. Yang tidak berpasangan tetap ditampilkan: tindakan tanpa
 * tagihan (dokter mencatat, kasir belum menagih) dan tagihan tanpa catatan
 * (kasir menagih, dokter belum sempat mencatat) sama-sama perlu terbaca,
 * dan menyembunyikan salah satunya berarti menghapus fakta.
 *
 * Pasangannya dihitung per kejadian, bukan per nama: dua sesi facial dalam
 * satu hari harus jadi dua baris berharga, bukan satu baris berharga dan
 * satu baris tanpa harga.
 */
export function treatmentLines(record: RecordRow): CellLine[] {
  const billed = (record.transaction?.items ?? []).filter(
    (item) => item.kind === "service",
  )

  // Penanda per indeks, bukan per nama — nama yang sama boleh muncul
  // beberapa kali dan tiap kemunculan berhak atas pasangannya sendiri.
  const taken = new Set<number>()

  const clinical = (record.treatments ?? [])
    .map((treatment) => treatment.service_name)
    .filter((name): name is string => Boolean(name))
    .map((name): CellLine => {
      const index = billed.findIndex(
        (item, at) => !taken.has(at) && sameService(item.name, name),
      )

      if (index === -1) return { label: name }

      taken.add(index)

      const match = billed[index]

      return {
        label: name,
        amount: match.subtotal,
        qty: match.qty > 1 ? match.qty : undefined,
      }
    })

  const rest = billed
    .filter((_, at) => !taken.has(at))
    .map((item): CellLine => ({
      label: item.name,
      amount: item.subtotal,
      qty: item.qty > 1 ? item.qty : undefined,
    }))

  return [...clinical, ...rest]
}

export function productLines(record: RecordRow): CellLine[] {
  return (record.transaction?.items ?? [])
    .filter((item) => item.kind === "product")
    .map((item): CellLine => ({
      label: item.name,
      amount: item.subtotal,
      qty: item.qty > 1 ? item.qty : undefined,
    }))
}

/**
 * Kedua sisi menyalin nama layanan saat datanya dibuat, jadi biasanya sama
 * persis. Yang membuatnya meleset hal-hal remeh: spasi ganda dari salin
 * tempel, dan huruf kapital yang berbeda antara master layanan dan ejaan
 * dokter. Selisih seperti itu tidak boleh berakhir sebagai baris kembar.
 */
function sameService(a: string, b: string): boolean {
  return normalise(a) === normalise(b)
}

function normalise(value: string): string {
  return value.trim().replace(/\s+/g, " ").toLowerCase()
}

/** Dipakai uji dan pemanggil lain yang perlu tahu apakah dua nama sepadan. */
export { sameService }
export type { TransactionItemRow }

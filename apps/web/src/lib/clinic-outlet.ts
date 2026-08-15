import type { Translatable } from "#/lib/company-locale.ts"

export interface OpeningHour {
  day: Translatable<string>
  /** null berarti tutup — dirender berbeda, bukan sebagai jam kosong. */
  hours: string | null
}

export interface ClinicOutlet {
  name: string
  city: Translatable<string>
  /** Dipecah per baris supaya alamat panjang tetap enak dibaca. */
  addressLines: Translatable<string[]>
  postalCode: string
  phone: string
  /** Format internasional tanpa tanda plus, mis. 628123456789. */
  whatsapp: string
  /** Tautan Google Maps resmi outlet ini. */
  mapsUrl: string
  /**
   * Kueri untuk peta sematan. Google melayani `?q=<kueri>&output=embed` tanpa
   * kunci API, jadi alamat lengkap sudah cukup. Kosongkan bila belum pasti —
   * halaman akan menampilkan kartu tautan, bukan peta yang salah tempat.
   */
  mapQuery: string
  openingHours: OpeningHour[]
  /** Patokan dan cara menuju lokasi. */
  gettingHere: Translatable<string[]>
}

const DAYS: Array<Translatable<string>> = [
  { id: "Senin", en: "Monday" },
  { id: "Selasa", en: "Tuesday" },
  { id: "Rabu", en: "Wednesday" },
  { id: "Kamis", en: "Thursday" },
  { id: "Jumat", en: "Friday" },
  { id: "Sabtu", en: "Saturday" },
  { id: "Minggu", en: "Sunday" },
]

/** Jam yang sama tiap hari; dipisah begini supaya mudah diubah per hari. */
function everyDay(hours: string): OpeningHour[] {
  return DAYS.map((day) => ({ day, hours }))
}

/**
 * Satu-satunya outlet yang beroperasi saat ini.
 *
 * PERLU DIKONFIRMASI: `addressLines`, `postalCode`, `phone`, `whatsapp`,
 * `openingHours`, dan `gettingHere` belum diverifikasi terhadap data resmi —
 * lihat catatan di PR/percakapan. `mapsUrl` sudah benar dan bisa dipakai apa
 * adanya; `mapQuery` sengaja dikosongkan sampai alamatnya pasti, supaya peta
 * tidak pernah menunjuk tempat yang keliru.
 */
export const OUTLET: ClinicOutlet = {
  name: "MEBA Clinic",
  city: { id: "Jakarta", en: "Jakarta" },
  // Dikosongkan sampai alamat resminya masuk; halaman menyusut dengan rapi
  // dan mengarahkan ke Maps, bukan menampilkan catatan internal ke pengunjung.
  addressLines: { id: [], en: [] },
  postalCode: "",
  phone: "",
  whatsapp: "",
  mapsUrl: "https://maps.app.goo.gl/R5mFMnqt5LnvtzYs8",
  mapQuery: "",
  openingHours: everyDay("09.00–20.00"),
  gettingHere: {
    id: [],
    en: [],
  },
}

/** Alamat satu baris untuk tombol salin dan data terstruktur. */
export function flatAddress(lines: string[], postalCode: string): string {
  return [...lines, postalCode].filter(Boolean).join(", ")
}

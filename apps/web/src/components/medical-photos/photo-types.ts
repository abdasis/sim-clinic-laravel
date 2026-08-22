/** Jenis foto klinis; nilainya sama persis dengan enum di sisi server. */
export type MedicalPhotoType = "before" | "after"

export interface PhotoRow {
  id: number
  type: string
  type_label?: string | null
  url?: string | null
  path?: string | null
}

export const PHOTO_ACCEPT = "image/jpeg,image/png"
export const PHOTO_MAX_BYTES = 2 * 1024 * 1024
const PHOTO_MIME = ["image/jpeg", "image/png"]

/**
 * Satu pasang foto: sebelum di kiri, sesudah di kanan.
 *
 * Salah satunya boleh kosong — perawatan yang fotonya baru diambil sebelum
 * tindakan tetap harus tampil, bukan disembunyikan sampai pasangannya ada.
 */
export interface PhotoPair {
  before?: PhotoRow
  after?: PhotoRow
}

export function photoSrc(photo: PhotoRow): string {
  return photo.url ?? photo.path ?? ""
}

/**
 * Pasangkan foto ke-n sisi sebelum dengan foto ke-n sisi sesudah.
 *
 * Pemasangan lewat urutan, bukan lewat penanda pasangan di database: klinik
 * memotret berurutan (sebelum lalu sesudah untuk area yang sama), dan
 * meminta staf menautkan tiap foto satu per satu menambah kerja tanpa
 * menambah ketepatan. Urutannya dipatok server (foto diurut menurut id),
 * jadi hasilnya sama di setiap kali muat.
 */
export function pairPhotos(photos: PhotoRow[] = []): PhotoPair[] {
  const before = photos.filter((photo) => photo.type === "before")
  const after = photos.filter((photo) => photo.type === "after")
  const rows = Math.max(before.length, after.length)

  return Array.from({ length: rows }, (_, index) => ({
    before: before[index],
    after: after[index],
  }))
}

export function countByType(photos: PhotoRow[] = []): {
  before: number
  after: number
} {
  return {
    before: photos.filter((photo) => photo.type === "before").length,
    after: photos.filter((photo) => photo.type === "after").length,
  }
}

/**
 * Alasan berkas ditolak, atau null bila lolos.
 *
 * Batasnya disamakan dengan aturan di server (jpg/png, 2 MB): ditolak di
 * sini supaya penggunanya tahu sebelum menunggu unggahan yang pasti gagal.
 */
export function photoRejection(file: File): "type" | "size" | null {
  if (!PHOTO_MIME.includes(file.type)) return "type"
  if (file.size > PHOTO_MAX_BYTES) return "size"

  return null
}

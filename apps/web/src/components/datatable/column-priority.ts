/**
 * Prioritas kolom: mana yang mengalah lebih dulu saat ruangnya menyempit.
 *
 * Mode kartu sudah menjawab layar paling sempit — tabel berkolom banyak di
 * bawah 640px berubah jadi daftar kartu. Yang belum terjawab pita di
 * antaranya: wadah 640-1024px, yang persis didapat tablet mendatar dan
 * desktop dengan sidebar terbuka. Di sana tabelnya masih tabel, enam kolom
 * dengan judul Indonesia yang panjang ("Saldo stok", "Status penjualan")
 * saling menghimpit, dan satu-satunya jalan keluar menggulir mendatar.
 *
 * Yang disembunyikan selalu kolom sekunder, dan selalu lewat CSS: keputusan
 * ini tidak boleh lewat `column.toggleVisibility`, karena itu state yang sama
 * dipakai pengguna lewat menu "Kolom" — menyetelnya dari lebar wadah akan
 * menimpa pilihan yang baru saja mereka buat.
 */
export type ColumnPriority = 1 | 2 | 3

/**
 * Kelas untuk sel dan judul kolom menurut prioritasnya.
 *
 * - `1` — selalu tampil. Kolom identitas, status, dan aksi. Tanpa ketiganya
 *   satu baris tidak bisa dikenali maupun diubah, jadi apa pun yang terjadi
 *   pada ruangnya mereka tetap ada.
 * - `2` — muncul sejak `md` (768px). Kolom yang menjelaskan, bukan
 *   mengidentifikasi: kategori, satuan, jumlah.
 * - `3` — muncul sejak `lg` (1024px). Kolom yang jarang dibaca sambil
 *   bekerja: tanggal dibuat, catatan, nama pembuat.
 *
 * Ambangnya lebar layar, bukan lebar wadah, karena ini keputusan CSS murni —
 * dan itu disengaja: kolom yang hilang-muncul mengikuti sidebar yang
 * dibuka-tutup membuat tabelnya terasa tidak stabil, sementara kartu (yang
 * memang mengikuti wadah) berganti bentuk sekaligus sehingga perubahannya
 * terbaca sebagai satu keputusan.
 */
export function priorityClass(priority: ColumnPriority | undefined): string {
  if (priority === 2) return "hidden md:table-cell"
  if (priority === 3) return "hidden lg:table-cell"

  return ""
}

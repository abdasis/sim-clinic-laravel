# Spesifikasi Aset Gambar — Company Profile

Ukuran di bawah diukur langsung dari komponen yang dipakai
(`apps/web/src/components/company-profile/*`), bukan perkiraan. Kolom "kotak
render" adalah ukuran nyata elemen `<img>` di browser; kolom "ukuran kirim"
sudah memperhitungkan layar 2x (retina).

## Batasan wajib

Berlaku untuk semua unggahan lewat `POST /{tenant}/clinic/company-profile/media`
(`MediaUploadRequest`):

| Aturan | Nilai |
| --- | --- |
| Ukuran berkas maksimum | **2 MB** (`max:2048`) |
| Format diterima | `jpg`, `jpeg`, `png`, `webp`, `svg` |
| Resize di server | **tidak ada** — berkas disimpan dan disajikan apa adanya |
| Lokasi simpan | `storage/app/public/company-profile/{tenant_id}/{entity}/` |
| Nama berkas | di-hash Laravel, nama asli tidak dipakai di URL |

Karena tidak ada pemrosesan di server, ukuran yang diunggah persis itulah yang
diunduh pengunjung. Ekspor di ukuran target, jangan unggah hasil kamera mentah.

Format yang disarankan: **WebP kualitas 80** untuk foto, **PNG transparan** bila
butuh transparansi, **SVG** untuk logo.

## Ringkasan ukuran

| Aset | Kotak render (desktop) | Kotak render (mobile) | Ukuran kirim | Rasio | Target berkas |
| --- | --- | --- | --- | --- | --- |
| Hero slide | 1905 x 680 | 390 x 658 | **2560 x 1440** | 16:9 | <= 700 KB |
| Treatment (kartu + detail) | 573 x 430 | 453 x 340 | **1600 x 1200** | 4:3 | <= 400 KB |
| Promo utama | 578 x 380 | 453 x 283 | **1600 x 1000** | 16:10 | <= 350 KB |
| Promo biasa | 544 x 306 | 453 x 255 | **1600 x 900** | 16:9 | <= 300 KB |
| Banner split | 552 x 360 | 453 x 224 | **1600 x 1200** | 4:3 | <= 350 KB |
| Banner selebar layar | 1905 x 296 | 485 x 264 | **2560 x 800** | 3.2:1 | <= 250 KB |
| Logo sub-brand | 160 x 32 | sama | **SVG** atau PNG 480 x 96 | bebas, <= 5:1 | <= 40 KB |
| Avatar testimoni | 56 x 56 | sama | **256 x 256** | 1:1 | <= 80 KB |
| Logo klinik | 28 px tinggi (header), 36 x 36 (footer) | sama | **SVG** atau PNG 400 x 120 | <= 3:1 | <= 60 KB |

## Detail per aset

### Hero slide — 2560 x 1440

Komponen: `hero-carousel.tsx`. Kotaknya `h-[78svh] max-h-[680px] min-h-[440px]`
selebar layar, dengan `object-cover`.

Rasio kotaknya berubah drastis antar perangkat: 2,80:1 di desktop 1920 px, tapi
0,59:1 (potret) di ponsel 390 px. Satu gambar harus selamat di keduanya, jadi
area amannya adalah irisan dari apa yang terlihat di semua layar:

- **Horizontal 55%-66%** — ponsel hanya menampilkan pita tengah 33%-67% dari
  lebar sumber, sedangkan judul di desktop menutupi sampai sekitar 51%. Yang
  tersisa untuk wajah/objek utama adalah pita sempit sedikit di kanan tengah.
- **Vertikal 20%-80%** — desktop hanya menampilkan 63% bagian tengah tinggi
  sumber; seperlima atas dan bawah terpotong.
- Hindari menaruh detail penting di **180 px paling bawah**: di situ ada kontrol
  carousel dan gradien peredup.
- Sisi kiri frame akan tertutup gradien gelap (`neutral-950/85`), jadi pilih foto
  yang bagian kirinya memang bisa gelap.

Batasi ke **3-4 slide**: tiap slide dimuat di awal halaman.

### Treatment — 1600 x 1200

Komponen: `treatment-grid.tsx` (`aspect-[4/3]`) dan halaman detail
`routes/$tenant/treatment/$slug.tsx` (`aspect-[4/3]`).

Satu gambar melayani dua tempat karena rasionya sama persis, dan karena sumber
4:3 masuk ke kotak 4:3, **tidak ada pemotongan sama sekali**. Ukuran terbesar
yang dipakai adalah 573 x 430 di halaman detail, jadi 1600 x 1200 sudah lebih
dari cukup untuk layar 2x.

### Promo — 1600 x 1000 (utama) dan 1600 x 900 (biasa)

Komponen: `promo-section.tsx`. Promo pertama tampil sebagai kartu lebar dengan
gambar di kolom `1.1fr` yang tingginya mengikuti panjang teks (minimal 304 px),
sisanya kartu `aspect-[16/9]` dua kolom.

Karena tinggi kolom promo utama mengikuti teks, sisakan margin aman sekitar 10%
di kiri-kanan supaya objek utama tidak terpotong saat teksnya panjang.

### Banner split — 1600 x 1200

Komponen: `content-banner.tsx`, cabang `layout_type === "split"`. Gambar mengisi
setengah kartu (`min-h-56`) dan tingginya ikut kolom teks.

Di ponsel kotaknya melebar jadi 2,02:1, yang memotong sepertiga atas dan bawah.
Jaga objek utama di **60% bagian tengah secara vertikal**.

### Banner selebar layar — 2560 x 800

Komponen: `content-banner.tsx`, cabang non-split. Gambar duduk di belakang
lapisan warna merek dengan opasitas 85-88%, jadi fungsinya tekstur suasana, bukan
subjek.

Kotaknya 6,44:1 di desktop — hanya **50% bagian tengah tinggi** yang terlihat.
Pilih foto berkontras rendah tanpa titik fokus, dan kompres agresif (kualitas 60
sudah cukup karena tertutup warna).

### Logo sub-brand — SVG

Komponen: `brand-section.tsx`. Dirender `h-8 w-auto max-w-[10rem]` dengan
`object-contain`, dan **default-nya grayscale** (warna muncul saat hover).

- SVG paling ideal: tajam di ukuran berapa pun dan ringan.
- Kalau raster: PNG transparan 480 x 96 (3x dari 160 x 32).
- Pastikan logonya masih terbaca dalam mono, karena itu tampilan defaultnya.
- Rasio jangan lebih lebar dari 5:1, nanti tingginya menyusut jauh di bawah 32 px.

### Avatar testimoni — 256 x 256

Komponen: `testimonial-section.tsx`, `Avatar` `size-14` (56 px) bulat dengan
`ring-4`. Potong persegi dengan wajah di tengah dan sedikit ruang di atas kepala;
sudutnya akan terpotong lingkaran.

### Logo klinik — SVG

Dipakai di dua tempat dari satu kolom `logo_path`:

- `company-header.tsx` — `h-7 w-auto`, jadi lockup horizontal setinggi 28 px.
- `company-footer.tsx` — `size-9 object-contain`, jadi kotak 36 x 36.

Konsekuensinya: logo yang sangat lebar akan mengecil di footer karena dipaksa
masuk kotak persegi. Rasio aman **maksimal 3:1**. Kalau butuh lockup lebar untuk
header sekaligus lambang persegi untuk footer, perlu tambahan kolom terpisah
(`logo_mark_path`) di backend.

### Favicon dan ikon PWA

Berkas statis di `apps/web/public/`, bukan unggahan tenant:

| Berkas | Ukuran |
| --- | --- |
| `favicon.ico` | 32 x 32 dan 48 x 48 dalam satu ICO |
| `logo192.png` | 192 x 192 |
| `logo512.png` | 512 x 512 |

## Anggaran berat halaman

Halaman depan memuat, dalam kondisi terisi penuh: 3 hero + 6 treatment +
3 promo + 2 banner + 6 logo brand + 3 avatar. Dengan target di tabel, totalnya
sekitar 5-6 MB, dan hero menyumbang sepertiganya. Kalau harus dipangkas, potong
jumlah slide hero lebih dulu, bukan kualitas treatment.

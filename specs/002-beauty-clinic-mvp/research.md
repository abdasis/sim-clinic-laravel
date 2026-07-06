# Research: MVP Sistem Klinik Kecantikan

**Spec**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md)

Hasil resolusi semua NEEDS CLARIFICATION dari Technical Context. Setiap keputusan diambil dengan prinsip lazy (ponytail ladder): stdlib/native/reuse dulu, dep baru hanya jika jelas dibutuhkan, sesuai constraint CLAUDE.md.

---

## R1. Strategi dependensi spec 001 (belum terimplementasi)

**Decision**: Spec 002 mengasumsikan spec 001 sudah (atau akan) diimplementasikan terlebih dahulu. Spec 002 TIDAK menarik ulang fondasi tenant — semua entitas bisnis memakai `BelongsToTenant` trait + `TenantScope` + middleware `ResolveTenant` + field `tenant_id` dari spec 001. Implementasi spec 002 hanya menambah: kolom `clinic_role` (enum 4 peran klinik) pada `users`, dan 13 tabel bisnis baru.

**Rationale**: Spec 002 secara eksplisit menyatakan "dibangun di atas spec 001" (assumptions spec.md). Duplikasi fondasi tenant = melanggar DRY + risiko dual implementation. Spec-kit workflow memproses spec secara berurutan; spec 001 sudah punya plan/tasks lengkap.

**Alternatives considered**:
- Tarik dependensi minimum spec 001 ke spec 002 → ditolak: duplikasi, bingung sumber kebenaran, CLAUDE.md larang boilerplate.
- Implementasi fondasi tenant di dalam spec 002 → ditolak: scope creep, melanggar asumssi spec.

**Action**: Sebelum `/speckit-implement` spec 002, pastikan spec 001 sudah terimplementasi (minimal: `Tenant` + `User.tenant_id` + `BelongsToTenant` + `TenantScope` + `ResolveTenant` + `lang/id/*`). Jika user ingin paralel, selesaikan item-item tersebut sebagai prasyarat.

---

## R2. Manajemen peran klinik & permission (FR-001, FR-002, FR-044, FR-075)

**Decision**: Manual `ClinicRole` enum (`admin`, `doctor`, `therapist`, `cashier`) sebagai kolom di tabel `users` (satu user satu peran klinik per tenant, assumption spec). Permission per modul via Laravel `Gate` + `Policy` per model/controller, bukan package `spatie/laravel-permission`.

**Rationale**:
- Konsisten dengan spec 001 yang memakai field `role` enum di User (bukan spatie permission).
- Hanya 4 peran tetap + matriks permission per modul — tidak dinamis, tidak butuh DB-driven permission. Gate matriks di `AuthServiceProvider`/policy = <60 baris, jauh lebih kecil dari spatie overhead.
- Native Laravel Gate/Policy adalah fitur platform (ladder rung 4).

**Permission matriks** (basis FR-002, FR-044, FR-075):

| Modul | Admin | Dokter | Terapis | Kasir |
|-------|-------|--------|---------|-------|
| Staff (US1) | CRUD | — | — | — |
| Service (US2) | CRUD | read | read | — |
| Patient (US3) | R/W | R/W | read | R/W dasar |
| Booking (US4) | R/W | R/W | R/W | R/W |
| MedicalRecord (US7) | R/W | R/W | R/W | — |
| Product (US6) | CRUD | — | — | — |
| Inventory/Stock (US6) | CRUD | — | — | — |
| POS/Transaction (US5) | R/W | — | — | R/W |
| Invoice (US5) | R/W + print | — | — | R/W + print |
| Report (US8) | R/W | — | — | — |

**Alternatives considered**:
- `spatie/laravel-permission` → ditolak: 4 peran statis, tidak butuh RBAC dinamis; overkill + dep baru.
- Role middleware per route → ditolak: tidak granular per aksi (view vs create vs delete), policy lebih tepat.

---

## R3. Foto before/after — validasi & storage (FR-042)

**Decision**: Validasi native Laravel rule (`image`, `mimes:jpg,jpeg,png`, `max:2048` KB) di FormRequest. Simpan file di disk `public` path `medical-photos/{tenant_id}/{record_id}/`. TIDAK pakai `intervention/image` untuk resize di MVP.

**Rationale**:
- Validasi mime + size = native Laravel (ladder rung 4). Cukup untuk FR-042.
- Resize/thumbnail = optimasi, bukan kebutuhan MVP. Storage local disk cukup (assumption spec: "manajemen storage besar/CDN di luar scope MVP").
- Intervention hanya saat ukuran foto jadi masalah (terbukti), bukan pre-emptive.

**Alternatives considered**:
- `intervention/image` untuk resize/compress saat upload → ditolak MVP: add dep, kompleksitas, belum ada kebutuhan terbukti.
- Validasi dimensi piksel → ditolak: over-specific, spec tidak menyebut dimensi, hanya format+ukuran.

**Upgrade path**: Saat foto besar mengganggu, tambah `intervention/image` di `UploadMedicalPhotoAction` untuk resize ke max-width + generate thumbnail.

---

## R4. Invoice — PDF vs HTML print (FR-057)

**Decision**: Invoice = HTML print-view (route `/clinic/transactions/{id}/invoice` render template print-friendly + `window.print()` dari tombol browser). TIDAK pakai `dompdf` di MVP.

**Rationale**:
- FR-057 hanya minta "menerbitkan invoice/struk yang menampilkan detail" — tidak mensyaratkan format file.
- HTML print = native browser (ladder rung 4), 0 dep, 0 kompleksitas.
- `dompdf` = dep berat, rendering berbeda dari browser, ditolak untuk MVP.

**Alternatives considered**:
- `dompdf/laravel-dompdf` atau `barryvdh/laravel-snappy` → ditolak MVP: dep berat, tidak ada requirement PDF eksplisit.
- Generate PDF server-side saat dibutuhkan → ditolak: sama.

**Upgrade path**: Tambah `dompdf` saat user minta download/email PDF invoice.

---

## R5. Tampilan jadwal booking harian/mingguan (FR-032)

**Decision**: Custom `schedule-grid.tsx` — CSS grid sederhana (baris = slot waktu, kolom = penanggung jawab atau hari), data dari endpoint list booking terfilter tanggal. `date-fns` (sudah terinstall) untuk formatting tanggal/jam. TIDAK pakai lib kalender eksternal.

**Rationale**:
- Data jadwal = list booking dengan filter rentang tanggal, sudah dicakup endpoint `BookingController@index`. View = presentasi saja.
- Lib kalender (`react-big-calendar`, `fullcalendar`) = dep besar, API opinionated, overkill untuk 200 booking/bulan (SC-008).
- CSS grid + map data = komponen <300 baris, reuse `ui/card` + `ui/badge` yang ada (ladder rung 2 + 4).

**Alternatives considered**:
- `react-big-calendar` → ditolak: add dep berat + dependensi moment/dayjs, kustomisasi tema sulit.
- Tabel HTML biasa → opsi cadangan; grid Tailwind lebih fleksibel untuk responsif.

**Upgrade path**: Pindah ke lib kalender kalau butuh drag-and-drop reschedule atau view month/agenda kompleks.

---

## R6. Harga historik transaksi (FR-056, FR-012 edge case)

**Decision**: Snapshot harga disimpan di `transaction_items.unit_price` (decimal) saat transaksi dibuat. `transaction_items` menyimpan relasi `service_id`/`product_id` (nullable, untuk arsip) + `name` snapshot + `unit_price` snapshot + `qty`. Subtotal = `sum(unit_price * qty)`.

**Rationale**:
- FR-056 eksplisit: harga historik wajib disimpan saat transaksi.
- Snapshot `name` juga (selain `unit_price`) agar invoice/laporan tetap menampilkan nama meski master diarsip/diubah (FR-066, edge case hapus produk).
- Laporan omzet (FR-070) = `sum(unit_price * qty)` dari transaction_items transaksi lunas — tidak perlu join ulang ke master.

**Alternatives considered**:
- Hanya relasi ke master, hitung ulang harga saat tampil → ditolak: melanggar FR-056, harga berubah → laporan lampau rusak.
- Tabel price history terpisah per master → ditolak: over-engineering, snapshot di line item sudah cukup & lazim.

---

## R7. Stok real-time & rollback (FR-052, FR-053, FR-058, FR-063)

**Decision**: Saldo stok = derived value, BUKAN kolom yang di-increment sembarangan. Implementasi:
- `products.stock_balance` (integer, default 0) = satu-sumber-kebenaran saldo saat ini, diupdate via `StockService` saja.
- Setiap perubahan wajib membuat record `stock_movements` (type: `in`, `out_manual`, `sold_pos`, `rollback`) + kuantitas + keterangan + `related_type`/`related_id` (morph ke Transaction untuk `sold_pos`/`rollback`).
- `StockService::adjust(product, type, qty, related)` = satu pintu: DB transaction, insert movement, update `stock_balance`.
- Penjualan POS: `sold_pos` dipanggil di `TransactionService` dalam DB transaction yang sama dengan insert transaction_items. Validasi stok cukup (FR-053) cek `stock_balance >= qty` sebelum insert.
- Pembatalan transaksi (FR-058): `CancelTransactionAction` panggil `StockService::adjust(..., 'rollback', ...)` per item produk.

**Rationale**:
- FR-063: "saldo = awal + masuk − keluar-manual − terjual-POS". Derived value konsisten jika semua mutasi lewat satu pintu.
- DB transaction + single service = mencegah saldo drift (SC-004 akurasi 100%, SC-005 rollback tanpa selisih).
- `stock_balance` sebagai kolom (bukan pure aggregate query) = baca cepat untuk tampilan list produk & cek stok POS; konsistensi dijaga via service + DB transaction. `ponytail: stock_balance kolom denormalized, bukan source of truth movement; konsistensi dijaga StockService + DB transaction, reconcile job bisa ditambah kalau drift terdeteksi`.

**Alternatives considered**:
- Hitung saldo via `sum(movements)` setiap query → ditolak: lambat untuk list produk + cek stok POS real-time (SC-008).
- Trigger DB → ditolak: logic bisnis tersembunyi di DB, susah test, melanggar pola service layer CLAUDE.md.
- Event/listener async → ditolak: stok harus konsisten saat transaksi commit, async = race condition.

**Upgrade path**: Job reconcile harian (`stock_balance` vs `sum(movements)`) kalau ada indikasi drift; lock per-produkt saat throughput tinggi.

---

## R8. Deteksi bentrok slot booking (FR-035, clarification session)

**Decision**: Saat store/update booking, query booking lain dengan `assignee_id` sama + rentang waktu beririsan (`start < other.end AND end > other.start`) + status bukan `cancelled`. Jika ada → response tetap 201/200 tetapi menyertakan flag `overlap_warnings` (array booking bentrok). Frontend tampilkan peringatan (toast/dialog) TIDAK memblokir simpan.

**Rationale**:
- Clarification session 2026-07-06 eksplisit: "Peringatan saja... tidak memblokir penyimpanan".
- Validasi (block) vs peringatan (warn) berbeda: ini warning pasca-validasi, jadi tidak di FormRequest rule. Flag di response JSON = cara paling lazy.

**Alternatives considered**:
- Block di FormRequest → ditolak: melanggar clarification.
- Frontend-only cek via fetch tambahan → ditolak: duplikasi logic, race condition; backend tetap harus tahu (audit).

---

## R9. Satu booking = satu layanan utama (edge case spec)

**Decision**: Tabel `bookings` punya `service_id` (single FK). Kunjungan dengan banyak layanan → multiple booking atau ditangani via transaction_items POS multi-item (sesuai catatan edge case spec). MVP: satu booking satu layanan.

**Rationale**: Edge case spec eksplisit menyatakan ini pilihan implementasi; single FK paling sederhana.

**Alternatives considered**: Junction `booking_service` → ditolak MVP: kompleksitas, tidak ada FR yang minta banyak layanan per booking.

---

## R10. Rekam medis vs treatment record (FR-040, FR-041)

**Decision**:
- `medical_records` = 1 per booking (unique `booking_id`), berisi SOAP 4 kolom text.
- `treatment_records` = many per `medical_record`, tiap row acu `service_id` + `notes` klinis. (Diperlukan karena FR-041 "mencatat treatment yang dilakukan" — bisa lebih dari satu per kunjungan meski booking single-service; tindakan tambahan di rekam medis bisa beda dari service booking.)
- `medical_photos` = many per `medical_record`, kolom `type` (`before`/`after`) + `path`.

**Rationale**: Pisah karena lifetime & kardinalitas berbeda; mengikuti entitas di spec Key Entities.

**Alternatives considered**: Satu tabel flat → ditolak: repetitive SOAP, melanggar normalisasi.

---

## R11. Tenant timezone untuk laporan (assumption spec)

**Decision**: Rentang tanggal laporan = `Carbon::parse($request->from/to)->startOfDay()` di timezone app config (`config('app.timezone')` = lokal tenant). Query `whereBetween('transactions.created_at', [...])`. MVP: timezone tunggal per app (tidak per-tenant timezone column).

**Rationale**: Spec assumption: "Rentang tanggal laporan mengikuti zona waktu lokal tenant". MVP single-deploy per tenant → timezone app = tenant. Per-tenant timezone column = kompleksitas di luar MVP.

**Alternatives considered**: Kolom `timezone` di tenants + konversi per query → ditolak MVP.

**Upgrade path**: Tambah `tenants.timezone` saat multi-region deploy.

---

## R12. Form pattern frontend — modal vs halaman (CLAUDE.md)

**Decision** (mengikuti CLAUDE.md form rule, dipetakan per modul):
- **Modal (≤5 field)**: Service (nama, deskripsi, harga, status), Product (nama, satuan, stock awal, min threshold, status), StockMovement (produk, type, qty, keterangan), Booking (pasien, layanan, assignee, tanggal/jam — 4-5 field), Payment (metode, jumlah).
- **Halaman (>5 field / logic berat)**: Patient (nama, tanggal lahir, jenis kelamin, telepon, WA, alamat — 6 field), MedicalRecord (SOAP 4 field + treatment records repeatable + photo uploader), POS Transaction (multi-item line + pembayaran + validasi stok), Report (filter + multiple views).

**Rationale**: CLAUDE.md mandatory; react-hook-form + zod via `ui/form.tsx` + `ui/field.tsx` yang sudah ada.

**Alternatives considered**: Semua modal → ditolak: melanggar CLAUDE.md.

---

## Ringkasan dependency baru spec 002

Setelah research: **TIDAK ADA dependency baru yang wajib** untuk MVP. Semua kebutuhan tercakup oleh:
- Native Laravel (validation, Gate/Policy, DB transaction, filesystem).
- Komponen frontend yang sudah terinstall (react-hook-form, zod, tanstack, shadcn, date-fns, recharts opsional).
- Komponen yang sudah ada di repo (`components/datatable/`, `components/ui/*`).

Dep yang ditunda (upgrade path bila dibutuhkan): `intervention/image` (R3), `dompdf` (R4). Dep warisan spec 001: `spatie/laravel-activitylog`.

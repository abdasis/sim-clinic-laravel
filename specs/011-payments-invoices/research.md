# Research: Integritas Item Transaksi, Pembayaran Cicilan & Cetak Invoice

**Date**: 2026-08-14 | **Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

## Konteks eksplorasi

Eksplorasi langsung ke codebase `apps/api` + `apps/web` mengonfirmasi bahwa backend inti modul POS sudah ada (dari spec 008). Research ini mendokumentasikan keputusan teknis untuk tiga celah yang tersisa, bukan riset eksplernal. Tidak ada `[NEEDS CLARIFICATION]` tersisa — semua keputusan di-ground di ERD (`docs/erd/`), normalisasi (`docs/normalization/`), spec 008, dan codebase nyata.

## Temuan: status implementasi eksisting

### Backend (sudah ada — tidak ditulis ulang)

- **`PayTransactionAction`** (`apps/api/app/Actions/Transaction/PayTransactionAction.php`): sudah sync `paid_amount` akumulatif + set `payment_status` 3-state dalam `DB::transaction` + `lockForUpdate` + audit naratif "Mencatat pembayaran {invoice_number} — status {lama} menjadi {baru}".
- **`PaymentController@store`**: authorize via `update` TransactionPolicy + `PayTransactionAction` + return `TransactionResource` + meta `{ payment_status, overpaid, message }`.
- **`PaymentRequest`**: validasi `method` (Enum), `amount` (gt:0), `paid_at` (date).
- **`PaymentResource`**: expose payment fields.
- **`TransactionController@show`**: `load('items', 'patient', 'payments')` — sudah sertakan relasi payments.
- **`TransactionResource`**: expose `paid_amount`, `outstanding_amount`, `payment_status`+label, `issued_at`, `payments` (whenLoaded), `items` (whenLoaded).
- **`InvoiceController@show`** + **`InvoiceService::render`**: render dari relasi (`loadMissing('items','payments','patient','cashier')`) — R4 sudah di backend. View blade `invoice.blade.php` punya section `.payments` + tombol print + `@media print`.
- **`TransactionRequest`**: app validation exclusive arc `items.*.service_id` `required_without:product_id` + `prohibits:product_id`.

### Backend gap (dikerjakan spec 011)

- **Migration `transaction_items`**: belum ada CHECK constraint exclusive arc. FK `product_id`/`service_id` masih `nullOnDelete` — perlu `restrictOnDelete`. Index sudah benar.

### Frontend (sudah ada — reuse)

- **`components/forms/`**: `FormCombobox` (searchable select + RHF), `FormSelect`, `FormInput`, `FormSubmit`, `useForm` (zodResolver wrapper), `applyServerErrors` (map 422 → RHF error).
- **`components/datatable/`**: `DataTable`, `FacetedFilter`, `Pagination`, `Toolbar`, `ColumnHeader`, `ViewOptions` — lengkap.
- **`components/ui/status-badge.tsx`**: `StatusBadge` + `PAYMENT_STATUS_VARIANTS` (unpaid=destructive, partially_paid=outline, paid=default).
- **`lib/format.ts`**: `formatCurrency`.
- **`components/clinic-breadcrumb.tsx`**: `ClinicBreadcrumb`.
- **`pos/components/payment-panel.tsx`**: input pembayaran TUNGGAL saat create transaksi (inline di `pos/index.tsx`). Bukan halaman cicilan bertahap untuk transaksi existing.
- **`pos/invoices/$id.tsx`**: render header + items + total + tombol print. **Gap**: tidak render section pembayaran (`InvoiceData` interface tidak declare `payments`, tidak ada section payments). R4 belum lengkap di FE.

### Frontend gap (dikerjakan spec 011)

- Tidak ada route detail transaksi (`pos/transactions/$id`) — hanya list `pos/transactions.tsx`.
- Tidak ada halaman untuk catat cicilan bertahap pada transaksi existing + lihat riwayat pembayaran.
- Invoice FE tidak render payments.

## Keputusan teknis

### D-1: CHECK constraint exclusive arc — DB-level enforcement

**Decision**: Tambah migration `CHECK ((product_id IS NULL) <> (service_id IS NULL))` pada `transaction_items`.

**Rationale**: App validation (`TransactionRequest`) sudah ada sebagai pertahanan UX (422), tapi rentan bypass lewat seed/job/bug. CHECK constraint = pertahanan integritas data (anomali #1 normalisasi). PostgreSQL mendukung CHECK expression. SQLite (test default) juga mendukung CHECK — jadi test sqlite bisa verifikasi. Anomali #1 eksplisit direkomendasikan CHECK di `docs/normalization/README.md`.

**Alternatives considered**:
- Morph single (`item_type` + `item_id` + `item_class`): lebih normal tapi query laporan jadi polimorfik — trade-off tidak sepadan untuk MVP (normalisasi README menolak).
- Hanya app validation: tidak menutup celah non-UI — risiko laporan FR-071/072 meleset.

### D-2: FK restrict product_id/service_id — ganti nullOnDelete

**Decision**: Migration alter FK `product_id`/`service_id` dari `nullOnDelete` → `restrictOnDelete`.

**Rationale**: Master produk/layanan diarsip (`status=archived`), bukan hapus (FR-013/066). Hapus permanen master yang masih dirujuk item historik harus diblokir agar snapshot + integritas laporan terjaga. ERD `transaction_items.md` menyatakan `restrictOnDelete`. `transaction_id` tetap `cascadeOnDelete` (child admin). Alter FK di PostgreSQL = drop+recreate constraint. SQLite tidak support drop FK → guard driver (skip di sqlite, jalankan via `phpunit.pgsql.xml`).

**Alternatives considered**:
- Tetap `nullOnDelete`: item kehilangan rujukan master → riwayat transaksi rusak. Ditolak.
- `cascadeOnDelete`: hapus master menghapus item historik → laporan omzet hilang. Ditolak.

### D-3: Invariant tenant — create via relasi (anomali #3)

**Decision**: Pertahankan app-level enforcement: child create via `$transaction->items()->create()` / `$transaction->payments()->create()` → `tenant_id` inherit. Tambah test assert child tidak bisa lintas-tenant.

**Rationale**: `BelongsToTenant` trait + `TenantScope` sudah aktif. `$transaction->items()->create()` otomatis isi `tenant_id` dari parent. DB-level CHECK subquery (tenant_id = parent tenant_id) tidak didukung PostgreSQL CHECK (no subquery). `ponytail: DB-level CHECK/trigger add saat audit keamanan tenant berikutnya` (normalisasi README). Pragmatic MVP = app enforcement + test.

**Alternatives considered**:
- DB trigger: kompleks, over-engineering untuk MVP. Ditolak.
- CHECK subquery: tidak didukung. Ditolak.

### D-4: Halaman detail transaksi + cicilan — route baru `pos/transactions/$id`

**Decision**: Buat route file-based `pos/transactions/$id.tsx` — halaman detail transaksi: header (invoice_number, pasien, status badge, subtotal/paid/outstanding), form catat pembayaran (`payment-form.tsx`), riwayat pembayaran (`payment-history.tsx`). POST ke `/{tenant}/clinic/transactions/{id}/payments` (endpoint sudah ada). Mutasi via TanStack Query invalidasi.

**Rationale**: Workflow langkah 12 FE minta "halaman bayar + multi-cicilan + breadcrumb". `payment-panel.tsx` eksisting = input tunggal inline saat create, bukan cicilan bertahap untuk transaksi existing. Pisah ke halaman detail karena >5 interaksi (lihat detail + catat cicilan + lihat riwayat + lihat sisa + print invoice) — sesuai aturan form design CLAUDE.md (halaman terpisah untuk logic berat). Reuse `FormSelect` (method) + `FormInput` (amount) + `FormDatePicker` (paid_at?) atau `FormInput` type date + `useForm`/zod + `applyServerErrors` + `StatusBadge` + `formatCurrency` + `ClinicBreadcrumb`.

**Alternatives considered**:
- Modal bayar: multi-cicilan + riwayat = logic berat, melanggar aturan modal (≤5 field / no complex logic). Ditolak.
- Edit `payment-panel.tsx`: tanggung jawab berbeda (inline create vs cicilan existing). Ditolak.

### D-5: Form catat pembayaran — field `paid_at`

**Decision**: `paid_at` default = hari ini (datetime-local), kasir bisa ubah. Pakai `FormInput` type `datetime-local` atau `FormDatePicker` bila mendukung time. Validasi zod: required, valid date, tidak di masa depan berlebihan (opsional).

**Rationale**: `paid_at` dipakai laporan omzet per periode (FR-070). Kasir umumnya catat pembayaran saat terjadi → default hari ini. `PaymentRequest` backend sudah validasi `date`. Sederhana — tidak butuh date-time picker library (YAGNI).

**Alternatives considered**:
- Auto-set `paid_at = now()` di backend: kasir tidak bisa catat pembayaran retroaktif (mis. catat kemarin). Kurang fleksibel. Ditolak.
- Date picker library: YAGNI, native input cukup.

### D-6: Invoice FE render payments — edit `pos/invoices/$id.tsx`

**Decision**: Tambah `payments` ke `InvoiceData` interface + render section pembayaran (metode, jumlah, waktu, total paid, outstanding) sebelum/after total. Data sudah tersedia dari `GET transactions/{id}` (load payments).

**Rationale**: R4 (ERD `invoices.md`) — konten invoice dari relasi termasuk payments. Backend `InvoiceService::render` sudah sertakan payments, blade view juga. Tapi FE `invoices/$id.tsx` hanya render items + total. Lengkapi FE agar konsisten dengan R4. Reuse `formatCurrency`. SC-005 butuh 100% akurat.

**Alternatives considered**:
- Hanya render di blade view (server-side print): FE invoice page juga punya tombol print sendiri (`window.print()`) — harus lengkap. Ditolak untuk hanya blade.

### D-7: Komponen reusable di `forms/` / `datatable/` — reuse, tidak buat baru

**Decision**: Tidak buat komponen reusable baru di `components/forms/` atau `components/datatable/` — yang ada sudah cukup. `payment-form.tsx` + `payment-history.tsx` = komponen domain colocated di `pos/components/` (bukan reusable cross-feature).

**Rationale**: Instruksi user: "gunakan yang sudah ada jika butuh di-improve dan buat component reusable di 2 folder tersebut". Eksplorasi menunjukkan `forms/` (FormCombobox/FormSelect/FormInput/FormDatePicker/useForm) + `datatable/` (lengkap) sudah cover kebutuhan. Tidak ada gap reusable yang muncul dari 3 celah ini. Bila saat implementasi ditemukan pola reusable cross-feature (mis. amount-input dengan format currency), baru extract ke `forms/` — tapi YAGNI sekarang.

**Alternatives considered**:
- Preemptive buat `AmountInput` reusable: belum ada 2+ konsumen nyata (constitution IV — abstraksi butuh minimal 2 konsumen). Ditolak sekarang.

### D-8: Test strategy

**Decision**: Test oleh `zahiira` (Pest/PHPUnit):
- **DB CHECK test** (PostgreSQL via `phpunit.pgsql.xml`): insert item product_id+service_id terisi → DB reject; keduanya null → reject; tepat satu → OK.
- **FK restrict test** (PostgreSQL): hapus product yang dirujuk item → DB restrict; arsip → OK.
- **Tenant invariant test**: create item via `$transaction->items()->create()` → tenant_id == transaction tenant_id; upaya lintas-tenant ditolak scope.
- **Cicilan flow test** (Feature): 3 pembayaran parsial subtotal 300rb → paid_amount akumulatif + status unpaid→partially_paid→paid; overpaid → meta overpaid=true.
- **Invoice render test** (FE vitest bila feasible, atau manual quickstart): invoice page tampilkan items + payments.

**Rationale**: Constitution II (TDD). Constraint test hanya berjalan di PostgreSQL (SQLite skip FK alter + CHECK subquery tidak relevan). Cicilan flow test bisa sqlite. Paralel dengan implementasi.

**Alternatives considered**:
- Hanya test sqlite: constraint restrict + CHECK tidak teruji → risiko produksi. Ditolak.
- Test manual saja: melanggar constitution II. Ditolak.
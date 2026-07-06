# Implementation Plan: MVP Sistem Klinik Kecantikan

**Branch**: `002-beauty-clinic-mvp` | **Date**: 2026-07-06 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/002-beauty-clinic-mvp/spec.md`

## Summary

8 modul operasional klinik kecantikan di atas fondasi multi-tenant spec 001: peran klinik (Admin/Dokter/Terapis/Kasir) + permission per modul, master layanan & produk, manajemen pasien + riwayat, booking & jadwal dengan deteksi bentrok (peringatan, bukan block), rekam medis SOAP + foto before/after, POS multi-item + stok real-time + invoice, inventory dengan pergerakan stok, dan laporan omzet/penjualan. Backend Laravel 13 (`apps/api`) — model `BelongsToTenant`, service/action untuk orkestrasi (POS, stok, overlap booking), policy per modul. Frontend React 19 (`apps/web`) — reuse `components/datatable/` (TanStack Table) + `components/ui/form.tsx` + `components/ui/field.tsx` (react-hook-form + zod) yang sudah ada; form ≤5 field modal, >5 field halaman; i18n `lang/id/*.php`; breadcrumb wajib.

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 13.8 (backend `apps/api`); TypeScript + React 19 (frontend `apps/web`).

**Primary Dependencies**:
- Backend (sudah): `laravel/framework ^13.8`, `laravel/sanctum ^4.0`, `laravel/tinker ^3.0`.
- Backend warisan spec 001 (prasyarat, belum terinstall): `spatie/laravel-activitylog` (audit log).
- Backend baru spec 002 (per research Phase 0): validasi & resize foto before/after — `intervention/image` (NEEDS CLARIFICATION: cukup validasi mime+size native Laravel rule tanpa resize, atau intervention untuk resize+compress). Invoice PDF — `dompdf/laravel-dompdf` atau cukup HTML print-view (NEEDS CLARIFICATION, MVP condong HTML print). Export laporan — di luar scope MVP (view saja).
- Frontend (sudah terinstall): `@tanstack/react-router`, `@tanstack/react-query`, `@tanstack/react-table ^8.21.3`, `@shadcn/react`, `react-hook-form ^7.81.0`, `@hookform/resolvers`, `zod ^4.4.3`, `sonner`, `tailwindcss ^4`, `date-fns ^4`, `react-day-picker ^10`, `recharts ^3` (untuk grafik laporan opsional), `lucide-react` (icon — catatan: spec 001 rencana `@hugeicons/react` tapi belum terinstall, MVP pakai `lucide-react` yang sudah ada).
- Komponen yang dipakai ulang (per user input): `apps/web/src/components/datatable/*` (6 file: datatable, toolbar, pagination, column-header, faceted-filter, view-options) + `apps/web/src/components/ui/form.tsx` + `field.tsx` + seluruh shadcn `ui/*` (input, select, dialog, sheet, dropdown-menu, badge, card, calendar, command, combobox, table, dll).

**Storage**: Single shared database (SQLite dev, Postgres/MySQL produksi) — semua tabel bisnis punya `tenant_id`. File foto before/after di `storage/app/public/medical-photos/{tenant}/` (local disk, disk `public`). Catatan: spec 001 konstitusi belum ratifikasi → tidak ada constraint governance.

**Testing**: PHPUnit 12 (`php artisan test`) backend; Vitest 4 (`bun run test`) frontend. Policy & service wajib feature test (SC-009: 100% akses terlarang ditolak).

**Target Platform**: Web (browser). Backend Laravel SPA-API + Frontend TanStack Start SSR.

**Project Type**: Web service (API) + SPA frontend (monorepo `apps/api`, `apps/web`).

**Performance Goals**: Jadwal harian responsif untuk tenant 50 pasien aktif + 200 booking/bulan (SC-008). Pendaftaran pasien + booking <3 menit (SC-002). Transaksi POS lengkap <2 menit (SC-003). Akurasi stok 100% (SC-004, SC-005).

**Constraints**: Class PHP ≤300 baris, method ≤100 baris (CLAUDE.md). File React ≤300 baris. i18n wajib `__()` / `t()` dari `lang/id/*.php`. Breadcrumb wajib setiap halaman. Form ≤5 field → modal, >5 field → halaman. Komentar Indonesia, hanya untuk logika rumit. Folder/file frontend kebab-case English. Tidak ada emoji di kode/komentar/commit.

**Scale/Scope**: MVP per-tenant. 8 modul, ~13 entitas bisnis baru di atas tenant/user spec 001. Luar scope: retur barang, hutang/piutang, multi-currency, PPN, notifikasi otomatis (SMS/WA), gateway pembayaran real-time, master global lintas-tenant, multi-peran simultan per user.

**Dependency kritis**: Spec 002 dibangun di atas spec 001 (tenant, `BelongsToTenant`, `TenantScope`, auth dasar, manajemen user, audit log). Faktanya implementasi spec 001 **belum ada** di repo — `apps/api/app/Models/User.php` masih default (tidak ada `tenant_id`/`role`), tidak ada `Tenant` model, tidak ada `BelongsToTenant` trait, tidak ada middleware `ResolveTenant`, tidak ada `lang/id/*`. NEEDS CLARIFICATION (research): apakah spec 001 diimplementasikan dulu sebelum spec 002, atau spec 002 menarik dependensi minimum yang dibutuhkan dari spec 001.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

`.specify/memory/constitution.md` masih placeholder (belum diratifikasi — semua field `[PRINCIPLE_X_*]` kosong). Tidak ada prinsip konkret untuk dievaluasi → tidak ada gate yang dilanggar. Re-check post-design: tetap tidak ada prinsip konkret → gate lolos.

**Catatan**: Constitution kosong berarti tidak ada constraint governance. Disarankan user ratifikasi constitution sebelum implementasi, tapi tidak memblokir plan. CLAUDE.md (aturan teknis global: ukuran file, i18n, breadcrumb, form pattern, naming) tetap berlaku sebagai constraint teknis dan dimasukkan ke Constraints di atas.

## Project Structure

### Documentation (this feature)

```text
specs/002-beauty-clinic-mvp/
├── plan.md              # file ini
├── research.md          # Phase 0
├── data-model.md        # Phase 1
├── quickstart.md        # Phase 1
├── contracts/
│   └── api-contracts.md # Phase 1
└── tasks.md             # Phase 2 (/speckit-tasks, belum dibuat)
```

### Source Code (repository root)

```text
apps/
├── api/                          # Laravel 13 backend
│   ├── app/
│   │   ├── Enums/
│   │   │   ├── ClinicRole.php            # admin, doctor, therapist, cashier
│   │   │   ├── BookingStatus.php         # pending, confirmed, done, cancelled
│   │   │   ├── PaymentMethod.php         # cash, transfer, qris, debit
│   │   │   ├── PaymentStatus.php         # unpaid, paid
│   │   │   ├── StockMovementType.php     # in, out_manual, sold_pos
│   │   │   └── ServiceStatus.php         # active, archived
│   │   ├── Models/
│   │   │   ├── (warisan spec 001: Tenant, User, Invitation, Activity)
│   │   │   ├── Service.php               # master layanan/treatment
│   │   │   ├── Patient.php               # pasien
│   │   │   ├── Booking.php               # janji temu
│   │   │   ├── MedicalRecord.php         # rekam medis SOAP
│   │   │   ├── TreatmentRecord.php       # treatment aktual per kunjungan
│   │   │   ├── MedicalPhoto.php          # foto before/after
│   │   │   ├── Product.php               # master produk
│   │   │   ├── StockMovement.php         # pergerakan stok
│   │   │   ├── Transaction.php           # penjualan POS
│   │   │   ├── TransactionItem.php       # item line (snapshot harga historik)
│   │   │   ├── Payment.php               # pencatatan pembayaran
│   │   │   └── Invoice.php               # dokumen transaksi
│   │   ├── Concerns/
│   │   │   └── BelongsToTenant.php       # warisan spec 001
│   │   ├── Scopes/
│   │   │   └── TenantScope.php           # warisan spec 001
│   │   ├── Http/
│   │   │   ├── Middleware/
│   │   │   │   └── (warisan spec 001: ResolveTenant, EnsureTenantActive)
│   │   │   ├── Controllers/
│   │   │   │   ├── StaffController.php           # manajemen staf + peran (US1)
│   │   │   │   ├── ServiceController.php         # master layanan (US2)
│   │   │   │   ├── PatientController.php         # pasien + riwayat (US3)
│   │   │   │   ├── BookingController.php         # booking + jadwal (US4)
│   │   │   │   ├── MedicalRecordController.php   # rekam medis SOAP + foto (US7)
│   │   │   │   ├── ProductController.php         # master produk (US6)
│   │   │   │   ├── StockMovementController.php   # stok masuk/keluar (US6)
│   │   │   │   ├── TransactionController.php     # POS (US5)
│   │   │   │   ├── PaymentController.php         # pembayaran (US5)
│   │   │   │   ├── InvoiceController.php         # invoice/struk (US5)
│   │   │   │   └── ReportController.php          # laporan (US8)
│   │   │   ├── Requests/                # FormRequest per modul (store/update)
│   │   │   └── Resources/               # API Resource per entitas
│   │   ├── Services/
│   │   │   ├── BookingOverlapService.php    # deteksi bentrok slot (FR-035)
│   │   │   ├── TransactionService.php       # orkestrasi POS: items + stok + harga historik
│   │   │   ├── StockService.php             # saldo real-time + rollback (FR-058, FR-063)
│   │   │   ├── InvoiceService.php           # generate invoice + total
│   │   │   └── ReportService.php            # agregasi omzet + penjualan (FR-070..074)
│   │   ├── Actions/
│   │   │   ├── CancelTransactionAction.php  # rollback stok (FR-058)
│   │   │   ├── PayTransactionAction.php     # ubah status lunas (FR-055)
│   │   │   ├── ArchiveServiceAction.php     # arsip layanan/produk (FR-013, FR-066)
│   │   │   └── UploadMedicalPhotoAction.php # simpan + validasi foto (FR-042)
│   │   └── Policies/                        # Gate per modul (FR-002, FR-044, FR-075)
│   │       ├── PatientPolicy.php
│   │       ├── BookingPolicy.php
│   │       ├── MedicalRecordPolicy.php
│   │       ├── TransactionPolicy.php
│   │       ├── ProductPolicy.php
│   │       ├── ServicePolicy.php
│   │       ├── StaffPolicy.php
│   │       └── ReportPolicy.php
│   ├── database/
│   │   ├── migrations/              # 13 tabel bisnis baru + kolom role klinik di users
│   │   ├── factories/               # per model
│   │   └── seeders/                 # ClinicDemoSeeder (4 staf + pasien + layanan + produk)
│   ├── routes/
│   │   ├── api.php                  # tambah grup /{tenant}/clinic/* dengan policy middleware
│   │   └── web.php
│   └── lang/id/
│       ├── auth.php  general.php  validation.php     # warisan + tambah
│       ├── staff.php  service.php  patient.php       # per modul
│       ├── booking.php  medical-record.php           # per modul
│       ├── product.php  inventory.php                # per modul
│       ├── pos.php  invoice.php  report.php          # per modul
│       └── clinic.php                                 # enum label peran/status
└── web/                          # React 19 frontend (TanStack Start)
    └── src/
        ├── components/
        │   ├── datatable/          # SUDAH ADA, reuse untuk list semua modul
        │   ├── ui/                 # SUDAH ADA (form, field, input, select, dialog, dll)
        │   ├── forms/              # BARU (saat implement): wrapper tipis di atas ui/form.tsx
        │   │   └── (form-input, form-select, form-textarea, form-submit, use-form)
        │   ├── schedule/           # BARU: grid jadwal harian/mingguan (FR-032)
        │   │   └── schedule-grid.tsx
        │   └── medical-photos/     # BARU: uploader before/after (FR-042)
        │       └── photo-uploader.tsx
        ├── routes/
        │   ├── $tenant/            # tenant-scoped (warisan spec 001: login, dashboard, users)
        │   └── $tenant/clinic/     # BARU: 8 modul
        │       ├── staff/          # datatable + form peran (US1)
        │       ├── services/       # datatable + modal form (≤5 field, US2)
        │       ├── patients/       # datatable + page form (>5 field) + [id]/history (US3)
        │       ├── bookings/       # form + schedule-grid + status action (US4)
        │       ├── medical-records/# page form SOAP + photo-uploader (>5 field, US7)
        │       ├── products/       # datatable + modal form (US6)
        │       ├── inventory/      # datatable + stock movement form (US6)
        │       ├── pos/            # page transaksi multi-item + pembayaran + invoice (US5)
        │       └── reports/        # filter tanggal + tabel/recharts (US8)
        ├── hooks/
        │   ├── use-trans.ts        # t() dari usePage().props.translations (warisan spec 001)
        │   └── use-data-table.ts   # SUDAH ADA di lib/, reuse
        └── utils/
            └── trans.ts            # helper t() (warisan spec 001)
```

**Structure Decision**: Monorepo `apps/api` (Laravel) + `apps/web` (TanStack Start). Backend: 13 model `BelongsToTenant` baru + enum untuk role/status + service/action orkestrasi (POS, stok, overlap) + policy per modul (class ≤300, method ≤100). Frontend: reuse `components/datatable/` + `components/ui/form.tsx` + `field.tsx` yang sudah ada untuk semua list + form; route `$tenant/clinic/*` untuk 8 modul; buat wrapper tipis `components/forms/` (rencana spec 001 belum terealisasi) saat implementasi pertama kalinya agar form konsisten; `schedule/` dan `medical-photos/` sebagai komponen khusus modul. i18n via `lang/id/*.php` + `t()` helper.

## Complexity Tracking

> Tidak ada violation constitution (constitution kosong). Tabel tidak diisi.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| - | - | - |

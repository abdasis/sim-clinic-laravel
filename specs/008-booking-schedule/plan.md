# Implementation Plan: Booking & Jadwal Klinik

**Branch**: `main` | **Date**: 2026-08-14 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/008-booking-schedule/spec.md`

## Summary

Fitur booking & jadwal **sudah terimplementasi sebagian besar** — backend lengkap (controller, service, actions, form requests, policy, resource, enum transisi status, overlap service, migration, routes) dan frontend dasar (jadwal harian/mingguan + form create + aksi status). Spec ini adalah **revisi/penyempurnaan** untuk memenuhi AC yang belum terpenuhi, bukan greenfield. Pendekatan teknis: (1) migration ubah FK `patient_id`/`service_id` menjadi `restrictOnDelete` (satu FK sudah restrict), (2) tegakkan immutability `patient_id` saat rekam medis ada di `BookingRequest`/`UpdateBookingAction` → 422 (Anomali #2 normalisasi), (3) FE refactor form menjadi dialog create+edit reuse + nonaktifkan field pasien bila rekam medis ada + indikator bentrokan + perbaikan breadcrumb self-link. Semua komponen form/datatable reusable yang sudah ada dipakai; **tidak ada komponen form baru**.

## Technical Context

**Language/Version**: PHP 8.3+ (backend), TypeScript (frontend TanStack Start / React 19)

**Primary Dependencies**: Laravel 13 API, Sanctum token auth, spatie/laravel-permission (role dinamis), spatie/laravel-activitylog (audit), TanStack Router + Query, react-hook-form + zod, shadcn/ui (radix-nova), date-fns. Semua sudah terpasang.

**Storage**: PostgreSQL (prod), SQLite (test `phpunit.xml` :memory:). Catatan: beberapa migration FK restrict di-skip pada SQLite (rebuild tabel) — skema produksi PostgreSQL tetap RESTRICT.

**Testing**: PHPUnit/Pest (backend, `php artisan test`), Vitest (frontend, `bun run test`). Delegasi BE test → agent `zahiira`.

**Target Platform**: Web (Laravel API port 8000 + TanStack Start port 3001)

**Project Type**: Web application (monorepo `apps/api` + `apps/web`)

**Performance Goals**: SC-008/SC-013 — deteksi bentrokan & tampilan jadwal <1 detik @500 booking/klinik/minggu.

**Constraints**: Method PHP ≤100 baris, class ≤300 baris (konstitusi V). Form modal ≤5 field tanpa logika kompleks (CLAUDE.md). Booking = 6 field (pasien, layanan, assignee, start, end, notes) → **masih dalam modal** (5 field inti + notes nullable; notes sederhana, bukan logika kompleks). Komponen FE ≤300 baris.

**Scale/Scope**: Multi-tenant, ratusan booking per klinik. Tidak ada entitas/tabel baru — murni revisi `bookings` + relasi + FE.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Prinsip | Status | Catatan |
|---------|--------|--------|
| I. Clean Code | PASS | Reuse komponen/Action eksisting; tidak ada abstraksi baru tanpa 2 konsumen. Immutability ditegakkan di lapis validasi (satu titik). |
| II. TDD | PASS | zahiira tulis test lebih dulu/bersamaan per task (feature + unit). FK restrict, immutability, transisi status, overlap — semua testable. |
| III. Multi-Tenant Isolation | PASS | `BelongsToTenant` + `TenantScope` sudah aktif; booking tenant-scoped; overlap query otomatis scoped. Tidak ada celah lintas-tenant. |
| IV. Simplicity (YAGNI) | PASS | Tidak ada komponen form baru (semua tercover); tidak ada endpoint baru; tidak ada entitas baru. Hard-delete booking tidak diekspos (status cancelled cukup). |
| V. Bounded Size | PASS | Semua file target ≤300 baris. Form-dialog create+edit tetap satu file <300. BookingController 144 baris (OK). |
| VI. Permission & Activity Log | PASS | Role dinamis spatie/laravel-permission (`booking.view`/`booking.manage` via MATRIX `SyncTenantClinicRolesAction`). Audit log via `LogAuditAction` (spatie wrapper) — sudah naratif status lama→baru. Tambah `booking.updated` diff old/new untuk perubahan pasien/field. |

Tidak ada pelanggaran konstitusi — Complexity Tracking tidak perlu diisi.

## Project Structure

### Documentation (this feature)

```text
specs/008-booking-schedule/
├── plan.md              # This file
├── research.md          # Phase 0 — audit gap + decisions
├── data-model.md        # Phase 1 — Booking entity revision
├── quickstart.md        # Phase 1 — validation guide
├── contracts/
│   └── bookings-api.md  # Phase 1 — endpoint contracts
└── tasks.md             # Phase 2 (/speckit-tasks — not this command)
```

### Source Code (repository root)

```text
apps/api/                                    # Backend Laravel
├── database/migrations/
│   ├── 2026_07_06_130000_create_bookings_table.php          # existing
│   ├── 2026_08_14_031000_change_bookings_assignee_foreign_key_to_restrict.php  # existing (assignee done)
│   └── 2026_08_14_*_change_bookings_patient_service_foreign_keys_to_restrict.php  # NEW — patient_id + service_id restrict
├── app/Http/Requests/
│   └── BookingRequest.php                   # EDIT — immutability patient_id saat medical record ada
├── app/Actions/Booking/
│   └── UpdateBookingAction.php              # EDIT — guard patient_id immutability (defense in depth)
├── app/Http/Resources/
│   └── BookingResource.php                  # EDIT — expose has_medical_record flag
├── app/Models/
│   └── Booking.php                          # existing (no change expected)
├── lang/id/
│   ├── booking.php                          # EDIT — add edit/cancel/overlap keys
│   └── clinic.php                           # existing (booking_status, overlap_warning, invalid_transition ok)

apps/web/                                    # Frontend TanStack Start
├── src/routes/$tenant/clinic/bookings/
│   ├── index.tsx                            # EDIT — fix breadcrumb self-link + overlap indicator + integrate edit
│   └── components/
│       ├── booking-form-modal.tsx           # REFACTOR → booking-form-dialog.tsx (create+edit, disabled pasien bila rekam medis)
│       └── booking-status-action.tsx        # existing (no change expected)
└── src/components/
    ├── datatable/                           # REUSE (DataTable, toolbar, pagination, faceted filter) — no new
    ├── forms/                               # REUSE (FormSelect, FormDatePicker withTime, FormTextarea, FormInput, FormSubmit, useForm) — no new
    └── schedule/schedule-grid.tsx           # existing (overlap indicator via cell badge, optional)
```

**Structure Decision**: Web application (Option 2). Monorepo `apps/api` + `apps/web` sudah ada — tidak ada struktur baru. Fitur murni mengedit file eksisting di kedua app. Tidak ada komponen form/datatable baru sesuai instruksi user (reuse yang ada di `components/datatable/` + `components/forms/`).

## Complexity Tracking

> Tidak ada pelanggaran Constitution Check — tabel ini kosong.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| — | — | — |
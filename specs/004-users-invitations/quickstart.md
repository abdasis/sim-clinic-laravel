# Quickstart — Users & Invitations

**Feature**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md)

Panduan validasi end-to-end. Tidak berisi implementasi penuh — detail ada di `tasks.md`.

## Prasyarat

- Docker db berjalan: `docker compose up -d db` (PostgreSQL port 5435).
- `apps/api/.env` ada (`cp .env.example .env && php artisan key:generate`).
- `apps/web` dependensi terpasang (`bun install`).
- Spec 003 (tenants + audit log via spatie/laravel-activitylog) sudah ter-implement: central tenant + platform_admin ter-seed.

## Setup (jalankan user sendiri)

```bash
cd apps/api
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
# edit config/permission.php: 'teams' => true, 'team_foreign_key' => 'tenant_id'
php artisan migrate                       # users soft-delete + restrict FK + permission tables
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan permission:cache-reset
php artisan serve                         # port 8000
```

```bash
cd apps/web && bun run generate-routes && bun run dev   # port 3001
```

## Skenario validasi

### US1 — Login tenant

1. Seed tenant + staf aktif (`php artisan db:seed --class=ClinicDemoSeeder` bila ada, atau buat manual via tinker).
2. Buka `http://localhost:3001/{slug}/login` → isi email+password staf → submit.
3. **Expected**: mendarat di `/{slug}/clinic` dengan sidebar sesuai `clinic_role`; breadcrumb root→tenant→Klinik.
4. Cek audit: query `audit_logs` where `action='user.login'` → record ada, causer = user, description naratif.

### US2 — Manajemen staf (admin)

1. Login sebagai admin klinik → buka `/{slug}/clinic/staff`.
2. **Expected**: daftar hanya staf aktif (tidak ada yang soft-deleted).
3. Ubah `clinic_role` satu staf (mis. doctor→therapist) → **Expected**: peran berubah; audit `staff.role_changed` naratif.
4. Nonaktifkan staf (dialog konfirmasi) → **Expected**: staf hilang dari daftar aktif; audit `staff.deactivated` "Menonaktifkan staf {name} — peran {role}".
5. Verifikasi data buatan staf tetap ada: cek `bookings` where `assignee_id={staf}` → record tetap ada (FK restrictOnDelete, soft-delete tidak putus).
6. Coba nonaktifkan admin terakhir → **Expected**: ditolak 422 "admin terakhir".

### US3 — Undang anggota

1. Login admin → `/{slug}/users` → buat undangan email baru + `clinic_role`.
2. **Expected**: undangan `pending` ber-token unik; audit `invitation.created` naratif.
3. Coba undang email yang sudah user aktif di tenant sama → **Expected**: ditolak 422 (FR-022).
4. Buka URL accept `http://localhost:3001/invitations/{token}` → set password → submit.
5. **Expected**: user baru tercipta (`active`), ter-assign spatie role (platform member + clinic `clinic_role`); undangan `accepted`; audit `invitation.accepted` naratif; redirect ke `/{slug}/login`.
6. Login dengan user baru → **Expected**: berhasil, sidebar sesuai `clinic_role`.
7. Batalkan undangan `pending` → **Expected**: `cancelled`, token tak bisa accept.
8. Tunggu/buat undangan expired → akses token → **Expected**: `expired`, ditolak.

## Tes otomatis

```bash
cd apps/api && php artisan test --filter=UserController
cd apps/api && php artisan test --filter=StaffController
cd apps/api && php artisan test --filter=InvitationController
cd apps/api && php artisan test --filter=RemoveUserAction
cd apps/api && php artisan test --filter=DeactivateStaffAction
```

**Expected**: semua hijau. Cakupan wajib: login valid/invalid/inactive, soft-delete exclude dari list, admin-terakhir ditolak, undang-ganda ditolak, accept assign role spatie, expire/cancel.

## Cek lint/typecheck (jalankan user sendiri)

```bash
cd apps/api && vendor/bin/pint
cd apps/web && npx tsc --noEmit --incremental
```
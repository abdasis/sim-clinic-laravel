# Research — Users & Invitations

**Feature**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md)

## R1 — Stack otorisasi: spatie/laravel-permission (teams) vs matriks statis

**Decision**: Adopsi `spatie/laravel-permission` v7 dengan fitur **teams** (`config/permission.php` → `'teams' => true`, `'team_foreign_key' => 'tenant_id'`). Role/permission di-scope per tenant via `setPermissionsTeamId($tenant->id)` di middleware tiap request tenant-scoped. Role platform (`platform_admin`, `tenant_admin`) = role **global** (`team_id = null`); role klinik (`admin`, `doctor`, `therapist`, `cashier`) = role **per-team** (`team_id = tenant_id`).

**Rationale**: Keputusan eksplisit user. spatie teams adalah pola resmi multi-tenant spatie: satu nama role bisa berbeda permission antar tenant, `model_has_roles` membawa `team_id` sebagai scope. Guard tunggal `sanctum` (SPA token) — tidak perlu multi-guard. Cache permission direset via `php artisan permission:cache-reset` setelah seed/assign.

**Alternatives considered**:
- Matriks statis `ClinicPermission` + Gate (existing) — ditolak per instruksi user.
- Hybrid (spatie platform role saja) — ditolak: user minta ganti penuh.
- `creativecrafts/laravel-role-permission-manager` — ditolak: kurang mainstream, bukan standar spatie.

**Implementasi kunci (dari docs spatie v7)**:
- `User` model: `use HasRoles;` + `protected string $guard_name = 'sanctum';` (override default guard agar tidak duplikasi role per guard).
- Middleware `SetPermissionTeamId`: `setPermissionsTeamId(app('tenant')->id)` saat request tenant-scoped; global/central route tetap `team_id=null`.
- `RolesAndPermissionsSeeder`: `Permission::create(['name' => 'staff.r' | 'staff.rw' | ...])` per modul, `Role::create(['name' => 'admin', 'team_id' => null])` untuk global template lalu assign per-team saat registrasi tenant, atau buat role per-team saat tenant dibuat.
- Registrasi tenant (`TenantRegistrationService`): set tenant dibuat, `setPermissionsTeamId($tenant->id)` → `Role::create` per clinic_role → `$user->assignRole('admin')` (clinic) + `assignRole('tenant_admin')` (platform, global).

## R2 — Soft-delete user + restrictOnDelete FK

**Decision**: Tambah kolom `deleted_at` (nullable timestamp) ke `users` + `SoftDeletes` trait di model. Index komposit `(tenant_id, deleted_at)`. FK `bookings.assignee_id`, `medical_records.author_id`, `transactions.cashier_id` → drop + recreate dengan `restrictOnDelete` (default Eloquent FK adalah `cascadeOnDelete`/`restrictOnDelete` tergantung deklarasi migration — yang existing perlu diverifikasi; revisi eksplisit ke `restrictOnDelete`).

**Rationale**: User adalah author rekam medis + causer audit log + assignee booking + cashier transaksi. Hard-delete memutus riwayat. Soft-delete (`deleted_at`) mempertahankan baris (FK tetap valid) sekaligus mengecualikan dari query default (`SoftDeletes` global scope). `restrictOnDelete` mencegah hard-delete tak sengaja lewat cascading dari sisi lain. `RemoveUserAction` dan `StaffController::deactivate` diubah: `status=inactive` + `$user->delete()` (soft) + audit naratif "Menonaktifkan staf {name} — peran {role}".

**Alternatives**:
- Hard-delete + nullable FK (`assignee_id` dst. set null) — ditolak: data jadi yatim, riwayat pecah.
- Hanya `status=inactive` tanpa `deleted_at` — ditolak per revisi spec (eksplisit minta soft-delete + index).

**Catatan TenantScope**: `TenantScope` existing memfilter `tenant_id`; soft-delete di-handle `SoftDeletes` trait (default scope `whereNull deleted_at`). Daftar staf aktif = `whereNotNull('clinic_role')` + otomatis exclude soft-deleted. Tidak ada konflik scope.

## R3 — Admin terakhir tidak bisa dinonaktifkan (FR-005/025)

**Decision**: Cek `activeAdminCount` sebelum nonaktifkan/ubah-peran-dari-admin. "Admin" = user dengan role klinik `admin` (clinic) `status=active` `deleted_at IS NULL` pada tenant. Bila `<= 1` → `abort(422, __('clinic.last_admin'))`. Sama untuk platform role `tenant_admin` di `RemoveUserAction`.

**Rationale**: Tenant wajib punya minimal satu admin aktif. Sudah ada pola di `StaffController::deactivate` + `updateRole` + `RemoveUserAction` — revisi hanya tambah kondisi `whereNull('deleted_at')` (soft-delete) pada count, dan naratif audit. Ekstrak ke `DeactivateStaffAction` agar `StaffController` tetap ramping (SRP) dan count logic reusable.

**Alternatives**: DB constraint — tidak praktis (conditional count lintas baris).

## R4 — Undangan: tolak email sudah user aktif di tenant sama (FR-022) + status transisi

**Decision**: `InvitationService::invite` cek `User::where('tenant_id', $tenant->id)->where('email', $email)->whereNull('deleted_at')->exists()` → tolak. Token unik 64-char (`Str::random(64)` + unique index). `expires_at = now()->addDays(7)`. Status satu arah: `pending` → (`accepted` | `cancelled` | `expired`). Accept → buat `User` + assign spatie role (clinic + platform) + undangan `accepted`. Cancel → admin set `cancelled`. Expire → saat `show`/`accept` menemukan `isExpired()` → set `expired` (lazy) + tolak.

**Rationale**: FR-022 scope per-tenant (email unik global sebagai kredensial, tapi cek undangan-ganda per tenant). Accept menetapkan `clinic_role` sesuai undangan → assign role spatie per-team. Existing `InvitationService` sudah dekat; revisi: cek soft-delete + assign role spatie + naratif audit "Mengundang {email} sebagai {role}" / "Menerima undangan — anggota {name} bergabung".

**Alternatives**: Job scheduler mark expired — `ponytail: add saat butuh notifikasi expire`; MVP cukup lazy expire saat akses.

## R5 — FE: reuse `components/datatable` + `components/forms`, form baru reusable

**Decision**: Halaman staf/users pakai `DataTable` + `useDataTable` (existing, sudah dipakai di `clinic/staff/index.tsx`). Form pakai `useForm` (zod resolver) + `FormInput`/`FormSelect`/`FormSubmit` (existing). Form baru reusable: `form-password.tsx` (password + confirm, dipakai halaman accept undangan; login tetap pakai `FormInput type=password` karena hanya 1 field). Dialog konfirmasi nonaktifkan staf: `deactivate-staff-dialog.tsx` di colocated components (bukan `forms/` karena dialog konfirmasi domain-spesifik, bukan form field reusable).

**Rationale**: Instruksi user eksplisit reuse existing. `form-password.tsx` reusable karena confirm-password muncul di accept undangan (dan potensial reset password masa depan) — 2+ konsumen potensial = absah sebagai reusable. Breadcrumb via `ClinicBreadcrumb` (existing) di tiap halaman dalam.

**Alternatives**: Taruh dialog konfirmasi di `forms/` — ditolak: dialog konfirmasi bukan form-field generic; tetap colocated.

## R6 — Audit log naratif via LogAuditAction existing

**Decision**: `LogAuditAction` (spec 003, spatie/laravel-activitylog backend) dipakai apa adanya. Action code + deskripsi naratif ditentukan caller:
- `user.login` → "Pengguna {email} berhasil masuk."
- `staff.deactivated` → "Menonaktifkan staf {name} — peran {role}."
- `staff.role_changed` → "Peran staf {name} diubah dari {lama} ke {baru}."
- `invitation.created` → "Mengundang {email} sebagai {role}."
- `invitation.accepted` → "Menerima undangan — anggota {name} bergabung."
- `invitation.cancelled` → "Membatalkan undangan ke {email}."

**Rationale**: Spec 003 sudah pasang infra audit; fitur ini hanya menambah caller + naratif. Tidak ada perubahan `LogAuditAction` signature.

## Resolved NEEDS CLARIFICATION

- **Stack permission**: spatie/laravel-permission (teams) — keputusan user.
- **Soft-delete vs hard-delete**: soft-delete (`deleted_at`) + `status=inactive` — revisi spec.
- **FK rule**: `restrictOnDelete` untuk FK ke `users` — revisi spec.
- **Masa kedaluwarsa undangan**: 7 hari (default existing `Invitation::defaultExpiry()`) — dipertahankan.
- **Status user saat accept undangan**: `active` + set password saat accept — existing, dipertahankan.
# API Contracts — Users & Invitations

**Feature**: [spec.md](./spec.md) | **Sumber**: `routes/api.php` (existing) + revisi spec 004

> Bentuk respons seragam: `{ "data": …, "meta": … }`. Koleksi → Resource collection di `data`, pagination di `meta`. Error: HTTP status + `{ message, errors }`. Validasi via FormRequest. Autentikasi: `Authorization: Bearer {token}` (Sanctum).

## Rute existing (dipertahankan, perilaku direvisi)

### POST `/{tenant}/login` — Login tenant (US1)

Public (resolve.tenant, tanpa auth). Body: `{ email, password }`.

**200**: `{ data: { user: UserResource, token: string }, meta: { redirect_to: "/{slug}" } }`
**422**: kredensial invalid → `{ message, errors }`
**403**: user `inactive`/ter-soft-delete → `{ message }`
**403/423**: tenant `inactive` (middleware, sebelum auth)

Audit: `user.login` naratif "Pengguna {email} berhasil masuk."

### GET `/{tenant}/users` — Daftar user tenant (US2, admin)

`auth:sanctum` + resolve.tenant + ensure.tenant.active. DataTable params: `page, per_page, sort, direction, search, filter[status], filter[role]`. **Revisi**: exclude soft-deleted (`deleted_at IS NULL`) secara default; query pakai index `(tenant_id, deleted_at)`.

**200**: `{ data: UserResource[], meta: { current_page, per_page, total, last_page } }`

### POST `/{tenant}/users/invite` — Undang anggota (US3, admin)

Body: `{ email, role: "tenant_admin"|"member", clinic_role?: "admin"|"doctor"|"therapist"|"cashier" }`.

**201**: `{ data: { token: string }, meta: { message } }`
**422**: email sudah user aktif di tenant sama (FR-022) → `{ message, errors }`

Audit: `invitation.created` naratif "Mengundang {email} sebagai {role}."

### POST `/{tenant}/users/{user}/remove` — Nonaktifkan user (US2, admin)

**Revisi**: soft-delete (`status=inactive` + `deleted_at`) bukan hard-delete. Proteksi admin terakhir → 422.

**200**: `{ data: [], meta: { message } }`
**422**: admin terakhir → `{ message }`

Audit: `user.removed` / `staff.deactivated` naratif "Menonaktifkan staf {name} — peran {role}."

### PATCH `/{tenant}/users/{user}/role` — Ubah peran platform (US2, admin)

Body: `{ role: "member"|"tenant_admin" }`. **Revisi**: assign spatie role global bersamaan; proteksi admin terakhir saat downgrade tenant_admin.

**200**: `{ data: UserResource, meta: { message } }`
**422**: admin terakhir downgrade → `{ message }`

Audit: `user.role_changed` naratif "Peran pengguna {name} diubah dari {lama} ke {baru}."

### GET `/{tenant}/clinic/staff` — Daftar staf klinik (US2, admin)

DataTable params. **Revisi**: `whereNotNull(clinic_role)` + exclude soft-deleted (default SoftDeletes scope). Autorisasi via spatie `hasPermissionTo('staff.r')`.

**200**: `{ data: StaffResource[], meta: { pagination } }`

### POST `/{tenant}/clinic/staff` — Buat staf langsung (admin)

Body: `{ name, email, password, clinic_role }`. **Revisi**: assign spatie role per-team (`clinic_role`) + set enum + sendiri dalam DB transaction.

**201**: `{ data: StaffResource, meta: { message } }`

Audit: `staff.created` naratif.

### PATCH `/{tenant}/clinic/staff/{staff}/role` — Ubah peran klinik (US2, admin)

Body: `{ clinic_role }`. **Revisi**: assign spatie role per-team + proteksi admin terakhir (clinic admin).

**200**: `{ data: StaffResource, meta: { message } }`
**422**: clinic admin terakhir → `{ message }`

Audit: `staff.role_changed` naratif "Peran staf {name} diubah dari {lama} ke {baru}."

### POST `/{tenant}/clinic/staff/{staff}/deactivate` — Nonaktifkan staf (US2, admin)

**Revisi**: ekstrak ke `DeactivateStaffAction` → soft-delete + `status=inactive` + proteksi admin terakhir + naratif "Menonaktifkan staf {name} — peran {role}."

**200**: `{ data: StaffResource, meta: { message } }`
**422**: clinic admin terakhir → `{ message }`

Audit: `staff.deactivated` naratif.

### GET `/invitations/{token}` — Lihat detail undangan (public)

**200**: `{ data: { email, tenant_slug }, meta: {} }`
**404**: token tidak valid/expired/cancelled → `{ message }`

### POST `/invitations/{token}/accept` — Terima undangan (public)

Body: `{ password }` (min 8, kompleks). **Revisi**: buat User + assign spatie role (platform `member`/`tenant_admin` + clinic `clinic_role` sesuai undangan) + undangan `accepted`, dalam DB transaction.

**200**: `{ data: [], meta: { redirect_to: "/{slug}/login", message } }`
**422**: token invalid/expired → `{ message, errors }`

Audit: `invitation.accepted` naratif "Menerima undangan — anggota {name} bergabung."

## Resource shape

**UserResource**: `{ id, name, email, role, role_label, clinic_role, clinic_role_label, status, status_label, tenant_id, deleted_at? }`

**StaffResource**: subset UserResource untuk staf klinik: `{ id, name, email, clinic_role, clinic_role_label, status, status_label }`

## Otorisasi (revisi: spatie/laravel-permission)

- Gate/Policy `clinic.access` digantikan spatie `hasPermissionTo('{module}.{r|rw}')`.
- Middleware baru `SetPermissionTeamId` pada route group `{tenant}` (set `team_id=tenant_id`); central route `team_id=null`.
- Permission per modul: `staff`, `service`, `patient`, `booking`, `medical_record`, `product`, `inventory`, `transaction`, `invoice`, `report` — masing-masing `.r`/`.rw`.
- FE sidebar mirror (`clinic/route.tsx`) baca `clinic_role` dari auth user (enum existing) untuk visibilitas menu — tidak perlu fetch permission spatie di FE untuk MVP (role enum cukup; `ponytail: fetch permission saat FE butuh granular`).
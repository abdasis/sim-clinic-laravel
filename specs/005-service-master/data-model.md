# Data Model — Master Layanan Klinik (005-service-master)

**Date**: 2026-08-14 | **Spec**: [spec.md](spec.md) | **Research**: [research.md](research.md)

Sumber kebenaran struktur: [`docs/erd/services.md`](../../docs/erd/services.md) + [`docs/normalization/README.md`](../../docs/normalization/README.md). Fitur ini revisi entitas `services` eksisting; tidak ada entitas baru.

## Entity: Service

Master layanan/treatment klinik. Tenant-scoped.

| Field | Type | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, NOT NULL, cascadeOnDelete | BelongsToTenant; auto-fill saat create |
| name | string(255) | NOT NULL | FR-011; tidak unique per tenant (duplikat diizinkan, R-research) |
| description | text | nullable | |
| price | decimal(12,2) | NOT NULL, >= 0 | FR-011; check di app (FormRequest `gte:0`) |
| status | enum(active, archived) | default `active` | FR-013; ServiceStatus enum |
| created_at | timestamp | | |
| updated_at | timestamp | | |

### Index

- `(tenant_id, status)` — sudah ada; filter layanan aktif per tenant (FR-019, R3).

### Relationships

| Relasi | Tipe | Delete rule (target) | Snapshot? |
|--------|------|----------------------|-----------|
| belongsTo `Tenant` | n:1 | — | — |
| hasMany `Booking` | 1:n | **restrictOnDelete** (revisi R1; sebelumnya cascadeOnDelete) | booking simpan `service_id` saja; `BookingResource` baca `service->name` via `whenLoaded` (live, bukan snapshot) |
| hasMany `TreatmentRecord` | 1:n (nullable FK) | **restrictOnDelete** (revisi R1; sebelumnya nullOnDelete) | snapshot `treatment_records.service_name` (immutable, R6/FR-016) |
| hasMany `TransactionItem` | 1:n (nullable FK) | **restrictOnDelete** (revisi R1; sebelumnya nullOnDelete) | snapshot `transaction_items.name` + `unit_price` (immutable, R6/FR-016) |

### Validation (store/update)

- `name`: required|string|max:255
- `description`: nullable|string
- `price`: required|numeric|gte:0
- `status`: nullable|enum(ServiceStatus) — default `active` saat store bila tidak dikirim

### State transitions

```
        create
          │
          ▼
      ┌────────┐  update(status=archived) / archive  ┌──────────┐
      │ active │ ──────────────────────────────────▶ │ archived │
      └────────┘ ◀──────────────────────────────────  └──────────┘
                   update(status=active)  (reactivate, opsional — MVP: diizinkan via form edit)
```

- `active → archived`: via `ArchiveServiceAction` (FR-013) atau form edit ubah status.
- `archived → active`: via form edit (tidak ada FR melarang; default diizinkan — admin bisa re-aktifasi).
- Tidak ada state `deleted` — hard-delete tidak diekspos endpoint (R2). DB `restrictOnDelete` blokir hard-delete bila masih direferensi.

## Snapshot invariant (FR-016, R6)

| Tabel | Kolom snapshot | Sumber (master) | Immutability |
|-------|----------------|-----------------|--------------|
| transaction_items | `name`, `unit_price` | `services.name`, `services.price` | tulis sekali saat create transaction item; **tidak ada path update**. Test kunci invariant (R5). |
| treatment_records | `service_name` | `services.name` | tulis sekali saat create treatment; **tidak ada path update**. Test kunci invariant (R5). |

Verifikasi: test assert ubah `service.name`/`price` + arsip → snapshot di transaction_items & treatment_records tidak berubah.

## Permission

`ClinicPermission::MATRIX` (tidak berubah, R8):

| Role | service |
|------|---------|
| admin | rw (CRUD + arsip) |
| doctor | r (view) |
| therapist | r (view) |
| cashier | (tidak ada — 403) |

Otorisasi via `ServicePolicy` → Gate `clinic.access` ['service', 'r'|'w'].

## Activity log

Setiap aksi ubah-data → `LogAuditAction` (spatie/laravel-activitylog, tabel `audit_logs`):

| Aksi | event/log_name | Deskripsi naratif |
|------|----------------|-------------------|
| store | `service.created` | "Membuat layanan {name}" |
| update | `service.updated` | "Memperbarui layanan {name}" |
| archive | `service.archived` | "Mengarsipkan layanan {name}" |

Properties: `tenant_id` (auto dari container). Causer: auth user (auto).

## Migration changes

Satu migration baru: `2026_08_14_*_restrict_service_foreign_keys` — drop + recreate 3 FK (`bookings.service_id`, `treatment_records.service_id`, `transaction_items.service_id`) dengan `restrictOnDelete`. Tidak ada perubahan kolom `services`.

## Tidak ada entity baru

Fitur ini murni revisi `services` + relasinya. Tidak ada tabel/kolom baru. `ServiceFactory` perlu dibuat (saat ini belum ada) untuk kebutuhan test (R9).
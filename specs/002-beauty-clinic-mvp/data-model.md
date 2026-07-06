# Data Model: MVP Sistem Klinik Kecantikan

**Spec**: [spec.md](./spec.md) | **Research**: [research.md](./research.md)

Semua entitas bisnis = **Tenant-Scopeable** (`tenant_id` + `BelongsToTenant` + `TenantScope`, warisan spec 001). Akses lintas tenant → dianggap tidak ada. Format tabel menyertakan field inti; `created_at`/`updated_at` diasumsikan di setiap tabel kecuali dicatat.

## Enum (app/Enums)

- **ClinicRole**: `admin`, `doctor`, `therapist`, `cashier` (R2).
- **BookingStatus**: `pending`, `confirmed`, `done`, `cancelled` (FR-031).
- **PaymentMethod**: `cash`, `transfer`, `qris`, `debit` (FR-054).
- **PaymentStatus**: `unpaid`, `paid` (FR-055).
- **StockMovementType**: `in`, `out_manual`, `sold_pos`, `rollback` (R7).
- **ServiceStatus**: `active`, `archived` (FR-013). Sama untuk produk (FR-066).
- **MedicalPhotoType**: `before`, `after` (FR-042).

## User (modifikasi — warisan spec 001)

Tambah kolom peran klinik di tabel `users` (spec 001).

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| clinic_role | enum(ClinicRole) | nullable | FR-001; null untuk user non-klinik (mis. platform admin spec 001). Satu user satu peran klinik (assumption). |

**Relationships**: hasMany `Booking` (sebagai assignee), hasMany `MedicalRecord` (sebagai author).

---

## Service (master layanan/treatment) — US2

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | BelongsToTenant |
| name | string(255) | not null | FR-011 |
| description | text | nullable | |
| price | decimal(12,2) | not null, ≥0 | FR-011: tidak negatif |
| status | enum(ServiceStatus) | default `active` | FR-013 |
| created_at/updated_at | timestamp | | |

**Validation (store/update)**: `name` required|string|max:255; `price` required|decimal|gte:0; `description` nullable|string; `status` enum.

**Relationships**: hasMany `Booking`, hasMany `TreatmentRecord`. Arsip (FR-013) = set `status=archived`, soft hide dari pilihan baru.

---

## Patient (pasien) — US3

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | |
| name | string(255) | not null | FR-020 |
| birth_date | date | nullable | FR-020 |
| gender | enum(male,female,other) | nullable | FR-020 |
| phone | string(50) | not null | FR-020, FR-023 (peringatan duplikat) |
| whatsapp | string(50) | nullable | FR-020 |
| address | text | nullable | |
| notes | text | nullable | |
| created_at/updated_at | timestamp | | |

**Validation**: `name` required; `phone` required|string|max:50; `birth_date` nullable|date|before:today; `gender` nullable|enum; `whatsapp` nullable|string; `address` nullable|string.

**Unique**: TIDAK ada unique constraint pada `phone` (FR-023 = peringatan, bukan block). Duplikat dideteksi di controller: `Patient::where('tenant_id', …)->where('phone', …)->exists()` → response flag `duplicate_warning`.

**Relationships**: hasMany `Booking`, hasMany `MedicalRecord`, hasMany `Transaction`.

---

## Booking — US4

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | |
| patient_id | bigint unsigned | FK→patients, not null | FR-030 |
| service_id | bigint unsigned | FK→services, not null | FR-030; satu layanan utama (R9) |
| assignee_id | bigint unsigned | FK→users, not null | FR-030; Dokter/Terapis |
| start_at | datetime | not null | FR-030 |
| end_at | datetime | not null | after `start_at` |
| status | enum(BookingStatus) | default `pending` | FR-031 |
| notes | text | nullable | |
| status_changed_at | timestamp | nullable | FR-034 audit sederhana |
| created_at/updated_at | timestamp | | |

**Validation**: `patient_id` exists in tenant; `service_id` exists+active; `assignee_id` exists + clinic_role in (doctor,therapist); `start_at` required|date|after:now; `end_at` required|date|after:start_at.

**State transitions** (FR-031): `pending`→`confirmed`→`done`; `pending`/`confirmed`→`cancelled`. `done` TIDAK →`cancelled` (edge case). Enforce di `BookingController`/FormRequest.

**Overlap detection** (FR-035, R8): post-validation, query booking lain `assignee_id` sama + `start_at < other.end_at AND end_at > other.start_at` + status ≠ cancelled → flag `overlap_warnings`. Tidak block.

**Relationships**: belongsTo `Patient`, `Service`, `Assignee`(User). hasOne `MedicalRecord`. hasOne `Transaction` (opsional, kalau booking jadi transaksi).

**Index**: `(tenant_id, assignee_id, start_at, end_at)` untuk query overlap & jadwal. `(tenant_id, start_at)` untuk view jadwal.

---

## MedicalRecord (rekam medis SOAP) — US7

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | |
| booking_id | bigint unsigned | FK→bookings, not null, unique | FR-040; 1 record per booking (R10) |
| patient_id | bigint unsigned | FK→patients, not null | denormalized untuk query riwayat |
| author_id | bigint unsigned | FK→users, not null | dokter/terapis pengisi |
| subjective | text | nullable | FR-040 SOAP |
| objective | text | nullable | |
| assessment | text | nullable | |
| plan | text | nullable | |
| created_at/updated_at | timestamp | | |

**Business rules**: booking harus `status=done` sebelum/serupa mengisi (FR-033). Hanya role dokter/therapist/admin (FR-044, Policy).

**Relationships**: belongsTo `Booking`, `Patient`, `Author`. hasMany `TreatmentRecord`, `MedicalPhoto`.

---

## TreatmentRecord (treatment aktual) — US7

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | |
| medical_record_id | bigint unsigned | FK→medical_records, not null | |
| service_id | bigint unsigned | FK→services, nullable | FR-041; nullable jika tindakan di luar master |
| service_name | string(255) | not null | snapshot nama (R6 spirit) |
| notes | text | nullable | catatan klinis |
| created_at/updated_at | timestamp | | |

**Relationships**: belongsTo `MedicalRecord`, `Service`.

---

## MedicalPhoto (foto before/after) — US7

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | |
| medical_record_id | bigint unsigned | FK→medical_records, not null | |
| type | enum(MedicalPhotoType) | not null | before/after |
| path | string(255) | not null | R3: `medical-photos/{tenant}/{record}/{file}` |
| created_at/updated_at | timestamp | | |

**Validation** (upload, R3): `file` required|image|mimes:jpg,jpeg,png|max:2048.

---

## Product (master produk) — US6

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | |
| name | string(255) | not null | |
| unit | string(50) | not null | FR-060 (pcs/botol/ml) |
| stock_balance | integer | not null, default 0 | R7; satu sumber saldo |
| min_threshold | integer | not null, default 0 | FR-065 ambang "stok menipis" |
| price | decimal(12,2) | not null, ≥0 | harga jual |
| status | enum(ServiceStatus) | default `active` | FR-066 arsip, tidak hapus permanen |
| created_at/updated_at | timestamp | | |

**Validation**: `name` required; `unit` required|string; `stock_balance` integer|gte:0; `min_threshold` integer|gte:0; `price` decimal|gte:0.

**Computed**: `is_low_stock = stock_balance <= min_threshold` (FR-065).

**Relationships**: hasMany `StockMovement`, hasMany `TransactionItem`.

---

## StockMovement (pergerakan stok) — US6, US5

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | |
| product_id | bigint unsigned | FK→products, not null | |
| type | enum(StockMovementType) | not null | in/out_manual/sold_pos/rollback (R7) |
| quantity | integer | not null | positif untuk `in`/`rollback`; positif tapi mengurangi saldo untuk `out_manual`/`sold_pos` |
| balance_after | integer | not null | saldo setelah mutasi (audit) |
| related_type | string(255) | nullable | morph: Transaction::class untuk sold_pos/rollback |
| related_id | bigint unsigned | nullable | |
| note | string(255) | nullable | keterangan/alasan (FR-061, FR-062) |
| created_at | timestamp | | |

**Business rules** (R7): semua mutasi lewat `StockService::adjust()` dalam DB transaction; `quantity` selalu positif, arah saldo ditentukan `type`. `balance_after` dicatat untuk audit.

**Relationships**: belongsTo `Product`. morphTo `related` (Transaction).

**Index**: `(tenant_id, product_id, created_at)` untuk riwayat per produk (FR-064).

---

## Transaction (penjualan POS) — US5

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | |
| patient_id | bigint unsigned | FK→patients, nullable | FR-050; bisa tanpa pasien? spec sebut pasien — not null (FR-050 "transaksi baru dengan pasien") |
| booking_id | bigint unsigned | FK→bookings, nullable | FR-033: opsional link dari booking done |
| cashier_id | bigint unsigned | FK→users, not null | kasir pembuat |
| invoice_number | string(50) | unique(tenant), not null | generate: `INV-YYYYMMDD-XXXX` |
| subtotal | decimal(12,2) | not null | sum(item.subtotal) |
| payment_status | enum(PaymentStatus) | default `unpaid` | FR-055 |
| cancelled_at | timestamp | nullable | FR-058 |
| created_at/updated_at | timestamp | | |

**State transitions** (payment_status): `unpaid`→`paid` (FR-055, saat sum(payments) ≥ subtotal). Status transaksi (aktif/batal): `cancelled_at` null→set untuk pembatalan (FR-058 rollback stok).

**Relationships**: belongsTo `Patient`(nullable), `Booking`(nullable), `Cashier`(User). hasMany `TransactionItem`, `Payment`. hasOne `Invoice`.

---

## TransactionItem (line item) — US5, R6

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | |
| transaction_id | bigint unsigned | FK→transactions, not null | |
| product_id | bigint unsigned | FK→products, nullable | nullable: item bisa layanan atau produk |
| service_id | bigint unsigned | FK→services, nullable | |
| name | string(255) | not null | R6 snapshot nama |
| unit_price | decimal(12,2) | not null | R6 snapshot harga historik (FR-056) |
| qty | integer | not null, >0 | |
| subtotal | decimal(12,2) | not null | unit_price * qty |
| created_at/updated_at | timestamp | | |

**Business rules**: salah satu `product_id`/`service_id` terisi. Stok produk di-check (FR-053) & adjust (FR-052) saat simpan transaksi via `StockService`.

**Relationships**: belongsTo `Transaction`, `Product`(nullable), `Service`(nullable).

---

## Payment (pembayaran) — US5

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | |
| transaction_id | bigint unsigned | FK→transactions, not null | |
| method | enum(PaymentMethod) | not null | FR-054 |
| amount | decimal(12,2) | not null, >0 | |
| paid_at | datetime | not null | |
| created_at/updated_at | timestamp | | |

**Business rules** (FR-055): `PayTransactionAction` cek `sum(payments.amount) ≥ transaction.subtotal` → set `payment_status=paid`. Kelebihan bayar → peringatan, tidak ada saldo otomatis (edge case).

**Relationships**: belongsTo `Transaction`.

---

## Invoice — US5

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | |
| transaction_id | bigint unsigned | FK→transactions, not null, unique | 1 invoice per transaksi |
| issued_at | datetime | not null | |
| created_at/updated_at | timestamp | | |

**R4**: konten invoice di-render dari transaksi + items + payments + tenant + patient di view HTML print, BUKAN kolom duplikat di tabel. `Invoice` model = record penerbitan + link ke transaction.

**Relationships**: belongsTo `Transaction`.

---

## Ringkasan relasi (high-level)

```
Tenant ──< User(clinic_role) >──┐
Tenant ──< Service >──┐          │
Tenant ──< Patient >──┼──< Booking >──assignee──> User
                     │      │
                     │      └──< MedicalRecord >──< TreatmentRecord >── Service
                     │                    │
                     │                    └──< MedicalPhoto
                     │
                     └──< Transaction >──< TransactionItem >── Product / Service
                              │   │
                              │   └──< Payment
                              └── hasOne Invoice
Product ──< StockMovement >──(related: Transaction)
```

## Index strategi (ponytail: DB constraint over app code)

- `services.(tenant_id, status)` — filter layanan aktif.
- `patients.(tenant_id, phone)` — deteksi duplikat (FR-023).
- `bookings.(tenant_id, assignee_id, start_at, end_at)` — overlap + jadwal (FR-035, SC-008).
- `bookings.(tenant_id, start_at)` — view jadwal harian/mingguan.
- `medical_records.(tenant_id, booking_id)` unique — 1 record per booking.
- `stock_movements.(tenant_id, product_id, created_at)` — riwayat stok (FR-064).
- `transactions.(tenant_id, invoice_number)` unique.
- `transactions.(tenant_id, payment_status, created_at)` — query omzet lunas per rentang (FR-070).
- `transaction_items.(tenant_id, transaction_id)`, `(tenant_id, product_id)`, `(tenant_id, service_id)` — agregasi laporan (FR-071, FR-072).
- FK `tenant_id` pada semua tabel — index implisit via FK.

`ponytail: stock_balance kolom denormalized di products, konsistensi dijaga StockService + DB transaction; reconcile job opsional kalau drift terdeteksi (R7).`

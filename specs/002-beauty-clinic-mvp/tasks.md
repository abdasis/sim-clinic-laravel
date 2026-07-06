# Tasks: MVP Sistem Klinik Kecantikan

**Input**: Design documents from `/specs/002-beauty-clinic-mvp/`

**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

**Tests**: Tidak diminta eksplisit di spec → test tasks TIDAK dibuat sebagai fase terpisah. Tiap task backend non-trivial wajib tinggalkan 1 cek runnable (`php -l <file>` untuk syntax, atau `php artisan test` / `php artisan tinker` untuk probe) seperti dicatat di deskripsi. Validasi end-to-end manual via skenario `quickstart.md` (V1–V8). Jika butuh TDD, tambahkan task test per story sesuai aturan skill (tests OPTIONAL).

**Organization**: Tasks dikelompokkan per user story (US1–US8). Tiap story dipecah jalur **Backend (BE)** (`apps/api/`) dan **Frontend (FE)** (`apps/web/`) supaya 2 programmer kerja paralel. Jalur BE dan FE dalam satu story saling lepas kecuali kontrak endpoint — FE pakai `contracts/api-contracts.md` sebagai stub saat BE belum siap.

**Dependency kritis spec 001**: Spec 002 dibangun di atas spec 001 (tenant, `BelongsToTenant`, `TenantScope`, `ResolveTenant`, `EnsureTenantActive`, auth Sanctum, `lang/id/*`, audit log). Fase Foundational (Phase 2) MEMVERIFIKASI spec 001 sudah ada sebelum lanjut (research.md R1).

## Format: `[ID] [P?] [Story?] Description`

- **[P]**: Bisa paralel (file beda, tanpa dependency task sebelumnya)
- **[Story]**: Label user story (US1..US8) — wajib untuk task di fase user story
- **[BE]**: Programmer backend (`apps/api/`)
- **[FE]**: Programmer frontend (`apps/web/`)
- Path eksplisit di setiap task

## Path Conventions

- **Backend**: `apps/api/` (Laravel 13, PHP 8.3)
- **Frontend**: `apps/web/` (React 19, TanStack Start)
- Path di deskripsi relatif terhadap repo root.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Verifikasi fondasi spec 001, struktur folder spec 002, dependency. BE dan FE paralel.

- [ ] T001 [BE] **Verifikasi spec 001 sudah terimplementasi** (BLOCKER — research.md R1). Cek keberadaan: `apps/api/app/Models/Tenant.php`, `apps/api/app/Concerns/BelongsToTenant.php`, `apps/api/app/Scopes/TenantScope.php`, `apps/api/app/Http/Middleware/ResolveTenant.php`, `apps/api/app/Models/User.php` punya `tenant_id`+`role`, `apps/api/lang/id/*.php`. Jika belum ada → hentikan, implementasikan spec 001 dulu. Jika ada → lanjutkan
- [ ] T002 [P] [BE] Install dependency warisan spec 001 jika belum: `composer require spatie/laravel-activitylog` + publish migration & config. User jalankan command install sendiri (CLAUDE.md: composer require konfirmasi dulu)
- [ ] T003 [P] [BE] Buat folder struktur spec 002: `apps/api/app/{Enums,Policies,Services}`, pastikan `apps/api/app/{Concerns,Scopes,Actions,Http}` dari spec 001 tidak rusak
- [ ] T004 [P] [FE] Verifikasi dependency frontend sudah terinstall di `apps/web/package.json`: `@tanstack/react-table`, `react-hook-form`, `@hookform/resolvers`, `zod`, `date-fns`, `react-day-picker`, `lucide-react`, `sonner`. **Tidak ada dep baru** untuk MVP (research.md §Ringkasan). Buat folder: `apps/web/src/routes/$tenant/clinic/{staff,services,patients,bookings,medical-records,products,inventory,pos,reports}`, `apps/web/src/components/{forms,schedule,medical-photos}`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Infrastruktur inti lintas-modul spec 002: kolom peran klinik, enum, Gate/permission matriks, i18n modul klinik, forms wrapper frontend. WAJIB selesai sebelum user story manapun.

**⚠️ CRITICAL**: Tidak ada user story boleh mulai sebelum fase ini selesai.

- [ ] T005 [BE] Buat migration `apps/api/database/migrations/2026_07_06_100000_add_clinic_role_to_users_table.php`: tambah `clinic_role enum(admin,doctor,therapist,cashier) nullable` (data-model.md User modifikasi, FR-001). Nullable agar user non-klinik (platform admin spec 001) tidak terdampak. Index `clinic_role`
- [ ] T006 [P] [BE] Buat enum `apps/api/app/Enums/ClinicRole.php` (`admin,doctor,therapist,cashier`), `app/Enums/BookingStatus.php` (`pending,confirmed,done,cancelled`), `app/Enums/PaymentMethod.php` (`cash,transfer,qris,debit`), `app/Enums/PaymentStatus.php` (`unpaid,paid`), `app/Enums/StockMovementType.php` (`in,out_manual,sold_pos,rollback`), `app/Enums/ServiceStatus.php` (`active,archived`), `app/Enums/MedicalPhotoType.php` (`before,after`). Pakai PHP 8.3 native enum + method `label()` yang return `__('clinic.role.admin')` dll (data-model.md Enum)
- [ ] T007 [BE] Update `apps/api/app/Models/User.php`: tambah fill `clinic_role`, cast ke `ClinicRole` enum, helper `hasClinicRole(...$roles)`. Relasi `hasMany Booking` (sebagai assignee), `hasMany MedicalRecord` (sebagai author). Class ≤300 baris (CLAUDE.md)
- [ ] T008 [BE] Buat trait/helper permission `apps/api/app/Concerns/HasClinicAccess.php` (atau service `app/Services/ClinicPermission.php`): matriks role→modul dari research.md R2. Method `canAccess(ClinicRole, string $module, string $action): bool`. Matriks: Admin=all; Dokter=patient(rw),booking(rw),medical_record(rw),service(r); Terapis=patient(r),booking(rw),medical_record(rw),service(r); Kasir=patient(rw dasar),transaction(rw),invoice(rw). Logika <60 baris. Dipakai semua Policy (R2)
- [ ] T009 [BE] Daftarkan `Gate::define('clinic.access', ...)` di `apps/api/app/Providers/AuthServiceProvider.php` (atau `AppServiceProvider`) yang bungkus `ClinicPermission::canAccess(...)`. Backend policy panggil gate ini. Pesan tolak i18n `__('clinic.forbidden')` (FR-004, FR-044, FR-075)
- [ ] T010 [P] [BE] Buat file bahasa `apps/api/lang/id/clinic.php` (label enum: role/status/method + forbidden, duplicate_warning, overlap_warning, low_stock, dst). Tambah `lang/id/{staff,service,patient,booking,medical_record,product,inventory,pos,invoice,report}.php` per modul (key label/placeholder/pesan). Locale default `id` di `config/app.php` (CLAUDE.md i18n wajib)
- [ ] T011 [BE] Update share translations (spec 001 `HandleInertiaRequests` atau SPA sanctum share): tambah modul klinik ke `translations` — `'clinic'`, `'staff'`, `'service'`, `'patient'`, `'booking'`, `'medical_record'`, `'product'`, `'inventory'`, `'pos'`, `'invoice'`, `'report'` (CLAUDE.md React i18n)
- [ ] T012 [P] [FE] Buat form reusable wrapper di `apps/web/src/components/forms/`: `form-input.tsx`, `form-select.tsx`, `form-textarea.tsx`, `form-date-picker.tsx`, `form-submit.tsx`, `use-form.ts` (wrapper `react-hook-form` + `zod` resolver). Dibangun di atas `apps/web/src/components/ui/form.tsx` + `ui/field.tsx` yang sudah ada (per user input — reuse). Tiap file ≤300 baris. Semua label/placeholder via `t()` — DILARANG hardcode string UI (CLAUDE.md)
- [ ] T013 [P] [FE] Extend `apps/web/src/hooks/use-trans.ts` (warisan spec 001) untuk baca key modul klinik baru. Buat layout shell `apps/web/src/routes/$tenant/clinic/route.tsx` (layout TanStack): sidebar 8 modul (filter by role di client baca `user.clinic_role`), breadcrumb root `{Tenant} / Clinic`. File ≤300 baris (CLAUDE.md)
- [ ] T014 [BE] Buat seeder `apps/api/database/seeders/ClinicDemoSeeder.php`: 4 staf (1 per peran klinik) di tenant demo + beberapa pasien + 3 layanan + 2 produk. Jalankan via `php artisan db:seed --class=ClinicDemoSeeder` (quickstart.md Setup)

**Checkpoint**: Foundation ready — BE punya peran klinik, enum, Gate permission, i18n modul, seeder. FE punya forms wrapper + layout clinic + i18n. User story boleh mulai paralel.

---

## Phase 3: User Story 1 - Manajemen Pengguna & 4 Peran Klinik (Priority: P1) 🎯 MVP

**Goal**: Admin kelola akun staf + tetapkan 1 dari 4 peran klinik (Admin/Dokter/Terapis/Kasir). Permission per modul aktif.

**Independent Test**: Buat 4 akun staf (1 per peran), login masing-masing, verifikasi setiap peran hanya akses modul diizinkan & ditolak di modul lain. (quickstart.md V1, SC-001, SC-009)

### Backend (BE)

- [ ] T015 [P] [BE] [US1] Buat policy `apps/api/app/Policies/StaffPolicy.php`: `viewAny/create/update/deactivate` hanya `ClinicRole::Admin`. Pakai gate `clinic.access` (T009). Tolak 403 i18n (FR-002, FR-003)
- [ ] T016 [P] [BE] [US1] Buat FormRequest `apps/api/app/Http/Requests/StoreStaffRequest.php`: `name required|max:255`, `email required|email|unique:users,email`, `clinic_role required|enum`, `password required|min:8` (mirror FR-016 spec 001). Atribut + pesan i18n dari `lang/id/staff.php` + `validation.php`
- [ ] T017 [BE] [US1] Buat controller `apps/api/app/Http/Controllers/StaffController.php`: `index` (datatable staf tenant), `store` (buat user + `clinic_role`), `updateRole` (`PATCH /{id}/role`), `deactivate` (`POST /{id}/deactivate`). Pakai `StaffPolicy`. Tolak nonaktifkan admin terakhir (FR-005, warisan spec 001). Response sesuai kontrak §1. Log audit via `LogAuditAction('staff.created','staff.role_changed','staff.deactivated')`. Class ≤300, method ≤100
- [ ] T018 [BE] [US1] Daftarkan route `apps/api/routes/api.php` grup `/{tenant}/clinic/staff` dengan middleware `auth:sanctum` + policy → `StaffController`

### Frontend (FE) — paralel dengan BE pakai kontrak §1

- [ ] T019 [P] [FE] [US1] Buat halaman `apps/web/src/routes/$tenant/clinic/staff/index.tsx`: datatable staf pakai `apps/web/src/components/datatable/datatable.tsx` + `use-data-table.ts` (reuse per user input). Kolom: nama, email, peran (badge), status. Tombol "Tambah Staf". Breadcrumb `{Tenant} / Clinic / Staff`. Hanya tampil jika `user.clinic_role=admin`. File ≤300 baris (CLAUDE.md)
- [ ] T020 [FE] [US1] Buat modal form `apps/web/src/routes/$tenant/clinic/staff/components/staff-form-modal.tsx` (4 field: name, email, clinic_role, password → ≤5 field = modal, CLAUDE.md form rule). Pakai `use-form.ts` + zod. Submit `POST /{tenant}/clinic/staff` via TanStack Query mutation. Teks via `t('staff.*')`. File ≤300 baris
- [ ] T021 [FE] [US1] Buat action ubah peran + nonaktifkan di row datatable (`staff/components/staff-actions-cell.tsx`): dropdown menu konfirmasi. Handle 422 admin terakhir dengan toast i18n

**Checkpoint**: US1 berfungsi — 4 peran terdefinisi, permission aktif lintas modul (validasi via V1 quickstart). Gate lintas-story siap dipakai.

---

## Phase 4: User Story 2 - Manajemen Layanan/Treatment & Harga (Priority: P1)

**Goal**: Admin kelola master layanan (nama, deskripsi, harga, status). Layanan jadi acuan booking/rekam medis/POS.

**Independent Test**: Buat 3 layanan harga berbeda, verifikasi muncul saat pilihan booking & POS. Harga negatif ditolak. (quickstart.md V2, US2 Independent Test)

### Backend (BE)

- [ ] T022 [P] [BE] [US2] Buat migration `apps/api/database/migrations/2026_07_06_110000_create_services_table.php`: `id`, `tenant_id FK`, `name string(255)`, `description text nullable`, `price decimal(12,2)`, `status enum(active,archived) default active`, timestamp. Index `(tenant_id, status)` (data-model.md Index Strategi)
- [ ] T023 [BE] [US2] Buat model `apps/api/app/Models/Service.php`: `BelongsToTenant` + `#[ScopedBy([TenantScope::class])]`. Fillable `name,description,price,status`. Cast `status`→`ServiceStatus`, `price`→decimal. Relasi `hasMany Booking`, `hasMany TreatmentRecord`. Class ≤300
- [ ] T024 [P] [BE] [US2] Buat policy `apps/api/app/Policies/ServicePolicy.php`: Admin = CRUD; Dokter/Terapis = `view` only; Kasir = tidak ada (R2 matriks). Gate `clinic.access`
- [ ] T025 [BE] [US2] Buat FormRequest `app/Http/Requests/ServiceRequest.php` (store+update): `name required|string|max:255`, `price required|decimal:2|gte:0` (FR-011 tidak negatif), `description nullable|string`, `status enum`. Pesan i18n `lang/id/service.php`
- [ ] T026 [BE] [US2] Buat controller `app/Http/Controllers/ServiceController.php`: `index` (datatable, hanya yang `status=active` di opsi pilihan), `store`, `show`, `update`, `destroy` → panggil `ArchiveServiceAction` set `status=archived` (FR-013, bukan hapus permanen). Resource `ServiceResource`. Response sesuai kontrak §2. Class ≤300
- [ ] T027 [P] [BE] [US2] Buat action `apps/api/app/Actions/ArchiveServiceAction.php`: set `status=archived`, return model. Dipakai `destroy`. Method ≤100 baris
- [ ] T028 [BE] [US2] Daftarkan route `/{tenant}/clinic/services` → `ServiceController` dengan policy middleware

### Frontend (FE)

- [ ] T029 [P] [FE] [US2] Buat halaman `apps/web/src/routes/$tenant/clinic/services/index.tsx`: datatable layanan (nama, harga, status badge). Tombol "Tambah Layanan". Breadcrumb `{Tenant} / Clinic / Services`. File ≤300 baris
- [ ] T030 [FE] [US2] Buat modal form `services/components/service-form-modal.tsx` (4 field: name, description, price, status → ≤5 field = modal, CLAUDE.md). Pakai `use-form.ts` + zod (price gte 0). Teks via `t('service.*')`. File ≤300 baris

**Checkpoint**: US2 berfungsi — master layanan tersedia untuk dirujuk booking/POS/rekam medis.

---

## Phase 5: User Story 3 - Manajemen Pasien & Riwayat Kunjungan (Priority: P1)

**Goal**: Staf (Admin/Dokter/Kasir) daftarkan pasien + cari + lihat riwayat kunjungan agregasi.

**Independent Test**: Daftarkan 1 pasien, verifikasi muncul di daftar + bisa dipilih saat booking/POS. Duplikat telepon → peringatan. (quickstart.md V3, US3 Independent Test)

### Backend (BE)

- [ ] T031 [P] [BE] [US3] Buat migration `apps/api/database/migrations/2026_07_06_120000_create_patients_table.php`: `id`, `tenant_id FK`, `name string(255)`, `birth_date date nullable`, `gender enum(male,female,other) nullable`, `phone string(50)`, `whatsapp string(50) nullable`, `address text nullable`, `notes text nullable`, timestamp. Index `(tenant_id, phone)` (data-model.md)
- [ ] T032 [BE] [US3] Buat model `apps/api/app/Models/Patient.php`: `BelongsToTenant` + scope. Fillable sesuai kolom. Relasi `hasMany Booking`, `hasMany MedicalRecord`, `hasMany Transaction`. Class ≤300
- [ ] T033 [P] [BE] [US3] Buat policy `app/Policies/PatientPolicy.php`: Admin/Dokter/Kasir = `viewAny/view/create/update`; Terapis = `view/viewAny` only (R2 matriks). Gate `clinic.access`
- [ ] T034 [BE] [US3] Buat FormRequest `app/Http/Requests/PatientRequest.php`: `name required`, `phone required|string|max:50`, `birth_date nullable|date|before:today`, `gender nullable|enum`, `whatsapp nullable`, `address nullable`. **Tidak ada unique:patients,phone** (FR-023 peringatan bukan block). Pesan i18n `lang/id/patient.php`
- [ ] T035 [BE] [US3] Buat controller `app/Http/Controllers/PatientController.php`: `index` (datatable + `?search=` nama/telepon FR-021), `store` — setelah simpan, cek duplikat telepon di tenant: `Patient::where('tenant_id',..)->where('phone',..)->where('id','!=',$new->id)->exists()` → sertakan `meta.duplicate_warning` + `duplicate_patient_id` (FR-023, R duplikat). `history($id)` → agregasi booking+treatment terurut kronologis (FR-022). `show`, `update`. Resource `PatientResource`. Response sesuai kontrak §3. Class ≤300, method ≤100
- [ ] T036 [BE] [US3] Daftarkan route `/{tenant}/clinic/patients` + `GET /{tenant}/clinic/patients/{id}/history`

### Frontend (FE)

- [ ] T037 [P] [FE] [US3] Buat halaman `patients/index.tsx`: datatable pasien (nama, telepon, jenis kelamin) + search bar (reuse `datatable-toolbar`). Breadcrumb `{Tenant} / Clinic / Patients`. File ≤300 baris
- [ ] T038 [FE] [US3] Buat halaman form `patients/new.tsx` dan `patients/$id/edit.tsx` (6 field: name, birth_date, gender, phone, whatsapp, address → >5 field = halaman, CLAUDE.md form rule). Pakai `use-form.ts` + zod. Handle response `meta.duplicate_warning` → tampilkan dialog konfirmasi (tidak block). Teks via `t('patient.*')`. File ≤300 baris
- [ ] T039 [FE] [US3] Buat halaman `patients/$id/history.tsx`: tampilan riwayat kunjungan (gabungan booking + treatment) terurut kronologis. Breadcrumb `{Tenant} / Clinic / Patients / {Name} / History`. File ≤300 baris

**Checkpoint**: US3 berfungsi — pasien tersedia sebagai subjek booking/rekam medis/POS, riwayat agregasi tampil.

---

## Phase 6: User Story 4 - Booking & Jadwal (Priority: P2)

**Goal**: Staf buat booking (pasien + layanan + assignee + tanggal/jam), kelola status (pending→confirmed→done / cancelled), lihat jadwal, peringatan bentrok (non-blocking).

**Independent Test**: Buat 1 booking, ubah status confirmed lalu done, booking muncul di jadwal. Booking kedua assignee sama slot beririsan → peringatan, tetap tersimpan. (quickstart.md V4, US4 Independent Test, SC-008)

### Backend (BE)

- [ ] T040 [P] [BE] [US4] Buat migration `apps/api/database/migrations/2026_07_06_130000_create_bookings_table.php`: `id`, `tenant_id FK`, `patient_id FK`, `service_id FK`, `assignee_id FK→users`, `start_at datetime`, `end_at datetime`, `status enum(BookingStatus) default pending`, `notes text nullable`, `status_changed_at timestamp nullable`, timestamp. Index `(tenant_id, assignee_id, start_at, end_at)` untuk overlap+jadwal, `(tenant_id, start_at)` (data-model.md, FR-035, SC-008)
- [ ] T041 [BE] [US4] Buat model `app/Models/Booking.php`: `BelongsToTenant` + scope. Fillable sesuai kolom. Cast `status`→`BookingStatus`, datetime. Relasi `belongsTo Patient,Service,Assignee(User)`; `hasOne MedicalRecord`, `hasOne Transaction`. Helper cek transisi status valid (FR-031: `done` tidak boleh `cancelled`, edge case). Class ≤300
- [ ] T042 [P] [BE] [US4] Buat policy `app/Policies/BookingPolicy.php`: semua peran klinik = `viewAny/view/create/update` (R2 matriks). Gate `clinic.access`. Validasi `assignee_id` punya `clinic_role` in [doctor,therapist] di FormRequest
- [ ] T043 [BE] [US4] Buat service `app/Services/BookingOverlapService.php`: method `detect(Booking $booking): array` — query booking lain dengan `assignee_id` sama + `start_at < other.end_at AND end_at > other.start_at` + status ≠ cancelled (R8). Return array booking bentrok. Method ≤100 baris
- [ ] T044 [BE] [US4] Buat FormRequest `app/Http/Requests/BookingRequest.php`: `patient_id` exists in tenant, `service_id` exists+active, `assignee_id` exists + `clinic_role` in (doctor,therapist), `start_at required|date|after:now`, `end_at required|date|after:start_at`. Pesan i18n `lang/id/booking.php`
- [ ] T045 [BE] [US4] Buat controller `app/Http/Controllers/BookingController.php`: `store` → simpan status `pending`, panggil `BookingOverlapService::detect`, sertakan `meta.overlap_warnings` (FR-035 non-blocking, R8). `updateStatus($id)` → enforce transisi valid (422 jika invalid, FR-031). `schedule(from,to,view)` → list booking aktif terurut per waktu+assignee (FR-032). `index`, `show`, `update`, `destroy`. Resource `BookingResource`. Response sesuai kontrak §4. Class ≤300, method ≤100
- [ ] T046 [BE] [US4] Daftarkan route `/{tenant}/clinic/bookings` + `PATCH /{tenant}/clinic/bookings/{id}/status` + `GET /{tenant}/clinic/bookings/schedule`

### Frontend (FE)

- [ ] T047 [P] [FE] [US4] Buat komponen `apps/web/src/components/schedule/schedule-grid.tsx`: CSS grid (baris = slot waktu, kolom = assignee atau hari) dari data `GET /bookings/schedule`. `date-fns` untuk formatting tanggal/jam. Reuse `ui/card` + `ui/badge`. File ≤300 baris (R5)
- [ ] T048 [FE] [US4] Buat halaman `bookings/index.tsx`: toggle view jadwal (hari/minggu) via `schedule-grid` + list booking. Breadcrumb `{Tenant} / Clinic / Bookings`. File ≤300 baris
- [ ] T049 [FE] [US4] Buat modal form `bookings/components/booking-form-modal.tsx` (5 field: patient_id, service_id, assignee_id, start_at, end_at → ≤5 field = modal, CLAUDE.md). Select pasien/layanan/assignee via combobox (reuse `ui/combobox`). Date/time via `react-day-picker`. Submit → handle `meta.overlap_warnings` → toast/dialog peringatan (tidak block, FR-035). File ≤300 baris
- [ ] T050 [FE] [US4] Buat action update status `bookings/components/booking-status-action.tsx`: dropdown (pending→confirmed→done, atau cancel). Disable `cancelled` jika status `done` (edge case)

**Checkpoint**: US4 berfungsi — booking + jadwal + peringatan bentrok non-blocking. Booking `done` siap dirujuk rekam medis (US7) & transaksi (US5).

---

## Phase 7: User Story 6 - Inventory Produk & Stok (Priority: P2)

**Goal**: Admin kelola produk + saldo stok real-time via pergerakan (in/out_manual/sold_pos/rollback). Riwayat stok + badge "stok menipis".

**Independent Test**: Buat produk, stok masuk 10, jual 2 via POS, stok keluar manual 1 (rusak) → saldo = 7. (quickstart.md V6, US6 Independent Test, SC-004). **Disebelum US5** karena POS butuh produk.

### Backend (BE)

- [ ] T051 [P] [BE] [US6] Buat migration `apps/api/database/migrations/2026_07_06_140000_create_products_table.php`: `id`, `tenant_id FK`, `name string(255)`, `unit string(50)`, `stock_balance integer default 0`, `min_threshold integer default 0`, `price decimal(12,2)`, `status enum(active,archived) default active`, timestamp. Index `(tenant_id, status)` (data-model.md)
- [ ] T052 [P] [BE] [US6] Buat migration `apps/api/database/migrations/2026_07_06_140100_create_stock_movements_table.php`: `id`, `tenant_id FK`, `product_id FK`, `type enum(StockMovementType)`, `quantity integer`, `balance_after integer`, `related_type string nullable` (morph), `related_id bigint unsigned nullable`, `note string(255) nullable`, `created_at`. Index `(tenant_id, product_id, created_at)` (FR-064, R7)
- [ ] T053 [BE] [US6] Buat model `app/Models/Product.php`: `BelongsToTenant` + scope. Fillable `name,unit,stock_balance,min_threshold,price,status`. Cast. Accessor `is_low_stock = stock_balance <= min_threshold` (FR-065). Relasi `hasMany StockMovement`, `hasMany TransactionItem`. Class ≤300
- [ ] T054 [BE] [US6] Buat model `app/Models/StockMovement.php`: `BelongsToTenant` + scope. Fillable sesuai kolom. Cast `type`→enum. Relasi `belongsTo Product`, `morphTo related`. Class ≤300
- [ ] T055 [BE] [US6] Buat service `app/Services/StockService.php`: method `adjust(Product $product, StockMovementType $type, int $quantity, ?string $note, ?Model $related): StockMovement` — **satu pintu mutasi stok** (R7). DB transaction: lock product, insert `stock_movements` dengan `balance_after`, update `product.stock_balance`. Method ≤100 baris
- [ ] T056 [P] [BE] [US6] Buat policy `app/Policies/ProductPolicy.php` + `app/Policies/StockMovementPolicy.php`: Admin only CRUD (R2 matriks). Gate `clinic.access`
- [ ] T057 [BE] [US6] Buat FormRequest `app/Http/Requests/ProductRequest.php` (name, unit, stock_balance gte:0, min_threshold gte:0, price gte:0, status) + `app/Http/Requests/StockMovementRequest.php` (type enum, quantity gt:0, note). Pesan i18n `lang/id/{product,inventory}.php`
- [ ] T058 [BE] [US6] Buat controller `app/Http/Controllers/ProductController.php`: `index` (datatable + flag `is_low_stock`), `store`, `show`, `update`, `destroy` → arsip (FR-066, tidak hapus permanen). Resource `ProductResource` sertakan `is_low_stock`. Class ≤300
- [ ] T059 [BE] [US6] Buat controller `app/Http/Controllers/StockMovementController.php`: `store` (produk, type, qty, note) → `StockService::adjust` (FR-061 restock, FR-062 out_manual). `indexByProduct($product_id)` → riwayat pergerakan (FR-064). Response sesuai kontrak §6
- [ ] T060 [BE] [US6] Daftarkan route `/{tenant}/clinic/products` + `/{tenant}/clinic/products/{id}/stock-movements` (POST + GET)

### Frontend (FE)

- [ ] T061 [P] [FE] [US6] Buat halaman `products/index.tsx`: datatable produk (nama, satuan, saldo, status, badge "stok menipis" jika `is_low_stock`). Breadcrumb `{Tenant} / Clinic / Products`. File ≤300 baris
- [ ] T062 [FE] [US6] Buat modal form `products/components/product-form-modal.tsx` (5 field: name, unit, stock_balance, min_threshold, price → ≤5 field = modal). Pakai `use-form.ts` + zod. Teks via `t('product.*')`. File ≤300 baris
- [ ] T063 [FE] [US6] Buat halaman `inventory/index.tsx`: form catat stok masuk/keluar + riwayat pergerakan per produk. Breadcrumb `{Tenant} / Clinic / Inventory`. File ≤300 baris

**Checkpoint**: US6 berfungsi — produk + stok real-time via `StockService`. Siap dipakai POS (US5) untuk validasi & pengurangan stok.

---

## Phase 8: User Story 5 - Kasir/POS: Penjualan, Pembayaran & Invoice (Priority: P2)

**Goal**: Kasir buat transaksi multi-item (layanan+produk), subtotal otomatis, stok produk berkurang real-time, catat pembayaran lunas, terbitkan invoice. Batal → rollback stok.

**Independent Test**: Transaksi 1 layanan + 1 produk, bayar lunas, invoice terbit, stok produk berkurang, masuk data laporan. (quickstart.md V5, US5 Independent Test, SC-003/004/005)

### Backend (BE)

- [ ] T064 [P] [BE] [US5] Buat migration `apps/api/database/migrations/2026_07_06_150000_create_transactions_table.php`: `id`, `tenant_id FK`, `patient_id FK nullable`, `booking_id FK nullable`, `cashier_id FK→users`, `invoice_number string(50) unique(tenant)`, `subtotal decimal(12,2)`, `payment_status enum(PaymentStatus) default unpaid`, `cancelled_at timestamp nullable`, timestamp. Index `(tenant_id, invoice_number)` unique, `(tenant_id, payment_status, created_at)` (data-model.md)
- [ ] T065 [P] [BE] [US5] Buat migration `2026_07_06_150100_create_transaction_items_table.php`: `id`, `tenant_id FK`, `transaction_id FK`, `product_id FK nullable`, `service_id FK nullable`, `name string(255)` snapshot (R6), `unit_price decimal(12,2)` snapshot (FR-056), `qty integer`, `subtotal decimal(12,2)`, timestamp. Index `(tenant_id, transaction_id)`, `(tenant_id, product_id)`, `(tenant_id, service_id)`
- [ ] T066 [P] [BE] [US5] Buat migration `2026_07_06_150200_create_payments_table.php`: `id`, `tenant_id FK`, `transaction_id FK`, `method enum(PaymentMethod)`, `amount decimal(12,2)`, `paid_at datetime`, timestamp
- [ ] T067 [P] [BE] [US5] Buat migration `2026_07_06_150300_create_invoices_table.php`: `id`, `tenant_id FK`, `transaction_id FK unique`, `issued_at datetime`, timestamp
- [ ] T068 [BE] [US5] Buat model `app/Models/Transaction.php`: `BelongsToTenant` + scope. Fillable. Cast `payment_status`→enum, datetime. Helper `generateInvoiceNumber()` (`INV-YYYYMMDD-XXXX`). Relasi `belongsTo Patient,Booking,Cashier`; `hasMany TransactionItem,Payment`; `hasOne Invoice`. Class ≤300
- [ ] T069 [P] [BE] [US5] Buat model `app/Models/TransactionItem.php`, `app/Models/Payment.php`, `app/Models/Invoice.php` — masing-masing `BelongsToTenant` + scope + relasi (data-model.md). Class ≤300 per file
- [ ] T070 [BE] [US5] Buat service `app/Services/TransactionService.php`: `create(array $data): Transaction` — DB transaction: validasi stok produk per item (`stock_balance >= qty`, FR-053), snapshot `name`+`unit_price` dari master (FR-056), hitung subtotal (FR-051), insert `transaction_items`, **panggil `StockService::adjust(...,sold_pos,...)` per item produk** (FR-052), generate invoice number. Method ≤100 baris. Orkestrasi 1 pintu
- [ ] T071 [BE] [US5] Buat service `app/Services/InvoiceService.php`: render data invoice dari transaction + items + payments + tenant + patient (konten untuk HTML print-view, R4). Method ≤100 baris
- [ ] T072 [P] [BE] [US5] Buat action `app/Actions/PayTransactionAction.php`: catat payment, jika `sum(payments) ≥ subtotal` → set `payment_status=paid` (FR-055). Peringatan kelebihan bayar via return flag (edge case). Method ≤100
- [ ] T073 [P] [BE] [US5] Buat action `app/Actions/CancelTransactionAction.php`: untuk tiap item produk, `StockService::adjust(...,rollback,...)` (FR-058), set `cancelled_at`. Method ≤100
- [ ] T074 [P] [BE] [US5] Buat policy `app/Policies/TransactionPolicy.php` + `InvoicePolicy.php`: Admin = rw; Kasir = rw + print; lainnya ditolak (R2 matriks). Gate `clinic.access`
- [ ] T075 [BE] [US5] Buat FormRequest `app/Http/Requests/TransactionRequest.php` (patient_id nullable, booking_id nullable, items array required each [service_id|product_id, qty>0]) + `PaymentRequest.php` (method enum, amount>0, paid_at). Pesan i18n `lang/id/{pos,invoice}.php`
- [ ] T076 [BE] [US5] Buat controller `app/Http/Controllers/TransactionController.php`: `store` → `TransactionService::create` (422 jika stok kurang FR-053). `index` datatable transaksi (status, total, pasien). `show`. `cancel` → `CancelTransactionAction`. Response sesuai kontrak §7. Resource `TransactionResource`. Class ≤300
- [ ] T077 [BE] [US5] Buat controller `app/Http/Controllers/PaymentController.php`: `store` → `PayTransactionAction`. Response sertakan `payment_status` baru
- [ ] T078 [BE] [US5] Buat controller `app/Http/Controllers/InvoiceController.php`: `show($transaction_id)` → render HTML print-view (R4, FR-057) dari `InvoiceService`. Route via web middleware (`routes/web.php`) atau API yang return HTML, untuk `window.print()`
- [ ] T079 [BE] [US5] Daftarkan route `/{tenant}/clinic/transactions` + `POST /{id}/payments` + `POST /{id}/cancel` + `GET /{id}/invoice`

### Frontend (FE)

- [ ] T080 [FE] [US5] Buat halaman `pos/index.tsx`: form transaksi multi-item (pilih pasien, tambah item layanan/produk + qty, subtotal real-time client-side) + panel pembayaran. >5 field + logic berat = halaman (CLAUDE.md form rule). Validasi stok: tampilkan error sebelum submit (FR-053). Submit `POST /transactions` → handle sukses → tampilkan invoice. Breadcrumb `{Tenant} / Clinic / POS`. File ≤300 baris
- [ ] T081 [FE] [US5] Buat komponen `pos/components/transaction-item-list.tsx` (line item selector + qty + subtotal) + `pos/components/payment-panel.tsx` (metode + jumlah + cek lunas) — pecah agar `pos/index.tsx` ≤300 baris. Reuse combobox produk/layanan. File ≤300 per file
- [ ] T082 [FE] [US5] Buat halaman `pos/transactions.tsx` (list riwayat transaksi): datatable (invoice_number, pasien, total, status). Tombol "Lihat Invoice" + "Batalkan" (rollback stok). Breadcrumb `{Tenant} / Clinic / POS / Transactions`. File ≤300 baris
- [ ] T083 [FE] [US5] Buat halaman invoice print-view `pos/invoices/$id.tsx`: render invoice dari `GET /{id}/invoice`, tombol "Cetak" trigger `window.print()`. Layout print-friendly (detail item, harga, total, metode, identitas pasien+klinik FR-057). File ≤300 baris

**Checkpoint**: US5 berfungsi — POS multi-item, stok real-time akurat 100% (SC-004), rollback tanpa selisih (SC-005), transaksi lengkap <2 menit (SC-003). Transaksi lunas siap untuk laporan (US8).

---

## Phase 9: User Story 7 - Rekam Medis: SOAP, Treatment & Foto Before/After (Priority: P3)

**Goal**: Dokter/Terapis isi rekam medis SOAP terikat booking done + treatment record + foto before/after. Riwayat tampil di profil pasien.

**Independent Test**: Dari booking done, buat rekam medis SOAP + 2 foto (before+after), verifikasi tersimpan & muncul di riwayat treatment pasien. (quickstart.md V7, US7 Independent Test, SC-007)

### Backend (BE)

- [ ] T084 [P] [BE] [US7] Buat migration `apps/api/database/migrations/2026_07_06_160000_create_medical_records_table.php`: `id`, `tenant_id FK`, `booking_id FK unique`, `patient_id FK`, `author_id FK→users`, `subjective/objective/assessment/plan text nullable`, timestamp. Index `(tenant_id, booking_id)` unique (data-model.md R10)
- [ ] T085 [P] [BE] [US7] Buat migration `2026_07_06_160100_create_treatment_records_table.php`: `id`, `tenant_id FK`, `medical_record_id FK`, `service_id FK nullable`, `service_name string(255)` snapshot, `notes text nullable`, timestamp
- [ ] T086 [P] [BE] [US7] Buat migration `2026_07_06_160200_create_medical_photos_table.php`: `id`, `tenant_id FK`, `medical_record_id FK`, `type enum(MedicalPhotoType)`, `path string(255)`, timestamp
- [ ] T087 [BE] [US7] Buat model `app/Models/MedicalRecord.php`: `BelongsToTenant` + scope. Fillable SOAP. Relasi `belongsTo Booking,Patient,Author(User)`; `hasMany TreatmentRecord,MedicalPhoto`. Class ≤300
- [ ] T088 [P] [BE] [US7] Buat model `app/Models/TreatmentRecord.php`, `app/Models/MedicalPhoto.php` — `BelongsToTenant` + scope + relasi (data-model.md R10). Cast type enum. Class ≤300
- [ ] T089 [P] [BE] [US7] Buat policy `app/Policies/MedicalRecordPolicy.php`: Admin/Dokter/Terapis = rw; Kasir = ditolak (FR-044). Gate `clinic.access`
- [ ] T090 [BE] [US7] Buat FormRequest `app/Http/Requests/MedicalRecordRequest.php` (booking_id exists+tenant, SOAP text nullable) + `TreatmentRecordRequest.php` (service_id exists, notes) + `MedicalPhotoRequest.php` (`file required|image|mimes:jpg,jpeg,png|max:2048`, `type enum` — R3 native Laravel rule). Pesan i18n `lang/id/medical_record.php`
- [ ] T091 [BE] [US7] Buat action `app/Actions/UploadMedicalPhotoAction.php`: simpan file ke disk `public` path `medical-photos/{tenant_id}/{record_id}/` (R3), buat record `MedicalPhoto`. Method ≤100
- [ ] T092 [BE] [US7] Buat controller `app/Http/Controllers/MedicalRecordController.php`: `store` (booking harus bisa diisi rekam medis, unique booking_id), `addTreatment`, `addPhoto` (multipart → `UploadMedicalPhotoAction`). `patientTreatments($patient_id)` → riwayat treatment pasien (FR-043). Response sesuai kontrak §5. Resource `MedicalRecordResource`. Class ≤300
- [ ] T093 [BE] [US7] Daftarkan route `/{tenant}/clinic/medical-records` + `POST /{id}/treatments` + `POST /{id}/photos` + `GET /patients/{id}/treatments`. Pastikan disk `public` ter-link (`php artisan storage:link`, R3)

### Frontend (FE)

- [ ] T094 [P] [FE] [US7] Buat komponen `apps/web/src/components/medical-photos/photo-uploader.tsx`: upload before/after, preview, validasi client (jpg/png ≤2MB). Reuse `ui/attachment` atau `ui/input`. File ≤300 baris
- [ ] T095 [FE] [US7] Buat halaman `medical-records/new.tsx` (booking_id, SOAP 4 field + treatment records repeatable + photo uploader → >5 field + logic = halaman, CLAUDE.md form rule). Pakai `use-form.ts` + field-array untuk treatment records. Submit multi-step. Breadcrumb `{Tenant} / Clinic / Medical Records / New`. File ≤300 baris
- [ ] T096 [FE] [US7] Buat halaman `patients/$id/treatments.tsx`: riwayat treatment pasien (SOAP + foto). Breadcrumb `{Tenant} / Clinic / Patients / {Name} / Treatments`. File ≤300 baris

**Checkpoint**: US7 berfungsi — rekam medis SOAP + treatment + foto terikat booking done, permission dokter/terapis/admin, <5 menit (SC-007).

---

## Phase 10: User Story 8 - Laporan: Omzet, Penjualan Treatment & Produk (Priority: P3)

**Goal**: Admin lihat laporan omzet (transaksi lunas), penjualan per treatment, per produk, rentang tanggal.

**Independent Test**: Setelah transaksi POS lunas, buka laporan omzet hari ini, verifikasi total cocok jumlah transaksi lunas. (quickstart.md V8, US8 Independent Test, SC-006)

### Backend (BE)

- [ ] T097 [BE] [US8] Buat service `app/Services/ReportService.php`: `revenue(from,to)` = `sum(transaction_items.subtotal)` join transactions where `payment_status=paid` + `cancelled_at null` dalam rentang (FR-070, FR-059, FR-073). `servicesReport(from,to)` = agregasi per service_id (qty, revenue, FR-071). `productsReport(from,to)` = per product_id (FR-072). Rentang lokal tenant (R11, `Carbon::parse()->startOfDay()`). Method ≤100. Pakai query builder aggregate
- [ ] T098 [P] [BE] [US8] Buat policy `app/Policies/ReportPolicy.php`: Admin only (FR-075). Gate `clinic.access`
- [ ] T099 [BE] [US8] Buat FormRequest `app/Http/Requests/ReportRangeRequest.php` (`from date required`, `to date required|after_or_equal:from`). Pesan i18n `lang/id/report.php`
- [ ] T100 [BE] [US8] Buat controller `app/Http/Controllers/ReportController.php`: `revenue`, `services`, `products` → `ReportService`. Rentang tanpa data → `{total_revenue:0,...}` + `meta.empty=true` (FR-074, bukan error). Response sesuai kontrak §8. Class ≤300
- [ ] T101 [BE] [US8] Daftarkan route `/{tenant}/clinic/reports/{revenue,services,products}` dengan policy

### Frontend (FE)

- [ ] T102 [FE] [US8] Buat halaman `reports/index.tsx`: filter rentang tanggal (`react-day-picker`) + 3 tab/view (omzet, penjualan treatment, penjualan produk). Tabel hasil + opsional grafik `recharts`. Hanya tampil jika `user.clinic_role=admin`. Breadcrumb `{Tenant} / Clinic / Reports`. File ≤300 baris
- [ ] T103 [P] [FE] [US8] Pecah komponen `reports/components/revenue-summary.tsx`, `reports/components/service-sales-table.tsx`, `reports/components/product-sales-table.tsx` agar `reports/index.tsx` ≤300 baris. Handle `meta.empty` → tampilkan pesan kosong i18n (FR-074). File ≤300 per file

**Checkpoint**: US8 berfungsi — laporan omzet + penjualan per modul, admin-only, omzet cocok manual (SC-006).

---

## Phase 11: Polish & Cross-Cutting Concerns

**Purpose**: Pemantapan lintas modul, konsistensi, validasi menyeluruh.

- [ ] T104 [P] [BE] Audit i18n: pastikan 0 hardcode string UI di response API — semua via `__()` dari `lang/id/*.php` (CLAUDE.md). Cek semua controller + resource
- [ ] T105 [P] [FE] Audit breadcrumb: setiap halaman `$tenant/clinic/*` punya breadcrumb hierarki valid, item aktif = `BreadcrumbPage` (bukan link), parent = link nyata (CLAUDE.md breadcrumb wajib)
- [ ] T106 [P] [FE] Audit ukuran file: pastikan semua file `apps/web/src/**` ≤300 baris, ekstrak jika melebihi (CLAUDE.md STRICT). Cek terutama `pos/index.tsx`, `medical-records/new.tsx`, `schedule-grid.tsx`
- [ ] T107 [P] [BE] Audit ukuran class/method: pastikan semua class PHP ≤300 baris & method ≤100 baris (CLAUDE.md). Cek `TransactionService`, `StockService`, `BookingOverlapService`, `ReportService`
- [ ] T108 [P] [BE] Tambah index DB yang ditandai data-model.md jika belum (overlap, schedule, report aggregate) — verify via migration
- [ ] T109 [BE] Validasi isolasi tenant: pastikan semua 13 model bisnis pakai `BelongsToTenant` + `TenantScope`. Probe via tinker: query dari konteks tenant A tidak return row tenant B (FR-012)
- [ ] T110 [P] [FE] Audit aksesibilitas form & dialog (label, aria, focus trap) sesuai `ui/dialog` + `ui/field` (CLAUDE.md accessibility basics)
- [ ] T111 Jalankan seluruh skenario validasi `quickstart.md` V1–V8 end-to-end. Perbaiki temuan. Pastikan SC-001..010 terpenuhi
- [ ] T112 [P] [BE] Syntax check seluruh file baru: `php -l <file>` per file (CLAUDE.md izin). Probe `php artisan tinker` untuk alur kritis (stok, overlap, laporan)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: T001 BLOCKER — verifikasi spec 001. T002–T004 paralel setelah T001 lolos.
- **Foundational (Phase 2)**: Bergantung Setup. BLOCKS semua user story.
- **User Stories (Phase 3–10)**: Bergantung Foundational. Lihat dependency antar-story di bawah.
- **Polish (Phase 11)**: Bergantung semua story selesai.

### User Story Dependencies (urut eksekusi rekomendasi)

```
Phase 3: US1 (P1) Staff & Role          — blok semua (Gate permission dipakai semua)
Phase 4: US2 (P1) Service               — butuh US1 (Gate); dirujuk US4/US5/US7
Phase 5: US3 (P1) Patient               — butuh US1 (Gate); dirujuk US4/US5/US7
   └─ US2 + US3 boleh paralel (file beda, sama-sama butuh US1)
Phase 6: US4 (P2) Booking + Schedule    — butuh US1 + US2 + US3
Phase 7: US6 (P2) Product + Inventory   — butuh US1 (Gate); dipakai US5
   └─ US4 dan US6 boleh paralel
Phase 8: US5 (P2) POS                   — butuh US3 (patient) + US2 (service) + US6 (product + StockService)
Phase 9: US7 (P3) Medical Record        — butuh US4 (booking done) + US2 (service)
Phase 10: US8 (P3) Report               — butuh US5 (transaksi lunas)
```

**Catatan urutan vs prioritas**: US6 (P2) dieksekusi sebelum US5 (P2) karena US5 butuh produk + `StockService` dari US6 (dependency-aware, bukan priority order murni). Label US tetap cocok dengan spec.md.

### Within Each User Story

- Model sebelum service
- Service sebelum controller/endpoint
- Backend endpoint + kontrak sebelum integrasi frontend penuh (FE boleh stub pakai kontrak)
- Policy wajib agar Gate aktif (SC-009)
- Checkpoint: validasi via skenario `quickstart.md` story terkait

### Parallel Opportunities

- Semua task `[P]` dalam satu fase boleh paralel (file beda)
- Dalam satu story: BE model + FE halaman boleh paralel (FE pakai kontrak stub)
- Antar-story setelah Foundational: US2 || US3; lalu US4 || US6; US5 setelah US6; US7 || US8 (tergantung US4/US5 selesai)
- Tim 2 orang: BE kerja `apps/api/`, FE kerja `apps/web/` sinkron via kontrak `contracts/api-contracts.md`

---

## Parallel Example: User Story 2 (Service)

```bash
# BE models + policy + migration (file beda):
Task: "Create services migration in apps/api/database/migrations/..."
Task: "Create ServicePolicy in apps/api/app/Policies/ServicePolicy.php"

# FE halaman + modal (file beda, pakai kontrak §2 sebagai stub):
Task: "Create services/index.tsx datatable"
Task: "Create service-form-modal.tsx"
```

---

## Implementation Strategy

### MVP First (User Story 1 + fondasi P1)

1. Phase 1: Setup (verifikasi spec 001!)
2. Phase 2: Foundational (peran klinik + Gate + i18n + forms wrapper)
3. Phase 3: US1 — 4 peran + permission aktif
4. Phase 4+5: US2 + US3 — master layanan + pasien
5. **STOP & VALIDATE**: quickstart V1, V2, V3 — peran, layanan, pasien jalan.

MVP inti (operasional dasar) = Setup + Foundational + US1 + US2 + US3. Berhenti di sini sudah cukup untuk demo manajemen staf/layanan/pasien.

### Incremental Delivery

1. Setup + Foundational → foundation ready
2. + US1 + US2 + US3 → MVP manajemen (P1 lengkap)
3. + US4 + US6 → booking + inventory (P2 parsial)
4. + US5 → POS lengkap + stok real-time
5. + US7 + US8 → rekam medis + laporan (P3 lengkap)
6. Polish → validasi V1–V8, SC-001..010

### Parallel Team Strategy

- BE: migrasi + model + service + policy + controller per story
- FE: datatable + form + halaman per story, pakai kontrak sebagai stub
- Integrasi penuh setelah endpoint BE siap

---

## Notes

- `[P]` = file beda, tanpa dependency task sebelumnya
- `[USx]` label memetakan task ke user story (traceability)
- Tiap story independen dites via skenario `quickstart.md` (V1–V8)
- Policy wajib tiap modul agar Gate `clinic.access` aktif (SC-009 100% akses terlarang ditolak)
- `StockService` = satu pintu mutasi stok (R7), dipakai POS (`sold_pos`) + inventory (`in/out_manual`) + cancel (`rollback`) — KDRF konsistensi SC-004/005
- Harga historik di-snapshot di `transaction_items.unit_price` + `name` (FR-056, R6) — jangan join ulang ke master saat invoice/laporan
- i18n: DILARANG hardcode string UI (CLAUDE.md). Teks baru → tambah key `lang/id/{modul}.php` + `t('modul.key')` di FE
- Breadcrumb wajib setiap halaman dalam (CLAUDE.md)
- File React ≤300 baris, class PHP ≤300, method ≤100 (CLAUDE.md STRICT)
- Commit per task atau kelompok logis. Stop di checkpoint untuk validasi story independen

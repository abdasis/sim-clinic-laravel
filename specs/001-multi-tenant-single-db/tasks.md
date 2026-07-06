# Tasks: Multi-Tenant Single Database

**Input**: Design documents from `/specs/001-multi-tenant-single-db/`

**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

**Tests**: Tidak diminta eksplisit di spec → test tasks TIDAK dibuat. Tiap task non-trivial backend wajib tinggalkan 1 cek runnable (syntax `php -l` / `php artisan test` manual via quickstart.md).

**Organization**: Tasks dikelompokkan per user story. Tiap user story dipecah jalur **Backend (BE)** dan **Frontend (FE)** supaya 2 programmer kerja paralel — BE di `apps/api/`, FE di `apps/web/`. Jalur BE dan FE dalam satu story saling lepas kecuali kontrak endpoint (FE butuh route BE selesai untuk integrasi; pakai kontrak di `contracts/api-contracts.md` sebagai stub).

## Format: `[ID] [P?] [Story?] Description`

- **[P]**: Bisa paralel (file beda, tanpa dependency task sebelumnya)
- **[Story]**: Label user story (US1..US5)
- **[BE]**: Programmer backend (`apps/api/`)
- **[FE]**: Programmer frontend (`apps/web/`)
- Path eksplisit di setiap task

## Path Conventions

- **Backend**: `apps/api/` (Laravel 13, PHP 8.3)
- **Frontend**: `apps/web/` (React 19, TanStack Start)
- Path di deskripsi sudah absolut relatif repo root.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Inisialisasi project, dependency, struktur folder. BE dan FE paralel.

- [ ] T001 [P] [BE] Buat branch `001-multi-tenant-single-db` dari `main` (jika belum). Install `spatie/laravel-activitylog` via composer (user input eksplisit — audit log pakai spatie, bukan tabel custom). Jalankan `composer require spatie/laravel-activitylog`, `php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"`, `--tag="activitylog-config"`. User jalankan command install sendiri (CLAUDE.md: composer require boleh, tapi konfirmasi dulu)
- [ ] T002 [P] [FE] Install dependency frontend baru di `apps/web/package.json`: `bun add @hugeicons/react @hugeicons/core-free-icons` (research.md §5, user input eksplisit). Pastikan `@tanstack/react-router`, `@tanstack/react-query`, `@tanstack/react-table ^8.21.3`, `react-hook-form ^7.81.0`, `@hookform/resolvers`, `zod ^4.4.3`, `sonner`, `tailwindcss ^4` sudah ada
- [ ] T003 [P] [BE] Buat folder struktur backend: `apps/api/app/{Scopes,Concerns,Services,Actions}`, pastikan `apps/api/app/Concerns/InteractsWithDataTable.php` (sudah ada) tidak rusak
- [ ] T004 [P] [FE] Buat folder struktur frontend: `apps/web/src/{components/forms,hooks,utils,routes/$tenant,routes/central}`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Infrastruktur inti yang WAJIB selesai sebelum user story manapun. BE: tenant resolver + scope + audit + i18n. FE: i18n helper + form reusable base.

**⚠️ CRITICAL**: Tidak ada user story boleh mulai sebelum fase ini selesai.

- [ ] T005 [BE] Buat migration `apps/api/database/migrations/2026_07_06_000001_create_tenants_table.php`: kolom `id`, `name string(255)`, `slug string(255) unique`, `phone string(50)`, `status enum(active,inactive) default active`, timestamp. Unique index `slug` (data-model.md Index Strategi)
- [ ] T006 [P] [BE] Buat migration `apps/api/database/migrations/2026_07_06_000002_add_tenant_role_status_to_users_table.php`: tambah `tenant_id bigint unsigned FK → tenants.id not null`, `role enum(platform_admin,tenant_admin,member) not null`, `status enum(pending,active,inactive) default active`, `email_verified_at nullable`. Unique index `email` global, index `tenant_id` (FR-015, FR-024, data-model.md)
- [ ] T007 [P] [BE] Tidak buat migration audit_logs custom. Pakai migration spatie (publish dari T001). Tabel default `activity_log`; rename ke `audit_logs` via custom model (T010). Verifikasi migration spatie terpublish di `apps/api/database/migrations/`
- [ ] T008 [BE] Buat model `apps/api/app/Models/Tenant.php`: fillable `name,slug,phone,status`, cast status enum, relasi `hasMany User`. Audit log via spatie Activity (query `properties->tenant_id`, bukan relasi Eloquent). Class ≤300 baris (CLAUDE.md)
- [ ] T009 [P] [BE] Buat model `apps/api/app/Models/User.php` (extend/ubah existing): tambah fillable `tenant_id,role,status`, cast, relasi `belongsTo Tenant`. Audit causer via spatie Activity (morph `causer_id`/`causer_type`, bukan relasi Eloquent langsung). Validasi password regex disimpan di FormRequest, bukan model
- [ ] T010 [P] [BE] Buat model `apps/api/app/Models/Activity.php` extend `Spatie\Activitylog\Models\Activity`: set `protected $table = 'audit_logs'`. Daftar di `config/activitylog.php` → `'activity_model' => \App\Models\Activity::class`. Tidak tambah fillable/kolom custom — `tenant_id` disimpan di `properties` JSON (data-model.md AuditLog via spatie)
- [ ] T011 [BE] Buat trait `apps/api/app/Concerns/BelongsToTenant.php`: saat creating model, isi `tenant_id` dari `app('tenant')->id` jika belum di-set. Letakkan di `boot()` trait (research.md §2, data-model.md)
- [ ] T012 [BE] Buat global scope `apps/api/app/Scopes/TenantScope.php`: filter `WHERE tenant_id = app('tenant')->id`. Baca tenant dari container. Untuk model global (tanpa `BelongsToTenant`), scope tidak berlaku (FR-003, FR-012)
- [ ] T013 [BE] Daftarkan `TenantScope` via attribute `#[ScopedBy([TenantScope::class])]` pada model tenant-scopeable (di terapkan per-model saat model dibuat di masing-masing story). Pastikan scope skip saat `app('tenant')` null (mis. CLI/seeding) — return query tanpa filter
- [ ] T014 [BE] Buat middleware `apps/api/app/Http/Middleware/ResolveTenant.php`: parse `{tenant}` slug dari route, resolve `Tenant` by slug, simpan ke `app()->instance('tenant', $tenant)`. Slug tidak dikenal → 404 dengan pesan i18n jelas (FR-007, SC-004). Tidak ekspos detail internal
- [ ] T015 [P] [BE] Buat middleware `apps/api/app/Http/Middleware/EnsureTenantActive.php`: tolak akses jika tenant `status=inactive` → 423 dengan pesan i18n + akhiri sesi (FR-009, SC-005). Boleh skip untuk admin platform pada route `/central/*`
- [ ] T016 [BE] Daftarkan middleware `ResolveTenant`, `EnsureTenantActive` di `apps/api/bootstrap/app.php` alias + route group. Route group `Route::prefix('{tenant}')->middleware(['resolve.tenant','ensure.tenant.active'])->group(...)` (research.md §1)
- [ ] T017 [P] [BE] Buat action `apps/api/app/Actions/LogAuditAction.php`: wrapper `activity()->performedOn($subject)->causedBy($actor)->withProperties(array_merge(['tenant_id'=>$tenant?->id], $context))->log($action)`. `$action` = action code (`tenant.registered`, `user.login`, dst). `$actor` null = anonim. Method ≤100 baris. Dipakai semua story untuk audit (FR-028, data-model.md)
- [ ] T018 [P] [BE] Buat file bahasa `apps/api/lang/id/general.php`, `lang/id/auth.php`, `lang/id/tenant.php` dengan key dasar (save, cancel, login, register, tenant_registered, validation, dst). Locale default `config/app.php` → `id` (CLAUDE.md i18n wajib)
- [ ] T019 [BE] Update `apps/api/app/Http/Middleware/HandleInertiaRequests.php` (atau sanctum SPA share): share `translations` = `['general'=>__('general'), 'auth'=>__('auth'), 'tenant'=>__('tenant')]` ke frontend (CLAUDE.md React i18n wajib)
- [ ] T020 [BE] Buat seeder `apps/api/database/seeders/CentralTenantSeeder.php`: buat tenant `slug=central` + user admin platform `role=platform_admin` di tenant central. Jalankan via `php artisan db:seed` (quickstart.md Setup)
- [ ] T021 [P] [FE] Buat util `apps/web/src/utils/trans.ts` dan hook `apps/web/src/hooks/use-trans.ts`: `t(key)` baca dari `usePage().props.translations` (CLAUDE.md React i18n). Fallback ke key jika tidak ada
- [ ] T022 [FE] Buat form reusable base di `apps/web/src/components/forms/`: `form-field.tsx`, `form-input.tsx`, `form-select.tsx`, `form-submit.tsx`, `use-form.ts` (wrapper react-hook-form + zod resolver). Tiap file ≤300 baris (CLAUDE.md). Pakai `@shadcn/react` + `sonner` untuk toast. Semua label/placeholder via `t()` — DILARANG hardcode string UI (CLAUDE.md i18n)

**Checkpoint**: Foundation ready — BE punya tenant resolver, scope, audit, i18n, seeder. FE punya `t()` helper + form base. User story boleh mulai paralel.

---

## Phase 3: User Story 1 - Tenant Self-Registration (Priority: P1) 🎯 MVP

**Goal**: Calon user daftar tenant publik → sistem buat tenant + admin pertama atomik, tenant langsung aktif.

**Independent Test**: Isi form registrasi publik → tenant + admin pertama terbuat bersamaan → admin pertama bisa login di konteks tenant baru. (quickstart.md V1)

### Backend (BE)

- [ ] T023 [P] [BE] [US1] Buat FormRequest `apps/api/app/Http/Requests/RegisterTenantRequest.php`: rules `company_name required|max:255`, `phone required|max:50|regex phone`, `email required|email|unique:users,email` (FR-015), `password required|min:8|regex:/^(?=.*[A-Za-z])(?=.*\d).{8,}$/` (FR-016). Atribut + pesan i18n dari `lang/id/validation.php` + `lang/id/tenant.php`
- [ ] T024 [BE] [US1] Buat service `apps/api/app/Services/TenantRegistrationService.php`: DB transaction — derive `slug` dari `company_name` (URL-safe, reject non-URL-safe FR-005), cek unique `tenants.slug` (FR-004), buat `Tenant` status active + `User` role tenant_admin status active (FR-014, FR-017). Atomik: gagal salah satu → rollback (edge case spec). Panggil `LogAuditAction('tenant.registered', ...)`. Class ≤300, method ≤100 (CLAUDE.md)
- [ ] T025 [BE] [US1] Buat controller `apps/api/app/Http/Controllers/TenantRegistrationController.php`: `store(RegisterTenantRequest)` → panggil service → response 201 `{data:{tenant,user}, meta:{redirect_to:"/{slug}/login"}}` sesuai kontrak `contracts/api-contracts.md` §2. Tambah key bahasa `lang/id/tenant.php` untuk pesan sukses/gagal
- [ ] T026 [BE] [US1] Daftarkan route `POST /register` (publik, no tenant prefix) di `apps/api/routes/api.php` → `TenantRegistrationController@store`

### Frontend (FE) — paralel dengan BE pakai kontrak §2 sebagai stub

- [ ] T027 [P] [FE] [US1] Buat landing page `apps/web/src/routes/index.tsx` (root `/`): hero + tombol login header → redirect ke `/central/login` (FR-026, FR-027). Breadcrumb tidak wajib di landing publik. Semua teks via `t('tenant.*')` / `t('general.*')`. File ≤300 baris (CLAUDE.md)
- [ ] T028 [FE] [US1] Buat halaman registrasi tenant `apps/web/src/routes/register.tsx` (>5 field? 4 field → boleh modal, TAPI alur sukses redirect → pilih halaman terpisah agar jelas). Form pakai `use-form.ts` + zod schema (company_name, phone, email, password). Submit `POST /register` via TanStack Query mutation. Validasi client mirror kontrak §2. Breadcrumb `Home / Register`. Teks via `t()`. File ≤300 baris (CLAUDE.md)
- [ ] T029 [FE] [US1] Setup TanStack Router route tree untuk root `/`, `/register`, dan layout `$tenant` stub (preparation US2) di `apps/web/src/routeTree.gen.ts` / route file. Pastikan dev route dapat slug `central`

**Checkpoint**: US1 berfungsi sendiri — registrasi publik buat tenant + admin, redirect ke `/{slug}/login`. BE dan FE selesai paralel.

---

## Phase 4: User Story 2 - Tenant Identification via URL Path (Priority: P1)

**Goal**: Sistem kenali tenant dari segmen path `/{tenant}/...`, aktifkan konteks per request. Slug invalid → 404 jelas.

**Independent Test**: Akses `/{valid-slug}/...` → konteks tenant aktif. Akses `/{invalid-slug}/...` → 404 pesan i18n. (quickstart.md V2)

### Backend (BE)

- [ ] T030 [BE] [US2] Validasi `ResolveTenant` (T014) handle: slug valid → `app('tenant')` set; slug invalid → 404 i18n `__('tenant.not_found')`; non-URL-safe slug → 404 (FR-005, FR-007, SC-004). Test manual via `php artisan tinker` + route dummy
- [ ] T031 [BE] [US2] Buat `AuthController` `apps/api/app/Http/Controllers/AuthController.php`: `login(email,password)` validasi kredensial + tenant aktif (FR-009), buat Sanctum token, log audit `user.login`, response `{data:{user,token}, meta:{redirect_to:"/{tenant}"}}` (kontrak §3). `logout` revoke token → 204
- [ ] T032 [BE] [US2] Daftarkan route tenant-scoped `POST /{tenant}/login`, `POST /{tenant}/logout` di `apps/api/routes/api.php` dengan middleware `ResolveTenant` (tanpa `EnsureTenantActive` di login agar bisa beri pesan khusus, atau biarkan aktif untuk nonaktif = 423). Tambah key `lang/id/auth.php`

### Frontend (FE)

- [ ] T033 [P] [FE] [US2] Buat layout tenant `apps/web/src/routes/$tenant.tsx` (layout route TanStack): baca param `tenant`, tampilkan header dengan slug + breadcrumb root. Validasi slug invalid tampilkan error page i18n `t('tenant.not_found')` (FR-007)
- [ ] T034 [FE] [US2] Buat halaman login tenant `apps/web/src/routes/$tenant/login.tsx`: form email+password (≤5 field → boleh di card, bukan modal). Submit `POST /{tenant}/login`. Sukses → redirect `/{slug}` dashboard. Breadcrumb `{Tenant} / Login`. Teks via `t('auth.*')`. File ≤300 baris

**Checkpoint**: US2 berfungsi — akses path tenant dikenali, login tenant berhasil, slug invalid ditolak jelas.

---

## Phase 5: User Story 3 - Data Isolation antar Tenant (Priority: P2)

**Goal**: Data tenant-scopeable terisolasi otomatis. Akses ID lintas tenant → dianggap tidak ada. Data global tetap bisa diakses semua tenant.

**Independent Test**: Buat data di tenant A, switch ke B → data A tidak muncul. GET resource A dari konteks B → 404. (quickstart.md V3, V7)

### Backend (BE)

- [ ] T035 [P] [BE] [US3] Buat migration `apps/api/database/migrations/2026_07_06_000004_create_patients_table.php` (contoh tenant-scopeable, template): `id`, `tenant_id FK`, `name string(255)`, timestamp. Index `tenant_id` (data-model.md Tenant-Scopeable Data)
- [ ] T036 [BE] [US3] Buat model `apps/api/app/Models/Patient.php`: pakai trait `BelongsToTenant` + attribute `#[ScopedBy([TenantScope::class])]`. fillable `name`. Relasi `belongsTo Tenant`. Verifikasi scope aktif via tinker: query dari konteks tenant A tidak return row tenant B
- [ ] T037 [BE] [US3] Buat trait `apps/api/app/Concerns/IsGlobal.php` (atau cukup konvensi: model global TIDAK pakai `BelongsToTenant` + TIDAK pakai `TenantScope`, jadi trait hanya marker kosong untuk dokumentasi — pilih lazy: marker class 1 baris dengan `ponytail:` comment). Contoh model global `apps/api/app/Models/Specialty.php` + migration (FR-008)
- [ ] T038 [BE] [US3] Buat controller contoh `apps/api/app/Http/Controllers/PatientController.php` (demo isolasi): CRUD sederhana tanpa `where tenant_id` manual (scope otomatis). Akses ID lintas tenant → 404 via scope (FR-012). Route `/{tenant}/patients` di `routes/api.php`. Tambah key `lang/id/patient.php`

### Frontend (FE)

- [ ] T039 [FE] [US3] Buat halaman datatable patient `apps/web/src/routes/$tenant/patients/index.tsx`: pakai `apps/web/src/components/datatable/` (reuse, research.md §5). Server-side via endpoint `GET /{tenant}/patients` (params `page,per_page,search,sort,direction,filters`). Breadcrumb `{Tenant} / Patients`. Teks via `t('patient.*')`. File ≤300 baris (CLAUDE.md)

**Checkpoint**: US3 berfungsi — isolasi data otomatis via global scope, data global bersama tetap tampil.

---

## Phase 6: User Story 4 - Multi-User per Tenant (Priority: P2)

**Goal**: Admin tenant undang anggota via email, user set password lalu login. Tolak duplikat + proteksi admin terakhir.

**Independent Test**: Admin undang email → undangan terkirim → user accept set password → login. Undang email sudah aktif → 422. Hapus admin terakhir → 422. (quickstart.md V4)

### Backend (BE)

- [ ] T040 [P] [BE] [US4] Buat migration `apps/api/database/migrations/2026_07_06_000005_create_invitations_table.php`: `id`, `tenant_id FK`, `email string(255)`, `role enum(tenant_admin,member) default member`, `token string(64) unique`, `expires_at timestamp`, `status enum(pending,accepted,cancelled,expired) default pending`, timestamp. Index `(tenant_id, email)` (data-model.md)
- [ ] T041 [P] [BE] [US4] Buat model `apps/api/app/Models/Invitation.php`: fillable sesuai, cast, relasi `belongsTo Tenant`. Helper generate token 64 char + `expires_at` (default +7 hari)
- [ ] T042 [BE] [US4] Buat service `apps/api/app/Services/InvitationService.php`: `invite(tenant, email, role)` — cek email sudah user aktif di tenant sama → 422 (FR-022); cek email unik global (FR-015, satu email satu tenant v1); buat invitation pending; kirim notifikasi (v1 boleh return token di response, tidak kirim email sungguhan — ponytail: email sungguhan add when notifikasi siap). `accept(token, password)` — validasi token + `expires_at`, buat User active, set invitation accepted. Log audit `user.invited`. Class ≤300, method ≤100
- [ ] T043 [BE] [US4] Buat action `apps/api/app/Actions/RemoveUserAction.php`: hapus/nonaktifkan user dari tenant. Tolak jika user = admin terakhir tenant → 422 (FR-025). Log audit `user.removed`. Method ≤100 baris
- [ ] T044 [BE] [US4] Buat FormRequest `apps/api/app/Http/Requests/InvitationRequest.php`: `email required|email`, `role in:member,tenant_admin`. Pesan i18n `lang/id/tenant.php`
- [ ] T045 [BE] [US4] Buat controller `apps/api/app/Http/Controllers/UserController.php`: `index` (datatable server-side, pakai `InteractsWithDataTable`), `invite(InvitationRequest)` → service, `remove(User, RemoveUserAction)`, `role(User, role)` → log audit `user.role_changed`. Response sesuai kontrak §4. Authorize: hanya `tenant_admin` (FR-024) → 403 jika bukan
- [ ] T046 [BE] [US4] Buat controller `apps/api/app/Http/Controllers/InvitationController.php` (publik, no prefix): `show(token)` validasi + tampilkan info set password, `accept(token, password)` → service accept. Kontrak §5. Validasi password sama FR-016
- [ ] T047 [BE] [US4] Daftarkan route: `GET /{tenant}/users`, `POST /{tenant}/users/invite`, `POST /{tenant}/users/{id}/remove`, `PATCH /{tenant}/users/{id}/role` (middleware `ResolveTenant` + `EnsureTenantActive` + `auth:sanctum`); `GET /invitations/{token}`, `POST /invitations/{token}/accept` (publik). Tambah key `lang/id/tenant.php`, `lang/id/auth.php`

### Frontend (FE)

- [ ] T048 [FE] [US4] Buat halaman user list `apps/web/src/routes/$tenant/users/index.tsx`: datatable reuse `components/datatable/`, server-side via `GET /{tenant}/users`. Kolom name, email, role, status, created_at. Tombol invite (admin only). Breadcrumb `{Tenant} / Users`. Teks via `t('tenant.*')`. File ≤300 baris
- [ ] T049 [FE] [US4] Buat modal undang user `apps/web/src/routes/$tenant/users/components/invite-modal.tsx` (2 field: email, role → ≤5 field, modal OK per CLAUDE.md form rule): pakai `use-form.ts` + zod. Submit `POST /{tenant}/users/invite`. Toast `sonner` sukses/error i18n. Teks via `t()`
- [ ] T050 [P] [FE] [US4] Buat halaman accept invitation `apps/web/src/routes/invitations.$token.tsx` (atau `/invitations/[token]` sesuai konvensi TanStack): tampilkan form set password, submit `POST /invitations/{token}/accept`. Sukses → redirect `/{slug}/login`. Breadcrumb `Invitation`. Teks via `t('auth.*')`. File ≤300 baris

**Checkpoint**: US4 berfungsi — undang, accept, tolak duplikat, proteksi admin terakhir, hapus keanggotaan.

---

## Phase 7: User Story 5 - Manajemen Tenant (Priority: P3)

**Goal**: Admin platform lihat semua tenant, ubah status aktif/non-aktif. Tenant nonaktif → akses ditolak, sesi aktif diakhiri.

**Independent Test**: Login platform admin via `/central/login` → lihat semua tenant → nonaktifkan satu → akses tenant itu ditolak. (quickstart.md V5, V6)

### Backend (BE)

- [ ] T051 [BE] [US5] Buat controller `apps/api/app/Http/Controllers/PlatformTenantController.php`: `index` (datatable server-side semua tenant, pakai `InteractsWithDataTable`, tanpa `TenantScope`), `status(Tenant, UpdateTenantStatusRequest)` → toggle active/inactive, log audit `tenant.status_changed`. Kontrak §6. Authorize: hanya `platform_admin` (di tenant central) → 403 lainnya
- [ ] T052 [BE] [US5] Buat FormRequest `apps/api/app/Http/Requests/UpdateTenantStatusRequest.php`: `status in:active,inactive`. Pesan i18n `lang/id/tenant.php`
- [ ] T053 [BE] [US5] Buat controller central login `apps/api/app/Http/Controllers/CentralAuthController.php` (atau extend `AuthController`): `POST /central/login` → validasi, resolve tenant dari user `tenant_id`, response `{data:{user,tenant:{slug}}, meta:{redirect_to:"/{slug}"}}` (kontrak §1). Log audit `user.login`
- [ ] T054 [BE] [US5] Daftarkan route `GET /central/tenants`, `PATCH /central/tenants/{id}/status`, `POST /central/login` (middleware `auth:sanctum` + cek `platform_admin` untuk tenant management). Tambah key `lang/id/tenant.php`

### Frontend (FE)

- [ ] T055 [P] [FE] [US5] Buat halaman central login `apps/web/src/routes/central/login.tsx`: form email+password, submit `POST /central/login`, sukses → redirect `/{slug}`. Breadcrumb `Central / Login`. Teks via `t('auth.*')`. File ≤300 baris
- [ ] T056 [FE] [US5] Buat halaman central tenant list `apps/web/src/routes/central/tenants/index.tsx`: datatable reuse, server-side via `GET /central/tenants`. Kolom name, slug, phone, status, created_at. Toggle status via `PATCH /central/tenants/{id}/status` (konfirmasi dialog). Breadcrumb `Central / Tenants`. Teks via `t('tenant.*')`. File ≤300 baris
- [ ] T057 [FE] [US5] Buat komponen status toggle `apps/web/src/routes/central/tenants/components/status-toggle.tsx` (jika halaman mendekati 300 baris, extract). Teks via `t()`

**Checkpoint**: US5 berfungsi — admin platform kelola tenant, nonaktifkan berhenti akses.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Konsistensi lintas story.

- [ ] T058 [P] [BE] Audit semua response error pakai `__()` i18n, status code konsisten (401/403/404/422/423) sesuai kontrak. Tambah key bahasa yang tertinggal di `lang/id/*.php`
- [ ] T059 [P] [FE] Audit semua halaman punya breadcrumb refleksi hierarki nyata (CLAUDE.md). Item terakhir = `BreadcrumbPage` (bukan link), item lain = link ke parent route
- [ ] T060 [P] [FE] Ganti usage `lucide-react` bertahap ke `@hugeicons/react` (`HugeiconsIcon icon={XxxIcon}`) di komponen baru. Tidak wajib migrasi penuh di v1 (research.md §5)
- [ ] T061 [BE] Verifikasi batas class ≤300 baris & method ≤100 baris di semua file BE baru (CLAUDE.md). Extract jika ada yang melebihi
- [ ] T062 [FE] Verifikasi batas file React ≤300 baris di semua file FE baru (CLAUDE.md). Extract ke `components/` subfolder jika melebihi
- [ ] T063 [BE] Jalankan validasi `php artisan test` + skenario quickstart.md V1–V7 manual (user jalankan sendiri command test — CLAUDE.md: tidak auto-run)
- [ ] T064 [FE] Jalankan `bun run test` (Vitest) jika ada test; validasi manual quickstart.md V1–V7 di browser (user jalankan sendiri — CLAUDE.md: tidak auto-run dev server)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: Tanpa dependency — mulai langsung. BE (T001, T003) paralel dengan FE (T002, T004)
- **Foundational (Phase 2)**: Bergantung Setup selesai — BLOK semua user story. BE T005–T020, FE T021–T022. Banyak task [P] dalam fase
- **User Stories (Phase 3–7)**: Semua bergantung Foundational. Setelah foundation ready, tiap story BE dan FE jalan paralel
- **Polish (Phase 8)**: Bergantung semua story yang diinginkan selesai

### User Story Dependencies

- **US1 (P1)**: Mulai setelah Foundational. FE T027–T029 pakai kontrak §2 sebagai stub — boleh mulai sebelum BE T023–T026 selesai (integrasi akhir setelah BE ready). MVP minimum
- **US2 (P1)**: Mulai setelah Foundational. Bergantung US1 (butuh tenant terdaftar untuk test login). FE T033–T034 butuh BE T030–T032 untuk integrasi
- **US3 (P2)**: Mulai setelah Foundational. Bergantung US1+US2 (tenant + resolver). Backend T035–T038 mandiri; FE T039 butuh endpoint BE
- **US4 (P2)**: Mulai setelah Foundational. Bergantung US1+US2 (admin tenant konteks). BE T040–T047; FE T048–T050 butuh endpoint BE
- **US5 (P3)**: Mulai setelah Foundational. Bergantung US1 (tenant ada). BE T051–T054; FE T055–T057 butuh endpoint BE

### Within Each User Story

- BE: migration → model → service/action → controller → route
- FE: route/layout → page → form/component → integrasi endpoint BE
- FE boleh mulai paralel dengan BE memakai kontrak `contracts/api-contracts.md` sebagai stub, integrasi paling akhir

### Parallel Opportunities

- Phase 1: T001/T003 (BE) paralel T002/T004 (FE) — 2 programmer
- Phase 2: task bertanda [P] paralel sesama programmer; BE dan FE paralel antar programmer
- Phase 3–7: **dalam satu story, BE dan FE paralel antar 2 programmer** (pola utama sesuai user input)
- Dalam satu jalur (BE/FE), task [P] paralel sesama file beda

---

## Parallel Example: User Story 1 (BE || FE)

```bash
# Programmer Backend (jalur BE):
Task T023: "RegisterTenantRequest di apps/api/app/Http/Requests/"
Task T024: "TenantRegistrationService di apps/api/app/Services/"
Task T025: "TenantRegistrationController di apps/api/app/Http/Controllers/"
Task T026: "Route POST /register di apps/api/routes/api.php"

# Programmer Frontend (jalur FE, paralel pakai kontrak §2):
Task T027: "Landing page apps/web/src/routes/index.tsx"
Task T028: "Halaman registrasi apps/web/src/routes/register.tsx"
Task T029: "Route tree stub apps/web/src/routeTree.gen.ts"
```

---

## Parallel Example: User Story 4 (BE || FE)

```bash
# Programmer Backend:
Task T040: "Migration invitations"
Task T041: "Model Invitation"
Task T042: "InvitationService"
Task T043: "RemoveUserAction"
Task T045: "UserController"
Task T046: "InvitationController"
Task T047: "Routes"

# Programmer Frontend (paralel, stub kontrak §4 §5):
Task T048: "User list datatable"
Task T049: "Invite modal"
Task T050: "Accept invitation page"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Selesaikan Phase 1: Setup
2. Selesaikan Phase 2: Foundational (CRITICAL — blok semua story)
3. Selesaikan Phase 3: User Story 1 (BE + FE paralel)
4. **STOP dan VALIDATE**: quickstart.md V1 — registrasi tenant + admin pertama + login
5. Deploy/demo jika siap

### Incremental Delivery

1. Setup + Foundational → Foundation ready
2. + US1 → test V1 → demo (MVP)
3. + US2 → test V2 → demo
4. + US3 → test V3 → demo
5. + US4 → test V4 → demo
6. + US5 → test V5–V7 → demo
7. Polish → validasi penuh

### Parallel Team Strategy (2 programmer)

1. Tim selesaikan Setup + Foundational bersama
2. Setelah Foundational ready:
   - Programmer BE: kerja jalur BE tiap story (migration, model, service, controller, route)
   - Programmer FE: kerja jalur FE tiap story (route, page, form, integrasi)
3. Tiap story: BE dan FE paralel. FE pakai kontrak sebagai stub, integrasi akhir setelah BE endpoint ready
4. Checkpoint tiap story: kedua jalur selesai → validasi quickstart.md

---

## Notes

- [P] task = file beda, tanpa dependency task sebelumnya
- [Story] label petik task ke user story
- [BE] / [FE] = programmer backend / frontend — inti pemisahan paralel sesuai user input
- Tiap user story boleh selesai & diuji sendiri
- Commit per task atau kelompok logis. Pesan commit bahasa Indonesia/Inggris tanpa emoji (CLAUDE.md)
- Stop di checkpoint untuk validasi story sendiri
- Hindar: task vague, konflik file sama, dependency cross-story yang rusak independensi
- CLAUDE.md: jangan auto-run `pint`, `bun dev`, `bun run build`, `composer run dev` — user jalankan sendiri
# Tasks: Platform Infrastructure — Tenants & Audit Log

**Input**: Design documents from `specs/003-platform-infra/`

**Prerequisites**: plan.md (required), spec.md (required), research.md, data-model.md, contracts/api-contracts.md, quickstart.md

**Tests**: SKIPPED per user instruction ("fokus fungsi skip test"). Tidak ada task test. Validasi fungsi via `quickstart.md` manual + `php -l` di titik kritis. Konstitusi TDD ditangguhkan untuk iterasi ini atas permintaan eksplisit user; `ponytail: tulis test di iterasi berikutnya sebelum push/merge`.

**Organization**: Tasks grouped by user story (spec.md P1→P2). Sebagian besar BE Langkah 1 sudah ada — task mencerminkan delta net-new + hardening saja.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story (US1–US4, maps to spec.md)
- Exact file paths in descriptions

## Path Conventions

- BE: `apps/api/` (Laravel)
- FE: `apps/web/` (TanStack Start)
- Monorepo root-relative

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Pasang dependency audit log (Langkah 2 prasyarat).

- [ ] T001 Install `spatie/laravel-activitylog` via composer di `apps/api/composer.json` (jalankan `composer require spatie/laravel-activitylog` di `apps/api/`; user jalankan sendiri)
- [ ] T002 Publish config activitylog di `apps/api/config/activitylog.php` (jalankan `php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config"`; user jalankan sendiri)

**Checkpoint**: spatie terpasang, config terpublish. Belum dipakai sampai Phase 2.

---

## Phase 2: Foundational (Audit Log Infra — Langkah 2)

**Purpose**: Migrasi audit log native → spatie. Infra sekali jadi, dipakai semua fitur via `LogAuditAction`. **BLOCKS** US4 + memengaruhi logging US1/US2/US3.

**⚠️ CRITICAL**: Selesaikan fase ini sebelum verifikasi logging di user story manapun.

- [ ] T003 Buat custom model `App\Models\Activity` extending `Spatie\Activitylog\Models\Activity` dengan `protected $table = 'audit_logs'` di `apps/api/app/Models/Activity.php`
- [ ] T004 Set `activity_model` => `App\Models\Activity::class` di `apps/api/config/activitylog.php`
- [ ] T005 Buat migration adaptasi `apps/api/database/migrations/2026_08_14_000000_migrate_audit_logs_to_spatie.php`: rename `action`→`description`, add `log_name` (string nullable), add `causer_type` (string nullable) + backfill `'App\Models\User'` for non-null `causer_id`, drop FK `causer_id` (native nullOnDelete ke users) + pastikan `causer_id` nullable, backfill `properties->tenant_id` dari kolom `tenant_id` eksisting (PostgreSQL), drop composite index `[tenant_id, action]`, drop kolom `tenant_id`. Lihat `research.md` D2.
- [ ] T006 Rewrite `App\Actions\LogAuditAction::handle()` di `apps/api/app/Actions/LogAuditAction.php` — ganti `AuditLog::create([...])` dengan rantai `activity()->causedBy($causer)->performedOn($subject)->withProperties(array_merge($context, $tenant ? ['tenant_id' => $tenant->id] : []))->log($action)`; turunkan `log_name` dari prefix action (mis. `tenant.registered` → `tenant`); signature `handle(string $action, ?Model $subject=null, ?User $causer=null, array $context=[], ?Tenant $tenant=null)` tetap. Return type ubah ke `?Activity`.
- [ ] T007 Hapus model native `App\Models\AuditLog` di `apps/api/app/Models/AuditLog.php` (post-migrasi; gantikan `App\Models\Activity`) — hapus file + hapus referensi import di seluruh caller (18 controller/service) yang import `App\Models\AuditLog`, ganti ke `App\Models\Activity` bila perlu tiping
- [ ] T008 Update return type / docblock caller `LogAuditAction` bila ada yang mengetik return `AuditLog` → `?Activity` di `apps/api/app/Services/TenantRegistrationService.php`, `apps/api/app/Http/Controllers/CentralAuthController.php`, `apps/api/app/Http/Controllers/PlatformTenantController.php`, `apps/api/app/Http/Controllers/UserController.php`, `apps/api/app/Http/Controllers/StaffController.php`, `apps/api/app/Services/InvitationService.php`, `apps/api/app/Actions/RemoveUserAction.php`, `apps/api/app/Http/Controllers/AuthController.php` — signature tidak berubah, hanya return type
- [ ] T009 Verifikasi `php -l` lulus untuk `apps/api/app/Models/Activity.php`, `apps/api/app/Actions/LogAuditAction.php`, dan setiap file caller yang diubah (user jalankan: `php -l <file>`)

**Checkpoint**: Audit infra spatie aktif. `Activity::where('properties->tenant_id', …)` jalan. Semua caller lama tetap compile via wrapper.

---

## Phase 3: User Story 1 — Login Central + Dashboard (Priority: P1) 🎯 MVP

**Goal**: Admin platform login central (BE sudah ada) + lihat dashboard central dengan breadcrumb + nav + guard FE (net-new FE).

**Independent Test**: Seed central → buka `/central/login` → login `admin@platform.test` → mendarat di `/central` dashboard dengan breadcrumb Central→Dashboard; tenant_admin yang navigasi ke `/central` diarahkan ke `/central/login`.

**Implementation for User Story 1** (BE login existing — tidak diubah; FE net-new):

- [ ] T010 [P] [US1] Tambah `hasPlatformRole(): boolean` (cek `user.role === 'platform_admin'`) di `apps/web/src/lib/auth.ts`
- [ ] T011 [P] [US1] Tambah i18n keys dashboard central (mis. `central.dashboard`, `central.dashboard.welcome`, `central.dashboard.total_tenants`, `central.dashboard.active_tenants`) di `apps/api/lang/id/*.php` group yang sesuai (mis. `central.php` atau `tenant.php`) — identifier English, nilai Indonesia semi-formal
- [ ] T012 [US1] Tambah nav item Dashboard ke `AppSidebar` navMain di `apps/web/src/routes/central/route.tsx` (url `/central`, icon hugeicons, `isActive` saat pathname === `/central`) + guard: bila `!hasPlatformRole()` redirect ke `/central/login` (kecuali route sudah `/central/login`)
- [ ] T013 [US1] Buat halaman dashboard `apps/web/src/routes/central/index.tsx` (`createFileRoute('/central')`): `ClinicBreadcrumb` items `[{label: t("general.central")}, {label: t("central.dashboard")}]` (item terakhir = page non-link), heading, ringkasan tenant (total + aktif via `apiGet('/central/tenants',{per_page:1})` baca `meta.total`, atau stat card sederhana) + link ke `/central/tenants`. Gaya Linear: border subtle, shadow tipis, density tinggi, tooltip pada aksi. Reuse `DataTable`/`Card`/`Button` existing. File ≤300 baris (ekstrak stat card ke `apps/web/src/routes/central/components/` bila melebihi).
- [ ] T014 [US1] Jalankan `bun run generate-routes` dari `apps/web` agar route tree `tsr` memuat `/central` (user jalankan sendiri)
- [ ] T015 [US1] Verifikasi `npx tsc --noEmit --incremental` lulus di `apps/web` (user jalankan sendiri)

**Checkpoint**: US1 berfungsi — login central → dashboard + breadcrumb + guard. Audit `user.login` tercatat via infra Phase 2.

---

## Phase 4: User Story 2 — Registrasi Tenant (Priority: P1)

**Goal**: Pendaftar daftar tenant baru, slug URL-safe unik + reject reserved `central`, audit `tenant.registered` tercatat (BE sebagian besar ada — hardening slug reserved).

**Independent Test**: `POST /api/register` dengan `company_name`→slug `central` ditolak 422; nama normal → 201 + tenant + tenant_admin + audit record `properties->tenant_id` terisi.

**Implementation for User Story 2** (BE `TenantRegistrationService` + `RegisterTenantRequest` + FE `register.tsx` existing — hardening):

- [ ] T016 [P] [US2] Tambah reserved slug list (konstanta `['central']`) + reject bila `Str::slug(company_name)` hasilnya di reserved list, di `apps/api/app/Services/TenantRegistrationService.php` (abort 422 dengan pesan i18n) — saat ini hanya cek duplikat, jadikan eksplisit
- [ ] T017 [P] [US2] Tambah validasi `company_name` reject bila `Str::slug` menghasilkan string kosong di `apps/api/app/Http/Requests/RegisterTenantRequest.php` (defensive layer di FormRequest, walau service sudah cek)
- [ ] T018 [US2] Pastikan `TenantRegistrationService::register()` di `apps/api/app/Services/TenantRegistrationService.php` memanggil `LogAuditAction` dengan `subject=$tenant`, `causer=null` (anonim), `context=['tenant_id'=>$tenant->id, 'slug'=>$tenant->slug]` — verifikasi post-migrasi Phase 2 record `Activity` tercipta dengan `properties->tenant_id` terisi
- [ ] T019 [US2] Verifikasi `php -l` lulus untuk `apps/api/app/Services/TenantRegistrationService.php` + `apps/api/app/Http/Requests/RegisterTenantRequest.php` (user jalankan sendiri)

**Checkpoint**: US2 — registrasi tenant + slug reserved `central` ditolak + audit anonim tercatat.

---

## Phase 5: User Story 3 — Toggle Status Tenant (Priority: P2)

**Goal**: Admin platform nonaktifkan/aktifkan tenant, akses diblokir saat inactive, audit `tenant.status_changed` tercatat dengan `old_status`+`new_status`. (BE `PlatformTenantController` + `EnsureTenantActive` + FE `status-toggle.tsx` existing — verifikasi + pastikan context audit.)

**Independent Test**: Login central → `PATCH /central/tenants/{id}/status` `inactive` → 200; `POST /{slug}/login` → 423; audit `tenant.status_changed` `properties.old_status=active`, `properties.new_status=inactive`.

**Implementation for User Story 3** (existing — pastikan context audit lulus ke properties):

- [ ] T020 [US3] Pastikan `PlatformTenantController::status()` di `apps/api/app/Http/Controllers/PlatformTenantController.php` memanggil `LogAuditAction` dengan `context=['old_status'=>$old, 'new_status'=>$new, 'tenant_id'=>$tenant->id]` (capture status lama sebelum update) — bila saat ini tidak passing old/new, tambahkan; verify post-migrasi `properties.old_status`/`properties.new_status` terisi
- [ ] T021 [US3] Verifikasi `EnsureTenantActive` middleware di `apps/api/app/Http/Middleware/EnsureTenantActive.php` tetap abort 423 untuk inactive (existing, no change — konfirmasi tidak regress oleh migrasi)
- [ ] T022 [US3] Verifikasi `php -l` lulus untuk `apps/api/app/Http/Controllers/PlatformTenantController.php` (user jalankan sendiri)

**Checkpoint**: US3 — toggle status + akses diblokir + audit dengan old/new status.

---

## Phase 6: User Story 4 — Audit Log Infra Reusable (Priority: P1)

**Goal**: Seluruh aksi kritis tercatat via `LogAuditAction` single-point, immutable, morph, queryable per tenant, causer nullable. (Infra sudah dibangun di Phase 2 — fase ini verifikasi end-to-end + query per tenant.)

**Independent Test**: Picu aksi (login, registrasi, status toggle) → `Activity::where('properties->tenant_id', $id)` mengembalikan record tenant; `causer` null pada `tenant.registered` tidak error; subject dihapus → record audit tetap ada.

**Implementation for User Story 4** (infra Phase 2 — verifikasi reuse + immutability):

- [ ] T023 [US4] Audit ulang seluruh 18 caller `LogAuditAction` (daftar di `docs/erd/audit_logs.md` + eksplorasi): konfirmasi tidak ada caller yang masih mengetik `AuditLog::create` atau import `App\Models\AuditLog` setelah Phase 2 T007-T008 — grep `AuditLog` di `apps/api/app/`
- [ ] T024 [P] [US4] Tambah i18n keys audit bila ada pesan audit yang user-facing (opsional, skip bila tidak ada UI audit di MVP) di `apps/api/lang/id/*.php`
- [ ] T025 [US4] Tambah catatan `ponytail: index JSON path properties->tenant_id add saat lambat` sebagai komentar di `apps/api/app/Models/Activity.php` (dokumentasi ceiling, sesuai `docs/erd/audit_logs.md`)
- [ ] T026 [US4] Jalankan `php artisan tinker` verifikasi (user jalankan sendiri): `Activity::where('properties->tenant_id', 1)->count()` jalan tanpa error; `Activity::latest()->first()->causer` resolve null tanpa error untuk record anonim

**Checkpoint**: US4 — audit infra reusable, immutable, queryable per tenant.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Konsistensi lintas story.

- [ ] T027 [P] Verifikasi semua teks UI dashboard central via `t()` (tidak ada string hardcode) di `apps/web/src/routes/central/index.tsx` + `central/route.tsx` — keys English, nilai Indonesia semi-formal
- [ ] T028 [P] Verifikasi setiap komponen FE baru ≤300 baris (dashboard + sub-komponen) — ekstrak bila melebihi
- [ ] T029 [P] Verifikasi setiap class/method BE ≤300/100 baris (`Activity`, `LogAuditAction::handle`, migration, controller yang diubah) — ekstrak bila melebihi
- [ ] T030 Jalankan `vendor/bin/pint` di `apps/api` (user jalankan sendiri) — format/lint BE
- [ ] T031 Jalankan validasi `quickstart.md` end-to-end (Validation 1–5) secara manual (user jalankan) — konfirmasi semua skenario lulus
- [ ] T032 `ponytail:` tulis catatan di `specs/003-platform-infra/tasks.md` atau commit message: test TDD ditunda iterasi ini atas permintaan user ("fokus fungsi skip test"); tambah test feature/unit di iterasi berikutnya sebelum push/merge (Konstitusi II)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: T001→T002 (composer dulu, baru publish config). Tidak ada dependency lain.
- **Foundational (Phase 2)**: BLOCKS semua user story yang mengandalkan audit (US1/US2/US3 logging + US4). T003→T004 (model dulu, config registrasi); T005 (migration) independen tapi harus sebelum T006 verifikasi runtime; T006 (rewrite LogAuditAction) tergantung T003+T004; T007 (hapus AuditLog) tergantung T006+T008 (caller bersih dulu); T008 tergantung T006; T009 verifikasi terakhir.
- **US1 (Phase 3)**: Tergantung Phase 2 (audit `user.login` via infra baru). FE tasks T010/T011 paralel.
- **US2 (Phase 4)**: Tergantung Phase 2 (audit `tenant.registered` via infra baru). T018 verifikasi tergantung T006.
- **US3 (Phase 5)**: Tergantung Phase 2 (audit `tenant.status_changed` context). T020 tergantung T006.
- **US4 (Phase 6)**: Tergantung Phase 2 lengkap (T007/T008 selesai). T23 grep audit setelah cleanup.
- **Polish (Phase 7)**: Tergantung semua story selesai.

### User Story Dependencies

- **US1 (P1)**: Setelah Foundational. Tidak tergantung story lain. FE-only + sedikit i18n BE.
- **US2 (P1)**: Setelah Foundational. Hardening BE slug reserved. Independen dari US1.
- **US3 (P2)**: Setelah Foundational. Verifikasi BE audit context. Independen dari US1/US2.
- **US4 (P1)**: Adalah konsumsi infra Foundational. Verifikasi reuse + immutability.

### Within Each User Story

- Model/config sebelum service/action
- Service/action sebelum controller/caller
- Caller update sebelum verifikasi `php -l`/`tsc`
- Story complete sebelum story prioritas berikutnya

### Parallel Opportunities

- Phase 1: T001 lalu T002 (sekuen singkat)
- Phase 2: T003 || T005 paralel (model vs migration — file berbeda); T004 setelah T003
- Phase 3 (US1): T010 (auth.ts) || T011 (lang id) paralel; T012 tergantung T010; T013 tergantung T011+T012
- Phase 4 (US2): T016 (service) || T017 (FormRequest) paralel; T018 verifikasi setelah
- Phase 6 (US4): T024 paralel dengan T023/T025
- Phase 7: T027 || T028 || T029 paralel

---

## Parallel Example: User Story 1

```bash
# FE auth guard + i18n keys berjalan paralel (file berbeda):
Task: "Tambah hasPlatformRole() di apps/web/src/lib/auth.ts"
Task: "Tambah i18n keys dashboard central di apps/api/lang/id/*.php"

# Lalu sekuen:
Task: "Tambah nav Dashboard + guard di apps/web/src/routes/central/route.tsx"
Task: "Buat dashboard apps/web/src/routes/central/index.tsx"
```

---

## Implementation Strategy

### MVP First (User Story 1 + Foundational)

1. Phase 1 Setup: pasang spatie + config
2. Phase 2 Foundational: migrasi audit ke spatie (infra)
3. Phase 3 US1: dashboard central + guard + nav
4. **STOP & VALIDATE**: jalankan quickstart Validation 2 (login central + dashboard) + Validation 4 (audit query per tenant)
5. Demo bila siap

### Incremental Delivery

1. Setup + Foundational → audit infra aktif
2. + US1 → dashboard central (MVP!)
3. + US2 → slug reserved hardening
4. + US3 → status toggle audit context
5. + US4 → verifikasi reuse audit
6. Polish → quickstart end-to-end

### Parallel Team Strategy

Dengan multiple developer:
1. Tim selesaikan Setup + Foundational bersama (infra blocking)
2. Setelah Foundational:
   - Developer A (sierly/FE): US1 dashboard
   - Developer B (ammar/BE): US2 slug hardening + US3 audit context + US4 verifikasi
3. Polish bersama

---

## Notes

- [P] tasks = file berbeda, tanpa dependency pada task belum selesai
- [Story] label = traceability ke spec.md
- Sebagian besar BE Langkah 1 sudah ada — task hanya delta net-new + hardening (lihat plan.md "Summary")
- Test SKIPPED iterasi ini (user: "fokus fungsi skip test") — `ponytail: tambah test TDD di iterasi berikutnya sebelum push/merge` (Konstitusi II)
- Setiap perubahan BE: jalankan `php -l` (user). Setiap perubahan FE: `npx tsc --noEmit --incremental` + `bun run generate-routes` bila route baru (user). Tidak auto-run dev/build.
- Commit setelah task/kelompok logis selesai. Commit message Conventional Commits, tanpa AI marker.
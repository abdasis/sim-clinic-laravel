# Tasks: Users & Invitations

**Input**: Design documents from `/specs/004-users-invitations/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/api-contracts.md, quickstart.md

**Tests**: SKIPPED per user instruction "fokus fungsional dan skip test". No test tasks generated.

**Organization**: Tasks grouped by user story. Karena BE/FE mayoritas sudah ada, sebagian besar task bersifat **revisi** file existing, bukan greenfield.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: User story this task belongs to (US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- BE: `apps/api/` (app/, database/migrations/, config/)
- FE: `apps/web/src/` (components/, routes/, hooks/, lib/)

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Pasang `spatie/laravel-permission` + publish config + enable teams.

- [ ] T001 Add `spatie/laravel-permission` via composer in `apps/api/composer.json` (run: `cd apps/api && composer require spatie/laravel-permission`)
- [ ] T002 Publish permission provider assets in `apps/api` (run: `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`) → generates `config/permission.php` + migration stub
- [ ] T003 Configure `apps/api/config/permission.php`: set `'teams' => true` and `'team_foreign_key' => 'tenant_id'`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Skema soft-delete + RBAC tables + middleware team-scope + role/permission seed. MUST complete before any user story.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

### Migrations

- [ ] T004 Create migration `apps/api/database/migrations/2026_08_14_000001_add_soft_delete_to_users_table.php`: add `deleted_at` timestamp nullable to `users` + composite index `(tenant_id, deleted_at)`
- [ ] T005 Create migration `apps/api/database/migrations/2026_08_14_000002_restrict_fk_assignee_to_users.php`: drop FK `bookings.assignee_id` → recreate with `restrictOnDelete()`
- [ ] T006 [P] Create migration `apps/api/database/migrations/2026_08_14_000003_restrict_fk_author_to_users.php`: drop FK `medical_records.author_id` → recreate with `restrictOnDelete()`
- [ ] T007 [P] Create migration `apps/api/database/migrations/2026_08_14_000004_restrict_fk_cashier_to_users.php`: drop FK `transactions.cashier_id` → recreate with `restrictOnDelete()`
- [ ] T008 Create migration `apps/api/database/migrations/2026_08_14_000005_add_clinic_role_to_invitations_table.php`: add `clinic_role` enum(admin, doctor, therapist, cashier) nullable to `invitations`

### spatie migration + guard

- [ ] T009 Edit generated spatie migration `apps/api/database/migrations/*_create_permission_tables.php`: ensure `team_foreign_key` column is `tenant_id` (the publish stub uses `team_id` by default — rename to match config) and `guard_name` default `'sanctum'`
- [ ] T010 Add `Spatie\Permission\Traits\HasRoles` + `Illuminate\Database\Eloquent\SoftDeletes` traits to `apps/api/app/Models/User.php`; add `protected string $guard_name = 'sanctum';` and `protected $dates = ['deleted_at']` (or rely on SoftDeletes cast)

### Middleware + seeder

- [ ] T011 Create `apps/api/app/Http/Middleware/SetPermissionTeamId.php`: `setPermissionsTeamId(app('tenant')->id)` when tenant bound, no-op for central routes
- [ ] T012 Register `SetPermissionTeamId` middleware alias in `apps/api/bootstrap/app.php` (as `permission.team`) and attach to tenant-scoped route groups in `apps/api/routes/api.php` (the `{tenant}` + `{tenant}/clinic` groups)
- [ ] T013 Create `apps/api/database/seeders/RolesAndPermissionsSeeder.php`: create permissions per module `{staff,service,patient,booking,medical_record,product,inventory,transaction,invoice,report}` each `.r` and `.rw`; create global roles `platform_admin`, `tenant_admin`, `member` (team_id=null); create per-team clinic role templates `admin`, `doctor`, `therapist`, `cashier` with permission matrix from `ClinicPermission::MATRIX`. Reset cache via `PermissionRegistrar::forgetCachedPermissions()` at start and end.
- [ ] T014 Revise `apps/api/database/seeders/CentralTenantSeeder.php`: after creating platform_admin user, `setPermissionsTeamId(null)` then `$user->assignRole('platform_admin')` (global role)
- [ ] T015 Revise `apps/api/database/seeders/TenantAdminSeeder.php` + `ClinicDemoSeeder.php`: after tenant + admin user created, `setPermissionsTeamId($tenant->id)`, `Role::firstOrCreate(['name'=>'admin','team_id'=>$tenant->id])` (or rely on seeder template), `$user->assignRole('tenant_admin')` (global) + `$user->assignRole('admin')` (clinic per-team); keep `clinic_role` enum + `role` enum set in sync within DB transaction

**Checkpoint**: Foundation ready — schema soft-delete + RBAC tables + team-scope middleware + roles seeded. User story implementation can begin.

---

## Phase 3: User Story 1 - Staf Klinik Masuk ke Tenant (Priority: P1) 🎯 MVP

**Goal**: Staf login tenant via `/{tenant}/login`, terautentikasi, mendarat di shell klinik dengan sidebar sesuai `clinic_role`, sesi tercatat audit.

**Independent Test**: Seed tenant + staf aktif → buka `/{slug}/login` → submit kredensial valid → mendarat di `/{slug}/clinic` dengan sidebar sesuai peran → audit `user.login` tercatat.

### Implementation for User Story 1

- [ ] T016 [US1] Revise `apps/api/app/Http/Controllers/AuthController.php`: login flow — set `setPermissionsTeamId($tenant->id)` after resolving tenant, reject user with `status != active` OR soft-deleted (`trashed()`), keep audit `user.login` naratif "Pengguna {email} berhasil masuk." via `LogAuditAction`
- [ ] T017 [US1] Verify `apps/web/src/routes/$tenant/login.tsx` (existing): uses `components/forms` (FormInput, FormSubmit, useForm) — ensure breadcrumb `ClinicBreadcrumb` shows root→tenant→Masuk (last item non-link); no string hardcode (all via `useTrans` `t()`)
- [ ] T018 [US1] Verify `apps/web/src/routes/$tenant/clinic/route.tsx` (existing): sidebar visibility derived from `clinic_role` (auth user enum) — keep FE mirror of role→module map; ensure post-login redirect lands here

**Checkpoint**: US1 functional — login tenant works, audit recorded, sidebar role-aware.

---

## Phase 4: User Story 2 - Admin Tenant Mengelola Staf (Priority: P1)

**Goal**: Admin lihat daftar staf aktif, ubah peran klinik, nonaktifkan (soft-delete). Staf soft-deleted tidak muncul di list aktif; data buatan staf tetap utuh (restrictOnDelete); admin terakhir ditolak; audit naratif "Menonaktifkan staf {name} — peran {role}".

**Independent Test**: Login admin → daftar staf → ubah peran satu staf → nonaktifkan staf lain → staf hilang dari list aktif → data booking/rekam medis/transaksi staf tetap ada → coba nonaktifkan admin terakhir ditolak 422.

### Implementation for User Story 2

#### BE — Action + Service extraction (soft-delete, admin-terakhir, spatie)

- [ ] T019 [US2] Create `apps/api/app/Actions/DeactivateStaffAction.php`: extract deactivate logic — guard admin-terakhir (count active clinic admin `whereNull('deleted_at')` per tenant, `<=1` → abort 422), set `status=inactive` + `delete()` (soft) in DB transaction, audit `staff.deactivated` naratif "Menonaktifkan staf {name} — peran {role}." (role label from `ClinicRole`). Max 100 lines.
- [ ] T020 [US2] Revise `apps/api/app/Actions/RemoveUserAction.php`: change hard-delete → soft-delete + `status=inactive`; guard tenant_admin terakhir (count active `tenant_admin` `whereNull('deleted_at')` per tenant, `<=1` → abort 422); keep audit `user.removed` naratif; ensure `setPermissionsTeamId($user->tenant_id)` before any role check
- [ ] T021 [US2] Revise `apps/api/app/Http/Controllers/StaffController.php::index`: keep `whereNotNull('clinic_role')` (soft-deleted auto-excluded by SoftDeletes scope); authorize via `$user->hasPermissionTo('staff.r')` (spatie) replacing `$this->authorize('viewAny', User::class)` if Policy migrated — or keep Policy delegating to spatie
- [ ] T022 [US2] Revise `apps/api/app/Http/Controllers/StaffController.php::updateRole`: assign spatie per-team role (`$staff->assignRole($newRole)` after `setPermissionsTeamId(tenant)`) within DB transaction alongside enum `clinic_role` update; guard clinic-admin-terakhir on downgrade from `admin`; audit `staff.role_changed` naratif "Peran staf {name} diubah dari {lama} ke {baru}."
- [ ] T023 [US2] Revise `apps/api/app/Http/Controllers/StaffController.php::deactivate`: delegate to `DeactivateStaffAction` (T019) instead of inline logic; remove inline admin-terakhir + status update code
- [ ] T024 [US2] Revise `apps/api/app/Http/Controllers/StaffController.php::store`: assign spatie per-team role (`clinic_role`) + set enum within DB transaction; audit `staff.created` naratif
- [ ] T025 [US2] Revise `apps/api/app/Http/Controllers/UserController.php::index`: ensure soft-deleted excluded (SoftDeletes scope); authorize tenant admin via spatie `hasPermissionTo` or keep `assertTenantAdmin` (platform role check) — spatie global role `tenant_admin` via `$user->hasRole('tenant_admin')`
- [ ] T026 [US2] Revise `apps/api/app/Http/Controllers/UserController.php::role`: assign spatie global role (`tenant_admin`/`member`) + set enum within DB transaction; guard tenant-admin-terakhir on downgrade; audit `user.role_changed` naratif "Peran pengguna {name} diubah dari {lama} ke {baru}."
- [ ] T027 [US2] Revise `apps/api/app/Http/Controllers/UserController.php::remove`: delegate to `RemoveUserAction` (T020, already soft-delete)

#### BE — Policies migration to spatie

- [ ] T028 [US2] Revise `apps/api/app/Policies/UserPolicy.php` (and any clinic Policy): replace `ClinicPermission::canAccess()` delegation with `$user->hasPermissionTo('{module}.{action}')` via spatie; Gate `clinic.access` in `apps/api/app/Providers/ClinicServiceProvider.php` either delegates to spatie `hasPermissionTo` or is removed in favor of direct Policy `can()`
- [ ] T029 [US2] Deprecate `apps/api/app/Services/ClinicPermission.php`: remove from Policy/Gate consumers after T028; keep file only if `RolesAndPermissionsSeeder` references `MATRIX` constant for seed (then delete after seed finalized) — `ponytail: delete file saat MATRIX tidak lagi dirujuk`

#### BE — Form Requests

- [ ] T030 [P] [US2] Revise `apps/api/app/Http/Requests/UpdateStaffRoleRequest.php`: validate `clinic_role` in `admin,doctor,therapist,cashier`; authorize via spatie `hasPermissionTo('staff.rw')`
- [ ] T031 [P] [US2] Revise `apps/api/app/Http/Requests/StoreStaffRequest.php`: validate name/email/password (FR-016 complex)/clinic_role; authorize via spatie `hasPermissionTo('staff.rw')`; email unique global rule

#### FE — Staff management (reuse datatable + forms)

- [ ] T032 [US2] Revise `apps/web/src/routes/$tenant/clinic/staff/index.tsx`: keep `DataTable` + `useDataTable`; confirm query excludes soft-deleted (BE handles); breadcrumb root→tenant→Klinik→Staf; guard `clinic_role==='admin'`
- [ ] T033 [US2] Create `apps/web/src/routes/$tenant/clinic/staff/components/deactivate-staff-dialog.tsx`: confirmation dialog (reuse shadcn Dialog/AlertDialog) for nonaktifkan staf — calls `POST /{tenant}/clinic/staff/{id}/deactivate`, toast success/error via sonner, invalidate `staff` query; show staff name + role in confirm text; i18n via `t()`. Max 300 lines.
- [ ] T034 [US2] Revise `apps/web/src/routes/$tenant/clinic/staff/components/staff-actions-cell.tsx`: add "Nonaktifkan" action wired to `deactivate-staff-dialog` (T033) + "Ubah peran" action (existing role select/modal); Tooltip + shortcut on each icon action per CLAUDE.md
- [ ] T035 [US2] Revise `apps/web/src/routes/$tenant/clinic/staff/components/staff-form-modal.tsx`: keep using `components/forms` (FormInput, FormSelect for clinic_role, FormSubmit, useForm+zod); ≤5 fields stays modal per Form Design rule; i18n labels
- [ ] T036 [US2] Revise `apps/web/src/routes/$tenant/users/index.tsx`: keep `DataTable`; ensure soft-deleted users not shown; breadcrumb root→tenant→Pengguna; admin-only guard
- [ ] T037 [US2] Revise `apps/web/src/routes/$tenant/users/components/invite-modal.tsx`: keep `components/forms`; add `clinic_role` select (FormSelect) alongside `role`; wire to `POST /{tenant}/users/invite`; toast + invalidate

#### FE — i18n keys

- [ ] T038 [P] [US2] Add Indonesian i18n keys to `apps/api/lang/id/staff.php`, `tenant.php`, `clinic.php`: `staff.deactivated`, `staff.last_admin`, `staff.deactivate_confirm` ("Nonaktifkan staf {name}? — peran {role}"), `staff.deactivate`, `staff.clinic_role`, dialog labels — semi-formal friendly tone, no hardcoded strings in FE

**Checkpoint**: US2 functional — staff CRUD + soft-delete + admin-terakhir guard + spatie role + audit naratif + FE pages with breadcrumb.

---

## Phase 5: User Story 3 - Admin Tenant Mengundang Anggota Baru (Priority: P2)

**Goal**: Admin undang anggota via email (token unik, `pending`, `expires_at` +7h). Accept → buat user + assign spatie role. Tolak email sudah user aktif di tenant sama (FR-022). Cancel/expire. Audit naratif.

**Independent Test**: Login admin → undang email baru → undangan `pending` token unik → coba undang email user aktif → ditolak → accept token valid → user aktif + role assigned → redirect login → login user baru berhasil → batalkan undangan lain → `cancelled` → akses token expired → `expired` ditolak.

### Implementation for User Story 3

#### BE — InvitationService revision

- [ ] T039 [US3] Revise `apps/api/app/Services/InvitationService.php::invite`: reject email that is already active user in same tenant — `User::where('tenant_id',$tenant->id)->where('email',$email)->whereNull('deleted_at')->exists()` → abort 422 (FR-022); persist `clinic_role` from request; audit `invitation.created` naratif "Mengundang {email} sebagai {role}."; ensure no duplicate pending invitation for same email+tenant (cancel old or reject)
- [ ] T040 [US3] Revise `apps/api/app/Services/InvitationService.php::accept`: in DB transaction — find pending non-expired invitation by token (else abort 422), create User (`status=active`, role enum from invitation, clinic_role enum), `setPermissionsTeamId($invitation->tenant_id)`, assign spatie global role (`member`/`tenant_admin` from invitation.role) + per-team clinic role (from invitation.clinic_role), set invitation `accepted`, audit `invitation.accepted` naratif "Menerima undangan — anggota {name} bergabung."
- [ ] T041 [US3] Add `InvitationService::cancel(Invitation $invitation)` method: only `pending` → `cancelled`; audit `invitation.cancelled` naratif "Membatalkan undangan ke {email}."
- [ ] T042 [US3] Add lazy expire in `apps/api/app/Http/Controllers/InvitationController.php::show` + `accept`: when `isExpired()` and status still `pending` → update to `expired` (DB), then abort 404/422; ensures `expired` status recorded

#### BE — Cancel endpoint + FormRequest

- [ ] T043 [US3] Add route `POST /invitations/{invitation}/cancel` (admin, auth, tenant-scoped) in `apps/api/routes/api.php` under `{tenant}/users` group → `InvitationController::cancel` delegating to `InvitationService::cancel` (T041); or fold into UserController
- [ ] T044 [P] [US3] Revise `apps/api/app/Http/Requests/InvitationRequest.php`: validate `email` (required,email), `role` (required,in:tenant_admin,member), `clinic_role` (nullable,in:admin,doctor,therapist,cashier); authorize tenant admin via spatie `hasRole('tenant_admin')`

#### BE — List invitations (optional, support FE cancel)

- [ ] T045 [US3] Add `GET /{tenant}/users/invitations` endpoint returning pending invitations for tenant (DataTable) in `apps/api/app/Http/Controllers/UserController.php` — so admin can see + cancel pending undangan; `InvitationResource` in `apps/api/app/Http/Resources/InvitationResource.php` (id, email, role, clinic_role, status, expires_at, created_at)

#### FE — Invitation accept + management

- [ ] T046 [US3] Create reusable `apps/web/src/components/forms/form-password.tsx`: password + confirm-password fields via `useForm`/`FormField` (react-hook-form), zod refine for match, strength hint — reusable for invitation accept (2+ future consumers: accept now, reset password later). Max 300 lines.
- [ ] T047 [US3] Revise `apps/web/src/routes/invitations/$token.tsx`: fetch `GET /invitations/{token}` (show tenant_slug + email) → form with `form-password` (T046) → submit `POST /invitations/{token}/accept` → on success toast + redirect to `/{slug}/login`; handle 404/expired with friendly message; breadcrumb root→Undangan
- [ ] T048 [US3] Revise `apps/web/src/routes/$tenant/users/components/invite-modal.tsx` (from T037): ensure `clinic_role` select present + submit; on success show toast "Undangan dikirim ke {email}"
- [ ] T049 [US3] Add pending invitations list section to `apps/web/src/routes/$tenant/users/index.tsx` (or colocated `components/invitation-list.tsx`): `DataTable` of pending invitations with "Batalkan" action → `POST /{tenant}/users/invitations/{id}/cancel` → toast + invalidate; breadcrumb already on page
- [ ] T050 [P] [US3] Add i18n keys to `apps/api/lang/id/tenant.php`: `invitation.title`, `invitation.accept`, `invitation.set_password`, `invitation.expired`, `invitation.cancelled`, `invitation.cancel_confirm`, `invitation.password_set`, `invitation.accepted` — semi-formal friendly

**Checkpoint**: US3 functional — invite/accept/cancel/expire + spatie role assign + audit naratif + FE accept page + admin cancel.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Konsistensi lintas story + cleanup + validasi.

- [ ] T051 [P] Remove `apps/api/app/Services/ClinicPermission.php` after confirming no consumer references it post-migration (T028/T029) — `ponytail` resolution
- [ ] T052 [P] Audit all audit-log call sites: ensure every membership action uses narrative description per research.md R6 (`user.login`, `staff.deactivated`, `staff.role_changed`, `invitation.created/accepted/cancelled`) — no robotic "update record id=N"
- [ ] T053 [P] Verify FE i18n: no hardcoded user-facing strings in revised routes (`login.tsx`, `staff/*`, `users/*`, `invitations/$token.tsx`) — all via `useTrans` `t()`
- [ ] T054 [P] Verify breadcrumbs on every revised inner page: `login.tsx`, `clinic/staff/index.tsx`, `users/index.tsx`, `invitations/$token.tsx` — root→active hierarchy, last item non-link
- [ ] T055 [P] Verify Tooltip + shortcut on all icon actions in `staff-actions-cell.tsx` and invitation cancel button per CLAUDE.md authoring discipline
- [ ] T056 Run `cd apps/api && vendor/bin/pint` (format check) — tell user to run
- [ ] T057 Run `cd apps/web && npx tsc --noEmit --incremental` (typecheck) — tell user to run
- [ ] T058 Run `cd apps/web && bun run generate-routes` (regen TanStack route tree after any route file added/renamed) — tell user to run
- [ ] T059 Run quickstart.md validation scenarios (US1/US2/US3) manually — tell user to run

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately. T001→T002→T003 sequential (composer, publish, config).
- **Foundational (Phase 2)**: Depends on Setup. Migrations T004–T008 + T009 can mostly parallel (different migration files) BUT run as one `php artisan migrate`. T010 depends on T004 (soft-delete col). T011–T012 (middleware) depend on T009 (spatie tables exist). T013 (seeder) depends on T009. T014–T015 depend on T013.
- **User Stories (Phase 3–5)**: All depend on Foundational complete.
  - US1 (Phase 3) — minimal, mostly verify existing + spatie team-id in login.
  - US2 (Phase 4) — depends on US1 (login needed to test admin actions). Largest phase.
  - US3 (Phase 5) — depends on US2 (admin auth + users page present); uses `InvitationService` revised with spatie.
- **Polish (Phase 6)**: After all stories.

### User Story Dependencies

- **US1 (P1)**: After Foundational. No story deps. MVP.
- **US2 (P1)**: After Foundational + US1 (login to act as admin). Independent testable.
- **US3 (P2)**: After Foundational + US2 (admin + users page). Independent testable.

### Within Each User Story

- Models/migrations before services/actions
- Actions before controllers (delegation)
- BE before FE (FE consumes contracts)
- i18n keys can parallel with FE
- Commit after each task or logical group

### Parallel Opportunities

- T006, T007 parallel with T005 (different FK migration files) — but apply together via migrate
- T030, T031 (FormRequests) parallel — different files
- T038, T050 (i18n) parallel — different lang files
- T044 parallel with T039/T040 (FormRequest vs Service)
- T046 (form-password) parallel with BE invitation tasks — FE reusable component independent
- T051–T055 (polish) all parallel — different concerns/files

---

## Parallel Example: User Story 2

```bash
# FormRequests in parallel (different files):
Task: T030 "Revise UpdateStaffRoleRequest.php"
Task: T031 "Revise StoreStaffRequest.php"

# BE actions + FE components in parallel (different stacks):
Task: T019 "Create DeactivateStaffAction.php"
Task: T032 "Revise staff/index.tsx"
Task: T038 "Add i18n keys staff.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (spatie install + config)
2. Complete Phase 2: Foundational (migrations + middleware + seeder) — CRITICAL blocks all
3. Complete Phase 3: US1 (login tenant + spatie team-id + audit)
4. **STOP and VALIDATE**: login tenant works, sidebar role-aware, audit recorded
5. Demo if ready

### Incremental Delivery

1. Setup + Foundational → Foundation ready (spatie teams + soft-delete schema)
2. Add US1 → validate → MVP (login)
3. Add US2 → validate → staff management + soft-delete + spatie roles
4. Add US3 → validate → invitations end-to-end
5. Polish → consistency, i18n, breadcrumb, cleanup `ClinicPermission`

### Parallel Team Strategy

With `ammar` (BE) + `sierly` (FE):
1. Complete Setup + Foundational together (ammar leads, spatie is BE infra)
2. Once Foundational done:
   - `ammar`: US2 BE (Actions, Controllers, Policies) then US3 BE (InvitationService)
   - `sierly`: US2 FE (staff pages, deactivate dialog) then US3 FE (form-password, accept page)
3. i18n keys + polish parallel at the end

---

## Notes

- [P] tasks = different files, no dependencies on incomplete tasks
- [Story] label maps task to user story for traceability
- Tests SKIPPED per user instruction — functional focus only
- Most tasks are **revisions** of existing files (BE/FE already implemented); blast radius largest in Foundational (spatie migration) + US2 (Policy rewrite)
- Commit after each task or logical group; commit langsung ke branch saat ini per CLAUDE.md
- Avoid: vague tasks, same file conflicts, cross-story dependencies that break independence
- Delegasi: BE Laravel → `ammar` (brief skill `/laravel-best-practices` + `/clean-code-principles`); FE → `sierly`; Push BE → `haikal` review `/code-review` low
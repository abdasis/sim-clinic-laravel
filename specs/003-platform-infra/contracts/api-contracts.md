# API Contracts — Platform Infrastructure: Tenants & Audit Log

**Spec**: [spec.md](./spec.md) | **Date**: 2026-08-14

Bentuk response konsisten project: `{ "data": …, "meta": … }` (koleksi → Resource collection di `data`, pagination di `meta`). Error: HTTP status + `{ message, errors }`. Validasi via FormRequest. Auth: Sanctum Bearer token (`Authorization: Bearer …`).

Sebagian besar endpoint **sudah ada** — kontrak di bawah mendokumentasikan kontrak existing + delta Langkah 2 (audit log tidak punya endpoint publik; hanya direkam internally).

---

## Endpoint existing (Langkah 1 — tidak berubah)

### POST /register — registrasi tenant (publik)

Buat tenant baru + user admin pertama atomik.

- **Request**:
  ```json
  { "company_name": "Klinik Sehat", "phone": "08123456789", "email": "admin@klinik-sehat.test", "password": "Password123" }
  ```
- **Response 201**:
  ```json
  { "data": { "tenant": { "id": 1, "name": "Klinik Sehat", "slug": "klinik-sehat", "status": "active" }, "user": { "id": 2, "name": null, "email": "admin@klinik-sehat.test", "role": "tenant_admin" } }, "meta": { "redirect_to": "/klinik-sehat/login" } }
  ```
- **Validasi** (`RegisterTenantRequest`): `company_name` required, `phone` required, `email` required|email|unique:users, `password` required|min:8 (letters+digits regex).
- **Aturan**: slug diturunkan `Str::slug(company_name)`; reject bila kosong / duplikat / reserved `central` (FR-003/004/005). Audit `tenant.registered` tercatat (causer nullable, subject = tenant baru, `properties.tenant_id` = tenant->id).

### POST /central/login — login platform admin (publik)

- **Request**: `{ "email": "admin@platform.test", "password": "password123" }`
- **Response 200**:
  ```json
  { "data": { "user": { "id": 1, "name": "Platform Admin", "email": "admin@platform.test", "role": "platform_admin", "clinic_role": null, "tenant_id": 1 }, "token": "1|abc...", "tenant": { "slug": "central" } }, "meta": { "redirect_to": "/central" } }
  ```
- **Aturan**: reject user `Inactive` (403). Audit `user.login` tercatat (causer = user, `properties.tenant_id` = central). Token Sanctum ability `spa`.

### GET /central/tenants — list tenant (auth, platform_admin)

DataTable server-side. Query: `page, per_page, sort, direction, search, filter[status]`.

- **Response 200**:
  ```json
  { "data": [ { "id": 1, "name": "Central", "slug": "central", "phone": null, "status": "active", "created_at": "2026-07-06T00:00:00.000000Z" } ], "meta": { "current_page": 1, "per_page": 10, "total": 3, "last_page": 1 } }
  ```
- **Otorisasi**: `assertPlatformAdmin()` (403 bila bukan).

### PATCH /central/tenants/{tenant}/status — toggle status tenant (auth, platform_admin)

- **Request**: `{ "status": "inactive" }`
- **Validasi** (`UpdateTenantStatusRequest`): `status` required|in:active,inactive.
- **Response 200**: `{ "data": { "id": 2, "name": "Klinik Sehat", "slug": "klinik-sehat", "status": "inactive" } }`
- **Aturan**: audit `tenant.status_changed` tercatat dengan `properties.old_status` + `properties.new_status` + `properties.tenant_id`. Tenant `inactive` → request berikutnya ke `/{tenant}/*` ditolak 423 (`EnsureTenantActive`).

### GET /translations — i18n (publik, existing)

Serialisasi `lang/id/*.php` untuk FE `useTrans()`. Tidak berubah.

---

## Audit log (Langkah 2 — internal, tidak ada endpoint publik)

Audit log direkam internally via `LogAuditAction` dari controller/service. Tidak ada endpoint CRUD publik di MVP. `ponytail: endpoint read audit log (GET /central/audit-logs, per-tenant) add saat modul audit UI dibutuhkan`.

Record yang tercipta (via `activity()` helper, table `audit_logs`):

| Action (`description`) | `log_name` | Causer | Subject | `properties` |
|---|---|---|---|---|
| `tenant.registered` | `tenant` | nullable (publik) | Tenant baru | `tenant_id`, slug |
| `user.login` | `auth` | User | — | `tenant_id`, ip_address? |
| `tenant.status_changed` | `tenant` | User (platform_admin) | Tenant | `tenant_id`, `old_status`, `new_status` |
| `user.role_changed` | `user` | User | User | `tenant_id`, old/new role |
| `staff.created` / `staff.role_changed` / `staff.deactivated` | `user` | User | User | `tenant_id` |
| `user.invited` / `user.joined` / `user.removed` | `user` | User | User/Invitation | `tenant_id`, email |

Query per tenant (internal): `Activity::where('properties->tenant_id', $tenantId)->get()`.

---

## FE route contracts (Langkah 1 FE — net-new)

| Route | Status | Catatan |
|---|---|---|
| `/central/login` | existing | login platform admin |
| `/central/tenants` | existing | list + status toggle |
| `/central` (dashboard) | **NEW** | `src/routes/central/index.tsx`; ringkasan tenant + link ke /central/tenants; guard `hasPlatformRole()` |
| `/register` | existing | registrasi tenant publik |

Breadcrumb dashboard central: `[{ label: t("general.central") }, { label: t("central.dashboard") }]` (item terakhir = page, bukan link). Nav item Dashboard ditambah ke `central/route.tsx` sidebar (`AppSidebar` navMain).
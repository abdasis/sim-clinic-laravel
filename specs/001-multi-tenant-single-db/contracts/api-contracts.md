# API Contracts: Multi-Tenant Single Database

**Spec**: [spec.md](../spec.md) | **Data Model**: [data-model.md](../data-model.md)

Format: REST JSON. Response wrapper `{data, meta}` (sesuai pola `DemoDataTableController`). Semua endpoint tenant-scoped di-prefix `/{tenant-slug}/...` kecuali registrasi publik, landing, dan central auth. Error format Laravel standar `{message, errors}`.

## Konvensi

- Prefix tenant: `/{tenant}` — middleware `ResolveTenant` resolve slug → tenant aktif.
- Auth: `auth:sanctum` (Sanctum SPA). Token/user terikat tenant.
- Bahasa: response error memakai `__()` dari `lang/id/*.php` (CLAUDE.md i18n wajib).
- Datatable: server-side, params `page`, `per_page`, `search`, `sort`, `direction`, `filters`.
- 404 ketika slug tenant tidak dikenal (FR-007) — pesan jelas, tidak ekspos internal.

---

## 1. Landing & Central Auth (no tenant prefix)

### GET `/`
Landing page publik (FR-026). HTML (web route, bukan JSON).

### POST `/central/login`
Login platform, redirect ke tenant user terdaftar.

Request:
```json
{ "email": "string", "password": "string" }
```

Response 200: `{data: {user, tenant: {slug}}, meta: {redirect_to: "/{tenant-slug}"}}`
Response 422: `{message, errors: {email|password}}`

---

## 2. Tenant Self-Registration (P1, publik)

### POST `/register`
Registrasi tenant baru + admin pertama (FR-013, FR-014, FR-017).

Request:
```json
{
  "company_name": "string",
  "phone": "string",
  "email": "string (email)",
  "password": "string (min 8, huruf+angka)"
}
```

Validation (FR-016):
- `company_name`: required, max 255
- `phone`: required, format lokal/internasional
- `email`: required, email, unique `users.email` (FR-015)
- `password`: required, min 8, regex `/^(?=.*[A-Za-z])(?=.*\d).{8,}$/`
- `slug`: auto-derived dari `company_name`; reject non-URL-safe (FR-005); unique `tenants.slug` (FR-004)

Response 201:
```json
{
  "data": {
    "tenant": {"id", "name", "slug", "status": "active"},
    "user": {"id", "email", "role": "tenant_admin"}
  },
  "meta": {"redirect_to": "/{slug}/login"}
}
```

Response 422: `{message, errors: {field: [msg]}}`

Atomik: tenant + user dibuat bersama atau keduanya gagal (edge case).

Audit: `tenant.registered` (FR-028).

---

## 3. Tenant-scoped Auth (prefix `/{tenant}`)

### POST `/{tenant}/login`
Login user ke tenant aktif.

Request: `{email, password}`
Response 200: `{data: {user, token}, meta: {redirect_to: "/{tenant}"}}`
Response 422: kredensial salah / tenant tidak aktif (FR-009).

Audit: `user.login`.

### POST `/{tenant}/logout`
Revoked token. 204.

---

## 4. User Management (admin tenant, prefix `/{tenant}`)

### GET `/{tenant}/users`
Daftar user tenant. Datatable server-side (pakai `apps/web/src/components/datatable/`).

Query: `page, per_page, search, sort, direction, filters[status], filters[role]`

Response 200:
```json
{
  "data": [{"id","name","email","role","status","created_at"}],
  "meta": {"current_page","per_page","total","last_page"}
}
```

### POST `/{tenant}/users/invite`
Undang anggota (FR-019, FR-020).

Request: `{email, role: "member"|"tenant_admin"}`
Validation: email tidak user aktif di tenant sama (FR-022); satu email satu tenant v1.
Response 201: `{data: {invitation: {id, email, role, expires_at}}}`
Response 422: `{message, errors}`

Audit: `user.invited`.

### POST `/{tenant}/users/{id}/remove`
Hapus/nonaktifkan keanggotaan (FR-023). Tolak jika admin terakhir (FR-025).

Response 200: `{data: {user_id, status}}`
Response 422: `{message: "admin terakhir"}`

Audit: `user.removed`.

### PATCH `/{tenant}/users/{id}/role`
Ubah peran (FR-024).

Request: `{role: "member"|"tenant_admin"}`
Audit: `user.role_changed`.

---

## 5. Invitation Accept (publik, no prefix)

### GET `/invitations/{token}?`
Tampilkan halaman set password (FR-020). Validasi token + `expires_at`.

### POST `/invitations/{token}/accept`
Request: `{password: "min 8, huruf+angka"}`
Response 200: `{data: {user, tenant: {slug}}, meta: {redirect_to: "/{slug}/login"}}`
Response 422: token invalid/expired.

---

## 6. Platform Admin (prefix `/central`, admin platform)

### GET `/central/tenants`
Daftar semua tenant (FR-006). Datatable server-side.

Query: `page, per_page, search, sort, direction, filters[status]`
Response 200: `{data: [{id, name, slug, phone, status, created_at}], meta}`

### PATCH `/central/tenants/{id}/status`
Ubah status tenant (FR-006, FR-011).

Request: `{status: "active"|"inactive"}`
Response 200: `{data: {tenant}}`
Audit: `tenant.status_changed`.

---

## 7. Global Data (FR-008)

### GET `/{tenant}/global/{resource}`
Master data bersama (mis. spesialis). Tidak terisolasi per tenant.
Response 200: `{data: [...]}`

---

## Error Contract

```json
{
  "message": "string (i18n)",
  "errors": { "field": ["string (i18n)"] }
}
```

Status codes:
- 200/201: sukses
- 401: unauthenticated
- 403: bukan admin (FR-024)
- 404: tenant slug tidak dikenal (FR-007) / resource lintas tenant (FR-012)
- 422: validation error
- 423: tenant inactive (FR-009) — opsi
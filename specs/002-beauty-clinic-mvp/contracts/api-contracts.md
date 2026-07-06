# API Contracts: MVP Sistem Klinik Kecantikan

**Spec**: [spec.md](../spec.md) | **Data Model**: [data-model.md](../data-model.md)

Format: REST JSON. Response wrapper `{data, meta}` (sesuai pola `DemoDataTableController`). Semua endpoint di-prefix `/{tenant-slug}/clinic/...` (warisan middleware `ResolveTenant` spec 001) + policy per modul (R2). Error format Laravel standar `{message, errors}`. Bahasa response memakai `__()` dari `lang/id/*.php` (CLAUDE.md).

## Konvensi

- Prefix: `/{tenant}/clinic/{module}` — `ResolveTenant` resolve slug → tenant aktif (spec 001).
- Auth: `auth:sanctum` + middleware `clinic.role` opsional (cek `clinic_role` tidak null).
- Permission: Laravel `Policy` per modul menolak (403) aksi di luar peran (R2, FR-002, FR-044, FR-075). Pesan jelas via `__(...)`.
- Datatable: server-side, params `page`, `per_page`, `search`, `sort`, `direction`, `filters`. Response `{data: [...], meta: {current_page, last_page, total, ...}}`.
- Timestamp: ISO 8601. Rentang tanggal laporan = lokal tenant (R11).

## Pola CRUD umum (berlaku: Service, Patient, Product)

```
GET    /{tenant}/clinic/{module}            index   (datatable, policy viewAny)
POST   /{tenant}/clinic/{module}            store   (policy create)
GET    /{tenant}/clinic/{module}/{id}       show    (policy view)
PUT    /{tenant}/clinic/{module}/{id}       update  (policy update)
DELETE /{tenant}/clinic/{module}/{id}       destroy (policy delete; soft/arsip untuk Service/Product — FR-013, FR-066)
```

Resource JSON (contoh Service):
```json
{"data": {"id": 1, "name": "Facial", "description": "...", "price": "150000.00", "status": "active", "created_at": "..."}, "meta": {}}
```

---

## 1. Staff & Peran — US1 (FR-001..005)

### GET `/{tenant}/clinic/staff`
List staf tenant + role. Policy: admin only.

### POST `/{tenant}/clinic/staff`
Buat/undang staf + tetapkan `clinic_role`.
```json
{"name": "...", "email": "...", "clinic_role": "cashier", "password": "..."}
```
Response 201: `{data: {user, clinic_role}}`.

### PATCH `/{tenant}/clinic/staff/{id}/role`
Ubah peran.
```json
{"clinic_role": "therapist"}
```
Error 422 jika menonaktifkan admin terakhir (FR-005, warisan spec 001).

### POST `/{tenant}/clinic/staff/{id}/deactivate`
Nonaktifkan staf. 422 jika admin terakhir (FR-005).

---

## 2. Service — US2

Pola CRUD umum. Field: `name`, `description`, `price`, `status`.

### POST `.../clinic/services` (FR-011)
```json
{"name": "Chemical Peeling", "description": "...", "price": 200000, "status": "active"}
```
Validasi 422: `price` <0 atau `name` kosong.

### DELETE `.../clinic/services/{id}` → arsip (FR-013)
Set `status=archived`, bukan hapus permanen. Response 200: `{data: {status: "archived"}}`.

---

## 3. Patient — US3 (FR-020..024)

### POST `.../clinic/patients`
```json
{"name": "...", "birth_date": "1990-01-01", "gender": "female", "phone": "0812...", "whatsapp": "...", "address": "..."}
```
Response 201 dengan flag duplikat (FR-023):
```json
{"data": {"id": 12, ...}, "meta": {"duplicate_warning": true, "duplicate_patient_id": 7}}
```
`duplicate_warning=false` jika nomor telepon unik di tenant.

### GET `.../clinic/patients?search={name|phone}` (FR-021)
Pencarian nama/telepon via datatable.

### GET `.../clinic/patients/{id}/history` (FR-022)
Riwayat kunjungan agregasi (booking + treatment), terurut kronologis.
```json
{"data": [{"date": "...", "service_name": "...", "status": "done", "assignee_name": "...", "type": "booking|treatment"}]}
```

---

## 4. Booking & Jadwal — US4 (FR-030..035)

### POST `.../clinic/bookings`
```json
{"patient_id": 12, "service_id": 3, "assignee_id": 5, "start_at": "2026-07-10T10:00:00+07:00", "end_at": "2026-07-10T11:00:00+07:00", "notes": ""}
```
Response 201 + overlap warning (FR-035, R8):
```json
{"data": {"id": 90, "status": "pending", ...}, "meta": {"overlap_warnings": [{"booking_id": 88, "patient_name": "...", "start_at": "...", "end_at": "..."}]}}
```
`overlap_warnings=[]` jika tidak bentrok. Status awal selalu `pending` (FR-031).

### PATCH `.../clinic/bookings/{id}/status` (FR-031)
```json
{"status": "confirmed"}
```
Validasi transisi: 422 jika `done`→`cancelled` (edge case) atau transisi tidak valid.

### GET `.../clinic/bookings/schedule?from=YYYY-MM-DD&to=YYYY-MM-DD&view=day|week` (FR-032)
List booking aktif terurut per waktu + assignee. Untuk `schedule-grid.tsx`.
```json
{"data": [{"id": 90, "patient_name": "...", "service_name": "...", "assignee_id": 5, "assignee_name": "...", "start_at": "...", "end_at": "...", "status": "confirmed"}]}
```

---

## 5. Rekam Medis — US7 (FR-040..044)

### POST `.../clinic/medical-records`
```json
{"booking_id": 90, "subjective": "...", "objective": "...", "assessment": "...", "plan": "..."}
```
Policy: dokter/terapis/admin (FR-044). 422 jika booking bukan milik tenant atau sudah ada record (unique booking_id).

### POST `.../clinic/medical-records/{id}/treatments` (FR-041)
```json
{"service_id": 3, "notes": "..."}
```

### POST `.../clinic/medical-records/{id}/photos` (FR-042, R3)
`multipart/form-data`: `file` (image jpg/png ≤2048KB), `type` (before|after).
Response 201: `{data: {id, type, path}}`. 422 jika format/ukuran invalid.

### GET `.../clinic/patients/{id}/treatments` (FR-043)
Riwayat treatment pasien (SOAP + foto).

---

## 6. Product & Inventory — US6 (FR-060..066)

### Product: pola CRUD umum. Field: `name`, `unit`, `stock_balance`, `min_threshold`, `price`, `status`.
Response sertakan `is_low_stock` (FR-065): `{data: {..., "stock_balance": 3, "min_threshold": 5, "is_low_stock": true}}`.

### POST `.../clinic/products/{id}/stock-movements` (FR-061, FR-062)
```json
{"type": "in", "quantity": 10, "note": "restock supplier A"}
```
`type`: `in` (restock) atau `out_manual` (rusak/internal). Lewat `StockService` (R7). Response: `{data: {movement, balance_after}}`.

### GET `.../clinic/products/{id}/stock-movements` (FR-064)
Riwayat pergerakan stok (in, out_manual, sold_pos, rollback) + `balance_after`.

---

## 7. POS / Transaction — US5 (FR-050..059)

### POST `.../clinic/transactions` (FR-050..053)
```json
{"patient_id": 12, "booking_id": null, "items": [
  {"service_id": 3, "qty": 1},
  {"product_id": 7, "qty": 2}
]}
```
`TransactionService` (R7): snapshot `name`+`unit_price` per item (FR-056), hitung subtotal (FR-051), validasi stok produk (FR-053), adjust stok via `StockService` dalam DB transaction (FR-052).
Response 201: `{data: {id, invoice_number, subtotal, payment_status: "unpaid", items: [...]}}`.
422 jika stok produk tidak cukup (FR-053).

### POST `.../clinic/transactions/{id}/payments` (FR-054, FR-055)
```json
{"method": "cash", "amount": 350000, "paid_at": "2026-07-10T11:30:00+07:00"}
```
`PayTransactionAction`: jika `sum(payments) ≥ subtotal` → `payment_status=paid` (FR-055). Peringatan (meta) jika kelebihan bayar (edge case).

### POST `.../clinic/transactions/{id}/cancel` (FR-058)
`CancelTransactionAction`: rollback stok produk via `StockService` (type `rollback`), set `cancelled_at`. Hanya jika belum `paid`? Spec: pembatalan transaksi mengembalikan stok — izinkan pembatalan, rollback selalu.

### GET `.../clinic/transactions/{id}/invoice` (FR-057, R4)
Invoice HTML print-view (render dari transaction + items + payments + tenant + patient). Bukan JSON — route web/pintasan print.

### GET `.../clinic/transactions` (FR-058 list)
Datatable transaksi: status, total, pasien.

---

## 8. Report — US8 (FR-070..075)

Policy: admin only (FR-075).

### GET `.../clinic/reports/revenue?from=...&to=...` (FR-070, FR-073)
```json
{"data": {"total_revenue": "1250000.00", "paid_transactions_count": 15, "from": "...", "to": "..."}}
```
Hanya transaksi `payment_status=paid` (FR-059, FR-073). Rentang tanpa data → `{data: {total_revenue: "0.00", paid_transactions_count: 0}}` + meta `{empty: true}` (FR-074).

### GET `.../clinic/reports/services?from=...&to=...` (FR-071)
```json
{"data": [{"service_id": 3, "service_name": "Facial", "qty_sold": 8, "revenue": "1200000.00"}]}
```
Agregasi dari `transaction_items` (service_id not null) transaksi lunas.

### GET `.../clinic/reports/products?from=...&to=...` (FR-072)
```json
{"data": [{"product_id": 7, "product_name": "Serum A", "qty_sold": 12, "revenue": "600000.00"}]}
```

---

## Error responses

- 401: tidak terautentikasi.
- 403: aksi ditolak peran (Policy) — `{message: __("clinic.forbidden")}` (FR-004, FR-044, FR-075).
- 404: resource tidak ada di tenant aktif (isolasi spec 001) — tidak ekspos internal.
- 422: validasi (FormRequest) — `{message, errors: {field: [__(...)]}}`.
- 422: transisi status tidak valid (booking), admin terakhir (FR-005), stok kurang (FR-053).

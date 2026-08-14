# API Contracts: Integritas Mutasi Stok & Riwayat Stok Produk

**Date**: 2026-08-14 | **Spec**: [spec.md](spec.md) | **Data Model**: [data-model.md](data-model.md)

## Konteks

Endpoint backend inti `stock_movements` sudah ada dari spec 007/008 (model, enum, policy, controller, request, FK restrict migration). Spec 012 menambah **1 endpoint baru** (reverse lookup per transaksi, FR-012), merevisi **1 endpoint eksisting** (response `indexByProduct` tambah field transaksi terkait), dan menambah **response error** dari guard saldo negatif (FR-015). Migration revisi morph tidak mengubah response shape (kolom `related_type`/`related_id` tetap, hanya cara penyimpanan morph berubah).

## Endpoint eksisting (revisi minor)

### 1. Catat pergerakan stok manual

Sudah ada. FE `stock-movement-form.tsx` POST ke sini.

```
POST /api/{tenant}/clinic/products/{product}/stock-movements
```

**Auth**: Bearer token, `StockMovementPolicy@create` (`inventory.manage`).

**Request body**:

```json
{
  "type": "in",
  "quantity": 10,
  "note": "Restock awal"
}
```

| Field | Validasi |
|-------|----------|
| type | required, in `['in', 'out_manual']` |
| quantity | required, integer, gt:0 |
| note | nullable, string, max:255 |

**Response 201** `{ data: StockMovement, meta }`:

```json
{
  "data": {
    "id": 42,
    "product_id": 7,
    "type": "in",
    "type_label": "Stok Masuk",
    "quantity": 10,
    "balance_after": 10,
    "note": "Restock awal",
    "created_at": "2026-08-14T10:00:00+00:00"
  },
  "meta": { "message": "Pergerakan stok berhasil dicatat." }
}
```

**Response 422** (validasi ATAU guard saldo negatif FR-015): `{ message, errors }`.
- `out_manual` dengan quantity > saldo → `message`: "Stok produk tidak mencukupi." (key `inventory.insufficient_stock`). FE `applyServerErrors` map ke form + toast.

### 2. Riwayat stok per produk

Sudah ada. FE `stock-movement-history.tsx` GET ke sini. **Revisi response: tambah field transaksi terkait**.

```
GET /api/{tenant}/clinic/products/{product}/stock-movements
```

**Auth**: Bearer token, `StockMovementPolicy@viewAny` (`inventory.view`).

**Query params** (server-side pagination, DataTable): `page`, `per_page`.

**Response 200** `{ data: StockMovement[], meta }`:

```json
{
  "data": [
    {
      "id": 42,
      "type": "sold_pos",
      "type_label": "Penjualan POS",
      "quantity": 2,
      "balance_after": 8,
      "note": null,
      "related_type": "transaction",
      "related_id": 12,
      "created_at": "2026-08-14T11:00:00+00:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 5,
    "last_page": 1
  }
}
```

- `related_type`: alias morph (`'transaction'`) via `enforceMorphMap` — bukan FQCN.
- `related_id`: id transaksi terkait (null bila mutasi `in`/`out_manual` manual).
- FE tampilkan link ke detail transaksi bila `related_type === 'transaction'` + `related_id` terisi.

## Endpoint baru (FR-012)

### 3. Reverse lookup mutasi per transaksi

NEW. Melayani audit/rekonsiliasi: semua mutasi (sold_pos + rollback) yang berhubungan dengan satu transaksi.

```
GET /api/{tenant}/clinic/transactions/{transaction}/stock-movements
```

**Auth**: Bearer token, `StockMovementPolicy@viewAny` (`inventory.view`).

**Query params**: `page`, `per_page`.

**Response 200** `{ data: StockMovement[], meta }` — shape sama `indexByProduct`:

```json
{
  "data": [
    {
      "id": 42,
      "type": "sold_pos",
      "type_label": "Penjualan POS",
      "quantity": 2,
      "balance_after": 8,
      "note": null,
      "related_type": "transaction",
      "related_id": 12,
      "created_at": "2026-08-14T11:00:00+00:00"
    },
    {
      "id": 55,
      "type": "rollback",
      "type_label": "Pengembalian",
      "quantity": 2,
      "balance_after": 10,
      "note": null,
      "related_type": "transaction",
      "related_id": 12,
      "created_at": "2026-08-14T12:00:00+00:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 2,
    "last_page": 1
  }
}
```

**Implementasi**: `StockMovement::where('related_type', $transaction->getMorphClass())->where('related_id', $transaction->id)->latest('created_at')->paginate()`. Pakai composite index `(related_type, related_id)` (FR-006).

**Response 404**: transaksi tidak ada / soft-deleted.

## Response error dari guard integritas (012)

### Guard saldo negatif (FR-015)

`StockService::adjust()` bila outbound (`out_manual`/`sold_pos`) dan `balance_after < 0`:

**Response 422**:
```json
{
  "message": "Stok produk tidak mencukupi.",
  "errors": { "quantity": ["Stok produk tidak mencukupi."] }
}
```

- Jalur controller `out_manual` → 422 langsung ke FE.
- Jalur `TransactionService` `sold_pos` → `TransactionService::productLine` sudah cek pre-emptif (422 cepat); service guard = backstop race condition (stok turun antara cek dan `adjust`).

### FK restrict product_id

Bila hapus permanen produk dengan riwayat mutasi (migration 060000 eksisting, PostgreSQL):

**PostgreSQL**: `SQLSTATE[23503]: Foreign Key Violation` → app tangkap → 422 "Tidak bisa menghapus produk: masih ada riwayat stok." Arsip (`status=archived`) tetap diizinkan.

**SQLite**: FK alter tidak didukung → restrict tidak teruji sqlite. WAJIB `phpunit.pgsql.xml` sebelum rilis.

## Kontrak FE (UI contract)

### Halaman inventaris (`inventory/index.tsx` — edit minor)

- **Breadcrumb**: "Beranda Klinik > {tenant} > Inventaris" — item terakhir non-link. Sudah benar.
- **Layout**: form catat pergerakan (atas) + riwayat stok produk terpilih (bawah). Sudah ada, refine spacing.

### Form catat pergerakan (`stock-movement-form.tsx` — edit minor)

- Reuse `FormSelect` (product + type), `FormInput` (quantity, type=number), `FormTextarea` (note), `FormSubmit`, `useForm` + `applyServerErrors`.
- `type` options: `in` + `out_manual` (manual only; `sold_pos`/`rollback` internal).
- Feedback saldo negatif: server 422 → `applyServerErrors` map ke `quantity` + toast error.
- Submit sukses → invalidate `['stock-movements', tenant, productId]` + `['products']`.

### Riwayat stok (`stock-movement-history.tsx` — rewrite pakai DataTable)

- **Pakai** `DataTable` (`components/datatable/datatable.tsx`) + `useReactTable` + `getCoreRowModel` + `getPaginationRowModel` + kolom def.
- **Kolom**: Waktu (`created_at`), Jenes (`type_label`), Jumlah (`quantity`, right-align `tabular-nums`), Saldo Setelah (`balance_after`, right-align), Keterangan (`note ?? '-'`), Transaksi (link ke `pos/transactions/$id` bila `related_type === 'transaction'`).
- **State**: loading skeleton (DataTable handle), empty "Belum ada mutasi stok." (`general.empty`), paginasi server-side (`DataTablePagination` + meta).
- **Tidak buat** DataTable baru — reuse `components/datatable/datatable.tsx` per instruksi user.

### Reverse lookup FE (opsional, ditanam di detail transaksi)

- Bila spec 011 halaman `pos/transactions/$id.tsx` sudah ada, tambah section "Pengaruh Stok" — `useQuery` ke `GET transactions/{id}/stock-movements`, Table sederhana (mutasi jual + rollback).
- YAGNI: tidak buat route terpisah bila bisa jadi section. `ponytail: halaman terpisah add saat reverse lookup butuh filter tersendiri`.

## i18n keys (lang/id/inventory.php + clinic.php — tambahan bila perlu)

| Key | Value |
|-----|-------|
| `inventory.insufficient_stock` | "Stok produk tidak mencukupi." |
| `inventory.empty_movements` | "Belum ada mutasi stok." |
| `inventory.related_transaction` | "Transaksi Terkait" |

`clinic.stock_movement_type.*` (in/out_manual/sold_pos/rollback) sudah ada. `inventory.*` sebagian besar sudah ada (title, product, type, quantity, note, balance_after, history, recorded). Identifier English, value Indonesia semi-formal friendly.
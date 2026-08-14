# `invoices`

Penerbitan invoice transaksi (Kasir/POS, US5).

## Kolom

| Field | Tipe | Constraint | Catatan |
|-------|------|-----------|---------|
| id | bigint unsigned | PK | |
| tenant_id | bigint unsigned | FK→tenants, not null | BelongsToTenant |
| transaction_id | bigint unsigned | FK→transactions, not null, unique | 1 invoice per transaksi |
| issued_at | datetime | not null | |
| created_at | timestamp | | |
| updated_at | timestamp | | |

## Relasi

- belongsTo `Transaction`

## Delete Rule

- FK `transaction_id` → **`cascadeOnDelete`** aman: parent (`transactions`) di-soft-delete, cascade DB hanya saat hard-delete parent (kasus terlarang/jarang).

## Catatan

- **R4**: konten invoice di-render dari `transaction` + `transaction_items` + `payments` + `tenant` + `patient` di view HTML print, BUKAN kolom duplikat di tabel.
- `Invoice` model = record penerbitan + link ke transaction. Tidak menyimpan data transaksi secara redundan.
- `transaction_id` unique — 1 invoice per transaksi.
- **YAGNI review**: tabel ini nyaris hanya `transaction_id` + `issued_at`. Bila `issued_at` cukup ditampung di `transactions`, tabel `invoices` tidak memberi nilai tambah dan bisa dihilangkan. Pertahankan hanya bila ada kebutuhan: nomor invoice terpisah dari `invoice_number` transaksi, multi-cetakan per transaksi, atau status cetak/terkirim. `ponytail: add bila butuh riwayat cetak/multi-invoice`.
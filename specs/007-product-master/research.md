# Research — Master Produk Klinik (007-product-master)

**Date**: 2026-08-14 | **Spec**: [spec.md](spec.md)

Fase riset menyelesaikan semua titik ambigu teknis. Sumber kebenaran data model: `docs/erd/products.md`, `docs/erd/stock_movements.md`, `docs/erd/transaction_items.md`, `docs/normalization/README.md` (R7 `stock_balance` denormalized intensional).

## R1 — Status eksisting modul produk

**Decision**: Modul produk sebagian besar sudah terimplementasi. Yang sudah ada: `Product` model (dengan `BelongsToTenant`, `is_low_stock` appended, cast `ServiceStatus`), `ProductController` (index/store/show/update/destroy), `ProductRequest`, `ProductResource`, `ProductPolicy`, `StockService::adjust()`, `StockMovement` model, `StockMovementController` (store + indexByProduct), `StockMovementRequest`, route `apiResource('products')` + nested `products/{product}/stock-movements`, lang `product.php` + `inventory.php`. FE: `products/index.tsx` + `product-form-modal.tsx`, `inventory/index.tsx` + `stock-movement-form.tsx` + `stock-movement-history.tsx`.

**Rationale**: Spec 007 adalah **revisi/penyempurnaan**, bukan greenfield. Gap di bawah yang perlu ditutup, sisanya reuse.

**Alternatives considered**: Bangun ulang dari nol — ditolak (melanggar YAGNI, duplikasi).

## R2 — FK `product_id` restrictOnDelete (FR-068, revisi input)

**Decision**: Migration baru mengubah FK `stock_movements.product_id` dari `cascadeOnDelete` → `restrictOnDelete`, dan `transaction_items.product_id` dari `nullOnDelete` → `restrictOnDelete`. Sesuai ERD `products.md` delete rule + revisi eksplisit input user.

**Rationale**: Produk diarsip, bukan dihapus. Hard-delete produk yang masih direferensi mutasi/transaksi harus diblokir DB — `restrict` adalah pertahanan integritas terakhir. `nullOnDelete` pada `transaction_items.product_id` berisiko memutus relasi historis; `cascade` pada `stock_movements.product_id` berisiko menghapus riwayat mutasi (R7 audit).

**Alternatives considered**:
- Pertahankan `nullOnDelete`/`cascadeOnDelete` — ditolak, melanggar FR-068 + R6 (riwayat historis utuh).
- App-level guard saja — ditolak, tidak mencegah path internal (artisan/tinker/bug). DB constraint = pertahanan final (konstitusi III spirit).

**Implementasi**: `dropForeign` + `foreignId('product_id')...->constrained('products')->restrictOnDelete()`. Migration terpisah, idempotent via `Schema` builder.

## R3 — `stock_balance` bukan input (FR-060, FR-063)

**Decision**: `stock_balance` dikeluarkan dari input form produk (FE + BE). `ProductRequest` menghapus `stock_balance` dari `rules()` — field tidak divalidasi/tidak diterima dari request. Saldo diawali 0 saat create (DB default + model). Saldo hanya berubah via `StockService::adjust()` (R7) yang sudah ada.

**Rationale**: FR-060 "saldo diawali 0, tidak diisi dari form"; FR-063 "hanya via `StockService::adjust()`". Saat ini `ProductRequest` menerima `stock_balance` required + FE menampilkan input saldo — ini **pelanggaran eksisting** yang harus diperbaiki. `ProductController::store` saat ini `Product::create($request->validated())` — setelah `stock_balance` dihapus dari request, create otomatis pakai DB default 0.

**Alternatives considered**:
- Keep `stock_balance` input tapi disable di FE — ditolak, validasi BE tetap menerima → path bypass ada.
- Mass-assignment guard via `$fillable` removal — tidak, `stock_balance` tetap di `$fillable` karena `StockService::adjust()` meng-update via `$locked->update(['stock_balance' => $newBalance])`. Solusi: **keluar dari FormRequest**, bukan dari `$fillable`.

**Catatan**: `$fillable` tetap menyertakan `stock_balance` (dibutuhkan `StockService`). Pertahanan "tidak ada path update langsung" (SC-007) dijaga oleh: (a) `stock_balance` tidak ada di `ProductRequest`, (b) tidak ada endpoint/method lain yang menerima `stock_balance` dari input, (c) diverifikasi via test — `Product::update()` dengan `stock_balance` di payload request tidak mengubah saldo.

## R4 — Layering Controller → ProductService → Action + audit log (FR-073, konstitusi VI, layering CLAUDE.md)

**Decision**: Modul produk saat ini **tidak punya layer Service/Action** — `ProductController` langsung menyentuh Eloquent (`Product::create`/`$product->update()`/arsip via update status) tanpa audit log. Ini melanggar dua aturan sekaligus: (a) CLAUDE.md "Controller WAJIB memanggil Service, dilarang langsung ke Action/DB", diperkuat commit `f72afc1` (tegas layering controller wajib via service); (b) konstitusi VI (audit log naratif wajib tiap aksi ubah-data). Spec 007 memperbaikinya dengan pola yang benar:

- **`ProductService`** (NEW, `app/Services/ProductService.php`) — mengorkestrasi use case: `create()`, `update()`, `archive()`. Memanggil Action + mengelola boundary. **DILARANG menyentuh DB langsung** (konstitusi/CLAUDE.md) — semua operasi DB via Action.
- **`CreateProductAction`** (NEW, `app/Actions/Product/CreateProductAction.php`) — `execute(ProductRequest|array)` → `Product::create()` (saldo default 0 via DB) + `LogAuditAction` event `product.created` narasi "Membuat produk {name}".
- **`UpdateProductAction`** (NEW, `app/Actions/Product/UpdateProductAction.php`) — capture old attributes → `$product->update()` + `LogAuditAction` event `product.updated` narasi "Memperbarui produk {name}" dengan old/new diff.
- **`ArchiveProductAction`** (NEW, `app/Actions/Product/ArchiveProductAction.php`) — set `status=Archived` + `LogAuditAction` event `product.archived` narasi "Mengarsipkan produk {name}". Mirror `ArchiveServiceAction` (flat `app/Actions/`) + tambah audit; tapi ditaruh di folder `app/Actions/Product/` sesuai aturan "folder per entity".
- **`ProductController`** (EDIT) — `store/update/destroy` WAJIB panggil `ProductService`, bukan Eloquent langsung. `index/show` tetap read langsung (exception CLAUDE.md: controller boleh inject Repository/read-only untuk query).

**Rationale**: Layering CLAUDE.md bersifat wajib dan baru diperkuat. `StockService` sudah ada sebagai preseden Service di domain inventory — `ProductService` konsisten. Action = satu use case + audit; `LogAuditAction` adalah Action cross-cutting yang boleh di-inject ke Action lain (CLAUDE.md: "May inject: ...event dispatcher, activity log"). 3 Action baru untuk operasi Eloquent trivial memang bertentangan dengan YAGNI murni, **tetapi** audit log (konstitusi VI) + layering (CLAUDE.md) menjadikan Action sebagai tempat yang benar untuk audit — bukan abstraksi prematur, melainkan pemenuhan dua aturan non-negotiable. Ini dicatat di Complexity Tracking plan.md.

**Alternatives considered**:
- (A) Log langsung di controller via `LogAuditAction` — **DITOLAK**: melanggar layering CLAUDE.md (controller wajib via service) + konstitusi VI penempatan audit di Action. Catatan: spec 005 service master R4 memilih log di controller — itu deviasi yang sekarang dilarang oleh penguatan `f72afc1`; spec 007 mengikuti aturan terbaru.
- (B) Audit di Service, tanpa Action — ditolak: Service dilarang menyentuh DB, dan audit adalah unit kerja konkret yang sepantasnya Action.
- (C) Satu `SaveProductAction` gabungan create+update — ditolak: satu Action = satu use case (CLAUDE.md); create & update punya audit shape berbeda (full attributes vs old/new diff).

**Catatan**: `ArchiveProductAction` ditaruh di `app/Actions/Product/` (folder per entity) meski `ArchiveServiceAction` flat di root — aturan terbaru CLAUDE.md menyebut "folder per entity" untuk Action entity; `ArchiveServiceAction` flat adalah warisan pra-aturan (`ponytail:` pindahkan saat service master direvisi). Action cross-cutting (`LogAuditAction`) tetap flat di root.

## R5 — Snapshot immutability `transaction_items` (FR-069, R6)

**Decision**: Tidak ada kode baru. `transaction_items.name` + `unit_price` adalah snapshot immutable (sekali tulis saat transaksi dibuat, tidak di-update). Verifikasi via test: ubah/arsip master produk → `transaction_items` lama tetap utuh. Tidak boleh ada path sinkronisasi snapshot→master.

**Rationale**: ERD `transaction_items.md` + `docs/normalization/README.md` R6 sudah menetapkan snapshot immutable. Saat ini `TransactionItem` tidak punya path update `name`/`unit_price` — hanya dibuktikan via test.

**Alternatives considered**: Tambah guard eksplisit — ditolak (YAGNI, immutable by convention + test cukup).

## R6 — Indikator low-stock (FR-065)

**Decision**: `is_low_stock` sudah computed di `Product` model (`getIsLowStockAttribute`: `stock_balance <= min_threshold`) + di-`append` ke Resource. FE sudah tampilkan Badge "Stok menipis". **Sudah benar, tidak ada perubahan.**

**Rationale**: Implementasi eksisting sesuai spec. Hanya diverifikasi via test (kondisi `<=` termasuk equality — edge case saldo = threshold tetap low).

## R7 — Endpoint hard-delete tidak diekspos (FR-068, YAGNI)

**Decision**: `ProductController::destroy` = arsip (set `status=archived`), bukan hard-delete. Tidak ada endpoint hard-delete permanen. DB `restrictOnDelete` (R2) memblokir hard-delete via path internal (artisan/tinker) bila produk direferensi — dibuktikan via test.

**Rationale**: Konsisten dengan spec 005 service master R2. Hard-delete produk master tidak ada use case MVP; arsip cukup.

**Alternatives considered**: Endpoint force-delete khusus admin — ditolak (YAGNI, berisiko hapus data historis).

## R8 — Arsip tidak muncul di pilihan POS baru (FR-067)

**Decision**: `ProductController::index` default hanya tampilkan produk `active` bila `filter[status]` tidak dikirim (mirror R3 service master). Saat ini index hanya filter status bila filter eksplisit dikirim — **perlu ubah default ke active**. FE halaman master kirim filter eksplisit (`all`/`archived`) untuk lihat arsip.

**Rationale**: FR-067 — produk arsip disembunyikan dari pilihan item POS baru. Saat ini konsumen `ProductResource` (POS, inventory stock-movement-form product dropdown) memakai `GET products` tanpa filter → harus default active.

**Catatan**: `StockMovementForm` (inventory) memanggil `GET products?per_page=100` tanpa filter status → setelah R8 default active, hanya produk aktif muncul di dropdown mutasi. Ini diinginkan (mutasi stok produk arsip jarang; bila butuh, kirim filter `all`). Edge case: produk arsip dengan saldo > 0 tetap bisa di-mutasi via path admin — `ponytail:` tambah filter `all` di dropdown bila butuh.

## R9 — Testing (konstitusi II, delegasi `zahiira`)

**Decision**: Test ditulis lebih dulu (Red) oleh agent `zahiira`, sebelum implementasi. Cakupan:
- Feature: controller CRUD + arsip, default active filter, validation (price negatif, `stock_balance` bukan input).
- Unit: `StockService::adjust()` mutasi saldo + `balance_after` benar.
- FK restrict: hard-delete produk direferensi `stock_movements`/`transaction_items` → `QueryException`.
- Snapshot immutability: ubah/arsip master → `transaction_items` lama utuh.
- No direct `stock_balance` update path: request dengan `stock_balance` tidak mengubah saldo.
- Activity log: create/update/archive menghasilkan row `audit_logs` naratif.
- Tenant isolation: produk tenant A tidak terlihat tenant B.
- Factory: `ProductFactory` (bila belum ada) untuk test.

**Rationale**: Konstitusi II TDD non-negotiable. `ProductFactory` existence perlu dicek — bila belum ada, dibuat.

**Alternatives considered**: Test setelah implementasi — ditolak (melanggar konstitusi II).

## R10 — FE revisi (FR-076, authoring discipline)

**Decision**: FE perubahan:
1. `product-form-modal.tsx`: hapus input `stock_balance` (FR-060). Ubah dari modal create-only → create+edit (prefill saat edit). Mirror `service-form-modal` pattern service master R6.
2. `products/index.tsx`: tambah row-actions "Ubah"+"Arsipkan" (mirror `service-actions-cell` service master), faceted filter status (active/archived/all), kirim filter eksplisit agar arsip tampil di halaman master (R8). Perbaikan breadcrumb sudah benar saat ini (tidak self-link, link ke `/$tenant/clinic`).
3. Form komponen: `FormInput`/`FormSelect`/`FormTextarea`/`FormSubmit`/`useForm` sudah ada & reusable — **tidak ada form komponen baru** di `components/forms/`. Semua field produk (name, unit, min_threshold, price) tercover `FormInput`; status tidak di-form (arsip via row action, bukan field).

**Rationale**: Input user eksplisit "gunakan `components/datatable/` dan `components/forms/` yang sudah ada; bila form belum tersedia buat dulu reusable di `components/forms/`." → semua field produk tercover komponen eksisting, tidak ada form baru. DataTable + `useDataTable` + `DataTableFacetedFilter` eksisting dipakai untuk filter status.

**Alternatives considered**: Buat `FormNumber` terpisah — ditolak (`FormInput type="number"` sudah cukup, tidak ada 2 konsumen nyata yang butuh behavior berbeda).

## R11 — lang/i18n keys (konstitusi V)

**Decision**: Tambah/isi key i18n:
- `product.php`: `+edit`, `+archive`, `+archive_confirm`, `+active`, `+archived` (label filter), `+status_all`.
- `inventory.php`: isi key hilang yang sudah dipakai FE (`product`, `history`, `created_at`, `movement_recorded`) — saat ini FE pakai key yang belum ada di lang file (fallback ke key literal).

**Rationale**: Konstitusi V — teks UI via i18n, dilarang hardcode. Row actions + filter label butuh key baru. Inventory lang incomplet (FE sudah refer key tak ada) — dilengkapi.

**Alternatives considered**: Hardcode string — ditolak (konstitusi V).

## Ringkasan gap yang ditutup (vs eksisting)

| Gap | File | Aksi |
|-----|------|------|
| FK restrict (R2) | migration baru | NEW |
| `stock_balance` bukan input (R3) | `ProductRequest`, `product-form-modal.tsx` | EDIT |
| Layering + activity log (R4) | `ProductService` (NEW), `CreateProductAction`/`UpdateProductAction`/`ArchiveProductAction` (NEW), `ProductController` (EDIT: via Service) | NEW + EDIT |
| Default active filter (R8) | `ProductController::index` | EDIT |
| Row actions edit/archive (R10) | `products/index.tsx` + `product-actions-cell.tsx` | EDIT + NEW |
| Faceted filter status (R10) | `products/index.tsx` | EDIT |
| Form create+edit (R10) | `product-form-modal.tsx` | EDIT |
| i18n keys (R11) | `product.php`, `inventory.php` | EDIT |
| Test (R9) | feature + unit + factory | NEW (delegasi `zahiira`) |

Tidak ada entity/tabel/kolom baru. Tidak ada form komponen baru di `components/forms/`. Service + 3 Action baru adalah pemenuhan layering CLAUDE.md + audit log konstitusi VI, bukan entity baru.
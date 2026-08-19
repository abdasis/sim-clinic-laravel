# Quickstart: AI Chat Booking

**Feature**: 013-ai-chat-booking | **Date**: 2026-08-19

Panduan validasi end-to-end fitur. Lihat [spec.md](./spec.md) untuk requirements, [data-model.md](./data-model.md) untuk entitas, [contracts/](./contracts/) untuk kontrak detail.

## Prasyarat

- DB PostgreSQL jalan (`docker compose up -d db`).
- Server WAHA jalan, satu sesi per tenant, terhubung (QR sudah dipindai).
- `DEEPSEEK_API_KEY` terisi di `.env` apps/api (`DEEPSEEK_BASE_URL=https://api.deepseek.com`, `DEEPSEEK_MODEL=deepseek-v4-pro`).
- Migrasi dijalankan (`php artisan migrate`).
- Queue worker jalan (`php artisan queue:work`) — pesan diproses asinkron.
- Webhook WAHA dikonfigurasi: `config.webhooks: [{ url: "{APP_URL}/api/whatsapp/webhook/{token}", events: ["message"] }]` (via `WahaClient::setWebhook` atau command setup).

## Skenario Validasi

### 1. Info klinik (US1)
**Lakukan**: Kirim WhatsApp dari nomor pasien ke nomor klinik: "Jam buka klinik?" dan "Treatment apa saja yang tersedia dan harganya?"
**Harapan**: Balasan dalam <15 detik berisi jam operasional dan daftar layanan+tarif sesuai database. Lalu kirim "Berapa skor timnas?" → balasan menolak (hanya konteks klinik).

### 2. Booking via chat (US2)
**Lakukan**: Kirim "Saya mau booking facial besok jam 14".
**Harapan**: AI identifikasi pasien dari nomor (jika terdaftar), panggil `create_booking`, balas konfirmasi (layanan, dokter, waktu, status pending). Cek DB: booking baru ada dengan metadata benar; audit log berisi narasi + `source: chatbot`. Kirim variasi tanpa layanan/waktu → AI tanya klarifikasi.

### 3. Anti-halusinasi (US3)
**Lakukan**: Tanyakan "Harga laser?" (layanan tidak ada) dan "Booking dengan dr. Budi" (tidak ada).
**Harapan**: AI nyatakan tidak tersedia, tidak mengarang harga/nama.

### 4. Stok produk (FR-008a)
**Lakukan**: Kirim "Stok vitamin C masih ada?"
**Harapan**: AI balas saldo stok produk (atau tidak tersedia), sebut menipis bila `is_low_stock`.

### 5. Pengaturan admin (US4)
**Lakukan**: Login admin klinik, buka halaman pengaturan chatbot. Aktifkan, set nama agent "Asisten Meba", upload avatar, pilih layanan yang boleh dibooking, simpan. Nonaktifkan, kirim pesan → tidak dibalas. Aktifkan, kirim pesan → AI pakai nama baru.
**Harapan**: Pengaturan tersimpan per-tenant; tidak memengaruhi klinik lain.

## Pemeriksaan Otomatis

- `php artisan test --filter=Chatbot` (Feature test: webhook, tools, booking, isolasi tenant, pengaturan).
- `php artisan test -c phpunit.pgsql.xml --filter=Chatbot` sebelum rilis (FK RESTRICT).
- `vendor/bin/pint` (format BE).
- `npx tsc --noEmit --incremental` (FE typecheck).

## Catatan

- Jangan auto-run `bun run dev`/`php artisan serve`/`queue:work` — jalankan sendiri saat validasi.
- Implementasi detail (migration, controller, action, service, job, FE) ada di `tasks.md` (dibuat `/speckit-tasks`).
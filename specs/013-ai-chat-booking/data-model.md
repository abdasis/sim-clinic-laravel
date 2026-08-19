# Data Model: AI Chat Booking

**Feature**: 013-ai-chat-booking | **Date**: 2026-08-19

## Entitas Baru

### ChatbotSetting

Konfigurasi chatbot per-tenant. Singleton per tenant (satu baris).

| Field | Tipe | Constraint | Keterangan |
|-------|------|-----------|-----------|
| id | bigIncrements | PK | |
| tenant_id | foreignId | NOT NULL, unique, → tenants.id RESTRICT | satu baris per tenant |
| is_active | boolean | default false | status aktif chatbot |
| agent_name | string(100) | nullable | nama agent chatbot |
| agent_avatar_path | string(255) | nullable | path file avatar di disk `public` |
| bookable_service_ids | json | nullable | array service.id yang boleh dibooking via chat; null = semua |
| created_at | timestamps | | |
| updated_at | timestamps | | |

- `BelongsToTenant`, `ScopedBy([TenantScope])`.
- Relasi: `tenant` (BelongsTo), `bookableServices` (BelongsToMany via `bookable_service_ids` JSON — read manual, bukan relasi Eloquent formal karena JSON).
- Validasi: `agent_name` max 100; `agent_avatar_path` path valid di disk public; `bookable_service_ids` array of id yang ada di tenant.

### ChatMessage

Riwayat percakapan per pengirim per tenant.

| Field | Tipe | Constraint | Keterangan |
|-------|------|-----------|-----------|
| id | bigIncrements | PK | |
| tenant_id | foreignId | NOT NULL → tenants.id RESTRICT | |
| sender_phone | string(20) | NOT NULL | nomor WhatsApp dinormalisasi (62xxx) |
| direction | string | NOT NULL, enum `in`/`out` | arah pesan |
| content | text | NOT NULL | isi pesan teks |
| role | string | nullable | `user`/`assistant`/`tool`/`system` (untuk replay ke AI) |
| tool_name | string | nullable | nama tool bila role=tool |
| tool_call_id | string | nullable | id tool call dari DeepSeek |
| created_at | timestamps | | |

- `BelongsToTenant`, `ScopedBy([TenantScope])`.
- Index: `(tenant_id, sender_phone, created_at)` untuk ambil riwayat terbaru per pengirim.
- Tidak ada FK ke `patients` (pengirim mungkin bukan pasien terdaftar).
- Riwayat dibatasi: ambil 10 pesan terakhir per pengirim sebagai konteks AI; purge pesan >30 hari (command opsional, out of scope MVP).

## Entitas Eksisting (dipakai, tidak diubah skema)

### Booking (dipakai untuk chat booking)
- Field sama: `patient_id`, `service_id`, `assignee_id`, `start_at`, `end_at`, `status`, `notes`, `tenant_id`.
- Booking dari chatbot: `notes` diisi penanda `source: chatbot` ATAU audit log properties `source: 'chatbot'` (pilih: audit properties, tanpa ubah skema booking).
- Tidak ada kolom baru di tabel `bookings`.

### Patient
- `whatsapp` dipakai untuk identifikasi pengirim (normalisasi via `PhoneNumber`).

### Service
- `name`, `price` (atau field tarif) dipakai di tool `search_services`.
- `bookable_service_ids` di ChatbotSetting membatasi `create_booking`.

### Product
- `name`, `unit`, `stock_balance`, `min_threshold` (`is_low_stock`) dipakai di tool `get_product_stock`.

### User (sebagai assignee)
- `clinic_role` doctor/therapist dipakai di tool `search_staff` dan validasi `create_booking`.

### Tenant
- `name`, `phone` dipakai di tool `get_clinic_info`.
- Operating hours: dari memory klinik / company profile / konfigurasi (tidak ada kolom operating_hours di tabel tenants — lihat catatan).

## Catatan

- Operating hours / jam operasional klinik: periksa implementasi aktual — bila belum ada kolom, `get_clinic_info` bisa return jam dari `ChatbotSetting` baru (field `operating_hours` JSON) atau dari company profile. Asumsi MVP: tambah field `operating_hours` (JSON, mis. `{mon: "09:00-17:00", ...}`) ke `ChatbotSetting` bila tidak ada sumber lain — ditentukan di tasks. Untuk sekarang, `get_clinic_info` return nama, alamat (dari company profile/tenant), phone, dan jam (sumber diselesaikan saat implementasi).
- Media pesan (gambar/suara): tidak disimpan; pesan media diabaikan/dibalas "hanya teks didukung" (FR-019).
- Tidak ada relasi formal Booking → ChatMessage; keterkaitan via audit log context.
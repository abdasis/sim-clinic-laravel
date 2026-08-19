# Research: AI Chat Booking

**Feature**: 013-ai-chat-booking | **Date**: 2026-08-19

## R1 — Integrasi AI DeepSeek (function calling via HTTP)

**Decision**: DeepSeek API OpenAI-compatible, dipanggil langsung via HTTP (`Http::post`) dari Service — bukan pakai SDK PHP, untuk menghindari dependensi baru (YAGNI, konstitusi Prinsip IV).

**Rationale**: Endpoint `POST https://api.deepseek.com/chat/completions`, header `Authorization: Bearer {DEEPSEEK_API_KEY}`, body JSON dengan `model`, `messages`, `tools`, `tool_choice`. Model default `deepseek-v4-pro` (bisa `deepseek-v4-flash` untuk respons cepat). Response berisi `choices[0].message.tool_calls` (array) bila AI memutuskan memanggil fungsi; setelah tool dijalankan, hasil dimasukkan sebagai message `role: "tool"` dengan `tool_call_id`, lalu request ulang hingga `finish_reason` bukan `tool_calls`. Ini loop function calling standar (dikonfirmasi dari dokumentasi Context7).

**Alternatives considered**:
- OpenAI PHP SDK: menambah dependensi; `Http` facade sudah cukup untuk satu endpoint.
- Thinking mode (`reasoning_effort`/`thinking`): ditambahkan opsional untuk booking kompleks, tapi default nonaktif untuk menjaga latensi <15 detik (SC-001).

**Implementasi**:
- `App\Support\DeepSeekClient` — klien HTTP tipat sama dengan `WahaClient` (constructor inject `base_url`, `api_key`, `model` dari config/env). Metode `chatCompletion(array $messages, array $tools): array`.
- Config: `config/services.php` key `deepseek` (`base_url`, `api_key`, `model`); env `DEEPSEEK_API_KEY`, `DEEPSEEK_BASE_URL=https://api.deepseek.com`, `DEEPSEEK_MODEL=deepseek-v4-pro`.
- API key disimpan di env (bukan DB) karena central, satu key untuk seluruh tenant — sejajar `WahaSetting` (central). Tidak perlu UI admin untuk API key.

## R2 — Webhook inbound WAHA

**Decision**: Tambah route webhook publik `POST /api/whatsapp/webhook` (di luar grup `{tenant}` karena WAHA tidak tahu tenant slug). Webhook menerima payload `{event, session, payload}`, sistem mencari tenant dari `session` lewat `WhatsappSetting`.

**Rationale**: Dokumentasi WAHA (waha.devlike.pro/docs/how-to/receive-messages) mengonfirmasi: webhook dikirim saat event `message`, payload `{event: "message", session: "default", payload: {id, timestamp, from, fromMe, to, body, hasMedia, ...}}`. Webhook dikonfigurasi per sesi via `POST /api/sessions` dengan `config.webhooks: [{url, events: ["message"]}]`. Karena `WahaClient` saat ini hanya outbound, inbound adalah bagian baru.

**Alternatives considered**:
- URL per-tenant `/api/{tenant}/whatsapp/webhook`: WAHA harus dikonfigurasi ke URL berbeda per sesi — bisa, tapi menambah kompleksitas konfigurasi. Dipilih route tunggal + resolve tenant dari `session` karena `WhatsappSetting.session` sudah unik per tenant.
- WebSocket WAHA: lebih real-time tapi butuh proses long-lived; webhook sederhana dan cukup.

**Implementasi**:
- `WahaClient` ditambah metode `setWebhook(string $url, array $events): void` — memanggil `PUT /api/sessions/{session}` dengan `config.webhooks`. Dipanggil saat sesi dibuat/di-update, atau lewat command setup.
- `InboundMessageController` (route publik, no auth) — validasi payload, resolve tenant via `WhatsappSetting::where('session', $session)->first()`, bind tenant ke container (`app()->instance('tenant', $tenant)`) agar `TenantScope` aktif, lalu dispatch job.
- Pesan masuk diproses asinkron via job (`ProcessInboundMessageJob`) agar webhook balas 200 cepat (WAHA retry bila lambat). Job: identifikasi pengirim (PhoneNumber::normalize dari `payload.from`), ambil riwayat chat, panggil AI, kirim balasan via `WahaClient::send`.
- Keamanan webhook: verifikasi via `X-Api-Key` header (WAHA kirim `X-Api-Key`? — tidak; pakai HMAC optional `hmac.key` dari config webhooks, atau secret token di URL query). Sederhana: webhook URL mengandung token random (`/api/whatsapp/webhook/{token}`) yang dicocokkan dengan `WahaSetting`/config. Alternatif: batasi IP server WAHA. Dipilih: token di URL + optional HMAC.

## R3 — Identifikasi pengirim & riwayat percakapan

**Decision**: Identifikasi pasien via `Patient::where('whatsapp', $normalizedFrom)->first()` (scope tenant aktif). Riwayat percakapan: simpan pesan ke tabel `chat_messages` per pengirim per tenant; kirim N pesan terakhir (mis. 10) sebagai konteks ke DeepSeek.

**Rationale**: `Patient.whatsapp` sudah dinormalisasi via `PhoneNumber`. Nomor pengirim dari `payload.from` (`62xxx@c.us`) → strip `@c.us` → `PhoneNumber::normalize`. Bila cocok pasien, booking bisa otomatis terikat `patient_id`. Bila tidak cocok, tetap jawab info umum tapi booking ditolak (butuh identitas pasien — FR-011/skenario 5).

**Alternatives considered**:
- Riwayat via Redis/cache: lebih cepat tapi tak persisten; pilih DB (PostgreSQL) karena sudah ada + untuk audit.
- Tanpa riwayat (stateless): AI tak paham konteks multi-pesan; skenario klarifikasi booking (US2 skenario 2) butuh konteks.

## R4 — Function calling tools (kontrak AI ↔ backend)

**Decision**: Definisikan tools (JSON Schema) untuk AI: `search_services`, `search_staff`, `get_clinic_info`, `get_product_stock`, `create_booking`. Setiap tool dipetakan ke handler PHP (Action/Service) yang mengakses DB ter-scoped tenant.

**Rationale**: FR-004..008a, FR-009. Skema tools mengikuti format OpenAI/DeepSeek (`{type: "function", function: {name, description, parameters: {type: "object", properties, required}}}`).

**Tools**:
1. `search_services` — parameter opsional `query` (nama layanan). Return daftar `{id, name, price, duration}`.
2. `search_staff` — parameter opsional `query`. Return daftar dokter/therapist `{id, name, role}` (filter `clinic_role` doctor/therapist).
3. `get_clinic_info` — no parameter. Return `{name, address, phone, operating_hours}` (dari Tenant / company profile / memory klinik).
4. `get_product_stock` — parameter `query` (nama produk). Return `{name, unit, stock_balance, is_low_stock}` atau daftar.
5. `create_booking` — parameter `patient_id`, `service_id`, `assignee_id`, `start_at`, `end_at` (required). Validasi + panggil `CreateBookingAction`. Return `{booking_id, status, overlap_warnings}` atau error.

**Anti-halusinasi**: system prompt menginstruksikan AI hanya menjawab dari hasil tool; bila tool return kosong, nyatakan tidak tersedia. FR-003/005.

## R5 — Pengaturan chatbot per-tenant (UI admin)

**Decision**: Model baru `ChatbotSetting` per tenant (`is_active`, `agent_name`, `agent_avatar_path`, `bookable_service_ids` JSON). Halaman web admin sederhana di `apps/web/src/routes/$tenant/clinic/chatbot/settings.tsx`. Avatar disimpan via pola `UploadCompanyMediaAction` (disk `public`, path `chatbot/{tenant_id}/avatar`).

**Rationale**: FR-022..025. `bookable_service_ids` membatasi layanan yang boleh dibooking via chat (FR-022/US4 skenario 2); tool `search_services` tetap return semua layanan untuk info, tapi tool `create_booking` menolak layanan di luar daftar. Nama agent dipakai di system prompt ("Kamu adalah {agent_name}, asisten klinik {clinic_name}").

**Alternatives considered**:
- Simpan di `WhatsappSetting` (extend field): bercampur antara konfigurasi WAHA dan chatbot — lebih bersih terpisah.
- Tanpa UI (otomatis): tidak memenuhi Q2: B.

## R6 — Isolasi tenant & keamanan

**Decision**: Webhook route publik resolve tenant dari `session` dan bind ke container sebelum memproses — sehingga semua query (Patient, Service, Product, Booking) ter-scoped `TenantScope` otomatis. Tidak ada akses data lintas tenant.

**Rationale**: Konstitusi Prinsip III. `TenantScope` memfilter `tenant_id` dari `app('tenant')`. Job memproses pesan juga menjalankan dalam konteks tenant (re-bind di job). Rate limit per pengirim via cache (`RateLimiter` Laravel, key per `tenant_id|phone`).

## R7 — Audit log & booking dari chatbot

**Decision**: Setiap booking dari chatbot dicatat via `LogAuditAction` dengan narasi "Membuat booking {layanan} untuk {pasien} melalui chatbot AI". Booking ditandai via kolom/note `source: chatbot` (atau di properties audit). Pesan chat disimpan di `chat_messages` tapi bukan audit log (karena bukan aksi ubah-data).

**Rationale**: Konstitusi Prinsip VI. `CreateBookingAction` eksisting sudah log audit; tambahkan context `source: 'chatbot'` di properties.

## Sumber

- DeepSeek API — function calling & chat completions (Context7 `/websites/api-docs_deepseek`): https://api-docs.deepseek.com/guides/tool_calls , https://api-docs.deepseek.com/api/create-chat-completion
- WAHA — receive messages via webhook: https://waha.devlike.pro/docs/how-to/receive-messages
- Codebase: `WahaClient`, `WhatsappSetting`, `BookingService`/`CreateBookingAction`, `Patient`, `Product`, `UploadCompanyMediaAction`, `TenantScope`.
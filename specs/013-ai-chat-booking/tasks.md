# Tasks: AI Chat Booking

**Input**: Design documents from `/specs/013-ai-chat-booking/`

**Prerequisites**: plan.md (required), spec.md (required), research.md, data-model.md, contracts/

**Tests**: Sertakan test tasks (konstitusi Prinsip II — TDD WAJIB: Red-Green-Refactor). Test task ditulis lebih dulu, konfirmasi GAGAL, baru implementasi.

**Organization**: Tasks dikelompokkan per user story agar tiap story bisa diimplementasi & diuji mandiri.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Dapat jalan paralel (file berbeda, tanpa dependensi pada task belum selesai)
- **[Story]**: User story pemilik task (US1, US2, US3, US4)
- Path file lengkap disebut di deskripsi

## Path Conventions

- **Backend**: `apps/api/` (Laravel). Migration `database/migrations/`, model `app/Models/`, controller `app/Http/Controllers/`, service `app/Services/`, action `app/Actions/<Entity>/`, request `app/Http/Requests/`, resource `app/Http/Resources/`, job `app/Jobs/`, support `app/Support/`, policy `app/Policies/`, test `tests/Feature/`.
- **Frontend**: `apps/web/src/routes/$tenant/clinic/chatbot/`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Konfigurasi & dependency dasar untuk fitur.

- [ ] T001 [P] Tambah config DeepSeek di `apps/api/config/services.php` (key `deepseek`: `base_url`, `api_key`, `model`) + env `DEEPSEEK_API_KEY`, `DEEPSEEK_BASE_URL=https://api.deepseek.com`, `DEEPSEEK_MODEL=deepseek-v4-pro` di `apps/api/.env.example`
- [ ] T002 [P] Tambah konstanta/token webhook di config (`apps/api/config/services.php` key `waha.webhook_token`) untuk verifikasi route webhook

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Infrastruktur inti yang WAJIB selesai sebelum user story manapun.

**⚠️ CRITICAL**: Tidak ada story work sebelum phase ini selesai.

- [ ] T003 [P] Buat migration `apps/api/database/migrations/2026_08_19_create_chatbot_settings_table.php` (tabel `chatbot_settings`: tenant_id unique FK RESTRICT, is_active bool default false, agent_name nullable, agent_avatar_path nullable, bookable_service_ids json nullable, timestamps) — lihat data-model.md
- [ ] T004 [P] Buat migration `apps/api/database/migrations/2026_08_19_create_chat_messages_table.php` (tabel `chat_messages`: tenant_id FK RESTRICT, sender_phone string(20), direction enum in/out, content text, role nullable, tool_name nullable, tool_call_id nullable, timestamps; index `(tenant_id, sender_phone, created_at)`) — lihat data-model.md
- [ ] T005 [P] [US1] Feature test: menerima webhook inbound WAHA + resolve tenant dari session — tulis di `apps/api/tests/Feature/Chatbot/InboundWebhookTest.php`, konfirmasi GAGAL sebelum impl (TDD Red)
- [ ] T006 [P] [US1] Feature test: `DeepSeekClient::chatCompletion` memformat request & parse response/tool_calls — tulis di `apps/api/tests/Feature/Chatbot/DeepSeekClientTest.php` (mock HTTP via `Http::fake`), konfirmasi GAGAL (Red)
- [ ] T007 [P] Buat model `apps/api/app/Models/ChatbotSetting.php` (BelongsToTenant, ScopedBy TenantScope, fillable, casts bookable_service_ids array)
- [ ] T008 [P] Buat model `apps/api/app/Models/ChatMessage.php` (BelongsToTenant, ScopedBy TenantScope, fillable, casts)
- [ ] T009 [P] Buat `apps/api/app/Support/DeepSeekClient.php` (constructor inject base_url/api_key/model dari config; metode `chatCompletion(array $messages, array $tools): array` via `Http::post`, lempar exception bila gagal + `Log::error`) — kontrak R1/contracts
- [ ] T010 Buat `apps/api/app/Support/ChatTools.php` (definisi skema 5 tools sebagai JSON array + map nama→handler; skema persis contracts/webhook-inbound.md section 2) — tergantung T007/T008 untuk handler
- [ ] T011 [P] Tambah route webhook publik `POST /whatsapp/webhook/{token}` di `apps/api/routes/api.php` (di luar grup `{tenant}`, no auth, validasi token dari config)
- [ ] T012 Buat `apps/api/app/Http/Controllers/InboundMessageController.php` (validasi payload, resolve tenant via `WhatsappSetting::where('session', ...)`, bind `app('tenant')`, abaikan `fromMe` & `hasMedia`, dispatch `ProcessInboundMessageJob`, balas 200) — lulus T005 (Green)

**Checkpoint**: Foundation ready — webhook diterima, tenant ter-resolve, DeepSeekClient & ChatTools tersedia. Story impl dapat mulai.

---

## Phase 3: User Story 1 - Tanya-jawab info klinik via chat WhatsApp (Priority: P1) 🎯 MVP

**Goal**: Pasien mengirim pesan WhatsApp, AI menjawab info klinik (layanan, jam, lokasi, stok produk) dari data database via function calling, menolak topik di luar konteks.

**Independent Test**: Kirim "Jam buka klinik?" → balasan jam dari DB. Kirim "Stok vitamin C masih ada?" → balasan saldo stok. Kirim "Berita terbaru?" → balasan menolak (hanya konteks klinik). Lihat quickstart.md skenario 1 & 4.

### Tests for User Story 1 (TDD Red dulu)

- [ ] T013 [P] [US1] Feature test: tool `search_services` return layanan sesuai DB ter-scope tenant — `apps/api/tests/Feature/Chatbot/SearchServicesToolTest.php`, konfirmasi GAGAL
- [ ] T014 [P] [US1] Feature test: tool `get_clinic_info` return info klinik (nama, alamat, telepon, jam) — `apps/api/tests/Feature/Chatbot/GetClinicInfoToolTest.php`, konfirmasi GAGAL
- [ ] T015 [P] [US1] Feature test: tool `get_product_stock` return stok produk + status menipis — `apps/api/tests/Feature/Chatbot/GetProductStockToolTest.php`, konfirmasi GAGAL
- [ ] T016 [P] [US1] Feature test: AI menolak pertanyaan di luar konteks klinik (system prompt scoping) — `apps/api/tests/Feature/Chatbot/ScopeGuardTest.php` (mock DeepSeek), konfirmasi GAGAL
- [ ] T017 [P] [US1] Feature test: end-to-end pesan masuk → balasan info klinik (mock DeepSeek + WAHA send) — `apps/api/tests/Feature/Chatbot/InfoChatE2ETest.php`, konfirmasi GAGAL

### Implementation for User Story 1

- [ ] T018 [P] [US1] Implement handler tool `search_services` di `apps/api/app/Support/ChatTools.php` (query `Service` ter-scoped tenant, return id/name/price/duration) — lulus T013 (Green)
- [ ] T019 [P] [US1] Implement handler tool `search_staff` di `apps/api/app/Support/ChatTools.php` (query `User` clinic_role doctor/therapist, return id/name/role)
- [ ] T020 [P] [US1] Implement handler tool `get_clinic_info` di `apps/api/app/Support/ChatTools.php` (Tenant name/phone + company profile address + jam operasional) — lulus T014 (Green)
- [ ] T021 [P] [US1] Implement handler tool `get_product_stock` di `apps/api/app/Support/ChatTools.php` (query `Product` by name, return name/unit/stock_balance/is_low_stock) — lulus T015 (Green)
- [ ] T022 [US1] Buat `apps/api/app/Services/ChatbotService.php` (orkestrasi: bangun system prompt dari `ChatbotSetting.agent_name` + nama klinik, ambil riwayat 10 pesan terakhir dari `ChatMessage`, panggil `DeepSeekClient::chatCompletion` dengan tools, loop eksekusi tool_calls hingga finish_reason != tool_calls, simpan pesan in/out ke ChatMessage, return balasan teks) — lulus T016, T017 (Green)
- [ ] T023 [US1] Buat `apps/api/app/Jobs/ProcessInboundMessageJob.php` (identifikasi pengirim via `PhoneNumber::normalize`, rate limit per pengirim via `RateLimiter`, panggil `ChatbotService`, kirim balasan via `WahaClient::send`, fallback ramah + `Log::error` bila gagal)
- [ ] T024 [US1] Tambah i18n key Indonesia semi-formal friendly di `apps/api/lang/id/chatbot.php` (sapaan, penolakan out-of-scope, fallback error, konfirmasi) — identifier English

**Checkpoint**: User Story 1 berfungsi & dapat diuji mandiri. MVP tercapai.

---

## Phase 4: User Story 2 - Booking janji lewat chat WhatsApp (Priority: P1)

**Goal**: Pasien membuat booking via chat; AI ekstrak niat, klarifikasi bila kurang, panggil `create_booking`, balas konfirmasi + peringatan overlap.

**Independent Test**: Kirim "Booking facial besok jam 14" (pasien terdaftar) → booking tercipta di DB + audit log + konfirmasi. Kirim tanpa layanan/waktu → AI tanya klarifikasi. Cek quickstart.md skenario 2.

### Tests for User Story 2 (TDD Red dulu)

- [ ] T025 [P] [US2] Feature test: tool `create_booking` berhasil buat booking untuk pasien terdaftar + layanan bookable + assignee valid — `apps/api/tests/Feature/Chatbot/CreateBookingToolTest.php`, konfirmasi GAGAL
- [ ] T026 [P] [US2] Feature test: tool `create_booking` menolak pasien tak terdaftar, layanan tak bookable, assignee bukan dokter/therapist, waktu lampau — `apps/api/tests/Feature/Chatbot/CreateBookingValidationTest.php`, konfirmasi GAGAL
- [ ] T027 [P] [US2] Feature test: booking dari chatbot tercatat audit log naratif + context `source: chatbot` — `apps/api/tests/Feature/Chatbot/BookingAuditLogTest.php`, konfirmasi GAGAL
- [ ] T028 [P] [US2] Feature test: AI tanya klarifikasi bila niat booking tak lengkap (mock DeepSeek) — `apps/api/tests/Feature/Chatbot/BookingClarificationTest.php`, konfirmasi GAGAL

### Implementation for User Story 2

- [ ] T029 [US2] Buat `apps/api/app/Actions/Chatbot/CreateChatBookingAction.php` (validasi: pasien terdaftar via `Patient` scope, service di `ChatbotSetting.bookable_service_ids` (atau semua bila null), assignee clinic_role doctor/therapist, start_at future; panggil `CreateBookingAction` eksisting; log audit via `LogAuditAction` context `source: chatbot` + narasi "Membuat booking {layanan} untuk {pasien} melalui chatbot AI"; return `{booking_id, status, overlap_warnings}` atau error) — lulus T025, T026, T027 (Green)
- [ ] T030 [US2] Daftarkan handler tool `create_booking` di `apps/api/app/Support/ChatTools.php` (dispatch ke `CreateChatBookingAction`)
- [ ] T031 [US2] Tambah perilaku klarifikasi di `ChatbotService` (system prompt menginstruksikan: bila parameter `create_booking` kurang, tanya user; jangan panggil tool dengan parameter tidak lengkap) — lulus T028 (Green)
- [ ] T032 [US2] Tambah i18n key konfirmasi booking & peringatan overlap di `apps/api/lang/id/chatbot.php`

**Checkpoint**: User Story 1 & 2 berfungsi mandiri.

---

## Phase 5: User Story 3 - Anti-halusinasi (Priority: P2)

**Goal**: AI tidak mengarang data; bila tool kosong, nyatakan tidak tersedia. Sudah terbagi di US1/US2; story ini merumuskan & memverifikasi batasan eksplisit.

**Independent Test**: Tanyakan "Harga laser?" (layanan tak ada) → AI nyatakan tidak tersedia, bukan mengarang. Tanyakan dokter tak ada → AI sebut tak ditemukan + tawarkan yang ada. Cek quickstart.md skenario 3.

### Tests for User Story 3 (TDD Red dulu)

- [ ] T033 [P] [US3] Feature test: tool return kosong → AI nyatakan tidak tersedia (mock DeepSeek, assert prompt instruksi anti-halusinasi + assert handler return `[]` ditangani) — `apps/api/tests/Feature/Chatbot/AntiHallucinationTest.php`, konfirmasi GAGAL

### Implementation for User Story 3

- [ ] T034 [US3] Perkuat system prompt anti-halusinasi di `ChatbotService` ("HANYA jawab dari hasil tool. Dilarang mengarang harga/jadwal/nama. Bila tool kosong, nyatakan tidak tersedia.") + handler tools return `[]` konsisten — lulus T033 (Green)
- [ ] T035 [US3] Tambah i18n key "tidak tersedia" / "tidak ditemukan" ramah di `apps/api/lang/id/chatbot.php`

**Checkpoint**: Semua story anti-halusinasi terverifikasi.

---

## Phase 6: User Story 4 - Pengaturan chatbot & personalisasi agent oleh admin (Priority: P3)

**Goal**: Admin klinik mengaktifkan/nonaktifkan chatbot, pilih layanan bookable, set nama & avatar agent via halaman web.

**Independent Test**: Buka halaman pengaturan, nonaktifkan → pesan tak dibalas. Aktifkan + ubah nama agent → AI pakai nama baru. Upload avatar → tersimpan per-tenant. Cek quickstart.md skenario 5.

### Tests for User Story 4 (TDD Red dulu)

- [ ] T036 [P] [US4] Feature test: GET/PUT `/{tenant}/clinic/chatbot/settings` otorisasi admin + persist `is_active`, `agent_name`, `bookable_service_ids` — `apps/api/tests/Feature/Chatbot/ChatbotSettingApiTest.php`, konfirmasi GAGAL
- [ ] T037 [P] [US4] Feature test: POST avatar upload simpan ke disk `public` path `chatbot/{tenant_id}/avatar` — `apps/api/tests/Feature/Chatbot/ChatbotAvatarUploadTest.php`, konfirmasi GAGAL
- [ ] T038 [P] [US4] Feature test: isolasi tenant — pengaturan klinik A tidak bocor ke klinik B — `apps/api/tests/Feature/Chatbot/ChatbotSettingIsolationTest.php`, konfirmasi GAGAL
- [ ] T039 [P] [US4] Feature test: chatbot nonaktif (`is_active=false`) → `ProcessInboundMessageJob` tak memproses/balas — `apps/api/tests/Feature/Chatbot/InactiveChatbotTest.php`, konfirmasi GAGAL

### Implementation for User Story 4

- [ ] T040 [P] [US4] Buat `apps/api/app/Http/Requests/ChatbotSettingRequest.php` (validasi `is_active` bool, `agent_name` max 100, `bookable_service_ids` array of id ter-tenant via `TenantRule::exists('services')`)
- [ ] T041 [P] [US4] Buat `apps/api/app/Http/Requests/ChatbotAvatarRequest.php` (field `file` required image mimes jpg/jpeg/png/webp max 2048, pola `MediaUploadRequest`)
- [ ] T042 [P] [US4] Buat `apps/api/app/Http/Resources/ChatbotSettingResource.php` (is_active, agent_name, agent_avatar_url, bookable_service_ids)
- [ ] T043 [P] [US4] Buat `apps/api/app/Policies/ChatbotSettingPolicy.php` (otorisasi admin klinik) + daftar Gate di `ClinicServiceProvider` bila perlu
- [ ] T044 [US4] Buat `apps/api/app/Actions/Chatbot/SaveChatbotSettingAction.php` (upsert ChatbotSetting per tenant, log audit naratif) — lulus T036 (Green)
- [ ] T045 [US4] Buat `apps/api/app/Actions/Chatbot/UploadChatbotAvatarAction.php` (simpan ke disk public path `chatbot/{tenant_id}/avatar`, log audit, pola `UploadCompanyMediaAction`) — lulus T037 (Green)
- [ ] T046 [US4] Buat `apps/api/app/Http/Controllers/ChatbotSettingController.php` (GET/PUT settings + POST avatar, otorisasi via policy, return Resource) — lulus T036, T037 (Green)
- [ ] T047 [US4] Tambah routes `/{tenant}/clinic/chatbot/settings` (GET, PUT) + `/{tenant}/clinic/chatbot/settings/avatar` (POST) di `apps/api/routes/api.php` dalam grup clinic
- [ ] T048 [US4] Tambah gate `is_active` check di `ProcessInboundMessageJob`/`ChatbotService` (skip bila `ChatbotSetting.is_active=false`) — lulus T039 (Green)
- [ ] T049 [P] [US4] Tambah i18n key UI admin di `apps/api/lang/id/chatbot.php` (label aktif, nama agent, avatar, layanan bookable, pesan tersimpan)
- [ ] T050 [US4] Buat halaman FE `apps/web/src/routes/$tenant/clinic/chatbot/settings.tsx` (toggle aktif, input nama agent, upload avatar, multi-select layanan bookable, breadcrumb; ≤300 baris, gaya Linear, tooltip+state lengkap per CLAUDE.md) — gunakan `FormSwitch`/`FormInput`/`FormSelect`/`MediaField` eksisting
- [ ] T051 [US4] Tambah entri sidebar chatbot di `apps/web/src/routes/$tenant/clinic/route.tsx` (role-filtered, grup Sistem/Komunikasi) + i18n key FE

**Checkpoint**: Semua user story berfungsi mandiri & terverifikasi.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Perbaikan lintas story.

- [ ] T052 [P] Tambah i18n key FE chatbot di `apps/web` (label menu, breadcrumb, pesan) via `useTrans`
- [ ] T053 Run `vendor/bin/pint` di `apps/api` untuk format/lint
- [ ] T054 Run `npx tsc --noEmit --incremental` di `apps/web` untuk typecheck
- [ ] T055 Run `php artisan test --filter=Chatbot` (sqlite); perbaiki test yang gagal
- [ ] T056 Run `php artisan test -c phpunit.pgsql.xml --filter=Chatbot` sebelum rilis (FK RESTRICT)
- [ ] T057 [P] Tambah command opsional `php artisan chatbot:setup-webhook` (konfigurasi webhook WAHA via `WahaClient::setWebhook` untuk semua tenant aktif) di `apps/api/app/Console/Commands/SetupChatbotWebhookCommand.php`
- [ ] T058 Run validasi quickstart.md (5 skenario end-to-end manual)
- [ ] T059 [P] Tambah factory `ChatbotSettingFactory` + `ChatMessageFactory` di `apps/api/database/factories/` untuk dukungan test

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: Tanpa dependensi — mulai segera. T001, T002 paralel.
- **Foundational (Phase 2)**: T003-T004 (migrations) paralel; T005-T006 (test Red) paralel; T007-T009 (model, client) paralel; T010 (ChatTools) tergantung T007-T008; T011 (route) tergantung T002; T012 (controller) tergantung T005, T011. BLOCKS semua story.
- **User Stories (Phase 3-6)**: Semua tergantung Foundational. US1 (Phase 3) adalah MVP; US2 (Phase 4) membangun di atas ChatTools/ChatbotService dari US1; US3 (Phase 5) memperkuat prompt; US4 (Phase 6) menambah pengaturan + gate is_active.
- **Polish (Phase 7)**: Tergantung story yang diinginkan selesai.

### User Story Dependencies

- **US1 (P1)**: Mulai setelah Foundational. Independen — MVP.
- **US2 (P1)**: Mulai setelah Foundational. Berbagi `ChatbotService`/`ChatTools` dengan US1 (bekerja di file sama secara berurutan, bukan paralel) — namun bisa diuji mandiri.
- **US3 (P2)**: Mulai setelah US1/US2 (memperkuat prompt di `ChatbotService`). Independen secara tes.
- **US4 (P3)**: Mulai setelah Foundational. Independen (tambah entity/route/FE baru) — bisa paralel dengan US1/US2 bila fokus BE/FE berbeda. Gate `is_active` (T048) tergantung `ProcessInboundMessageJob` (T023).

### Within Each User Story

- Test task (TDD Red) ditulis & konfirmasi GAGAL sebelum implementasi.
- Model/migration sebelum service/action.
- Service/action sebelum controller/route.
- Core sebelum integrasi.
- Story selesai sebelum prioritas berikutnya (kecuali paralel tim).

### Parallel Opportunities

- T001, T002 (setup config) paralel.
- T003, T004 (migrations) paralel.
- T005, T006 (foundational test Red) paralel.
- T007, T008, T009 (model, DeepSeekClient) paralel.
- T013-T017 (US1 test Red) paralel.
- T018-T021 (US1 tool handlers — file sama `ChatTools.php`, jadi berurutan bila konflik, tapi logika independen).
- T040-T043, T049 (US4 request/resource/policy/i18n — file berbeda) paralel.
- T052, T057, T059 (polish, file berbeda) paralel.

---

## Parallel Example: User Story 1

```bash
# Test Red US1 (paralel, file berbeda):
Task: "T013 search_services tool test di tests/Feature/Chatbot/SearchServicesToolTest.php"
Task: "T014 get_clinic_info tool test di tests/Feature/Chatbot/GetClinicInfoToolTest.php"
Task: "T015 get_product_stock tool test di tests/Feature/Chatbot/GetProductStockToolTest.php"
Task: "T016 scope guard test di tests/Feature/Chatbot/ScopeGuardTest.php"
Task: "T017 E2E info chat test di tests/Feature/Chatbot/InfoChatE2ETest.php"

# Tool handlers US1 (ChatTools.php — berurutan bila konflik, logika independen):
Task: "T018 search_services handler di app/Support/ChatTools.php"
Task: "T019 search_staff handler di app/Support/ChatTools.php"
Task: "T020 get_clinic_info handler di app/Support/ChatTools.php"
Task: "T021 get_product_stock handler di app/Support/ChatTools.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (T001-T002)
2. Complete Phase 2: Foundational (T003-T012) — BLOCKS semua story
3. Complete Phase 3: User Story 1 (T013-T024)
4. **STOP & VALIDATE**: Test US1 mandiri (quickstart skenario 1 & 4)
5. Deploy/demo bila siap — MVP chatbot tanya-jawab berfungsi

### Incremental Delivery

1. Setup + Foundational → foundation ready
2. + US1 → tes mandiri → demo (MVP!)
3. + US2 → tes mandiri → demo (booking via chat)
4. + US3 → tes mandiri → demo (anti-halusinasi terverifikasi)
5. + US4 → tes mandiri → demo (pengaturan admin + personalisasi)
6. Polish → rilis

### Parallel Team Strategy

Dengan beberapa developer:
1. Tim selesaikan Setup + Foundational bersama
2. Setelah Foundational:
   - Developer A (BE): US1 → US2 → US3 (berurutan, file `ChatbotService`/`ChatTools` sama)
   - Developer B (BE+FE): US4 (pengaturan admin — relatif independen)
3. Story complete & integrate mandiri

---

## Notes

- [P] tasks = file berbeda, tanpa dependensi pada task belum selesai.
- [Story] label memetakan task ke user story untuk traceability.
- Tiap user story dapat diselesaikan & diuji mandiri.
- Konfirmasi test GAGAL sebelum implementasi (TDD Red-Green-Refactor, konstitusi Prinsip II).
- Commit setelah tiap task atau kelompok logis. Tidak ada emoji/AI marker di commit.
- Stop di checkpoint untuk validasi story mandiri.
- Jalankan `php artisan test` (jangan auto-run `serve`/`queue:work`/`bun run dev` — user jalankan sendiri saat validasi).
- Delegasi: BE Laravel → agent `ammar` (pakai skill `/laravel-best-practices` + `/clean-code-principles`); FE → agent `sierly`. Push BE → `haikal` review `/code-review` low.
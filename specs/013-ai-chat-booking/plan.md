# Implementation Plan: AI Chat Booking

**Branch**: `013-ai-chat-booking` | **Date**: 2026-08-19 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/013-ai-chat-booking/spec.md`

## Summary

Chatbot WhatsApp berbasis DeepSeek (function calling) yang menjawab pertanyaan seputar klinik (layanan, jam, lokasi, stok produk) dan membuat booking janji langsung dari chat. Semua jawaban berakar pada data database via tool calls — tidak ada halusinasi. Menerima pesan masuk via webhook WAHA baru (saat ini hanya outbound), mengaitkan tenant dari nama sesi WAHA. Admin klinik dapat mengaktifkan chatbot, memilih layanan yang boleh dibooking, serta menyetting nama & avatar agent. Pendekatan teknis: route webhook publik → job asinkron → `DeepSeekClient` (HTTP) dengan loop tool_calls → eksekusi tool (Action ter-scoped tenant) → balas via `WahaClient`.

## Technical Context

**Language/Version**: PHP 8.3+ (Laravel 13, apps/api); TypeScript (TanStack Start, apps/web).

**Primary Dependencies**: Laravel (Http facade, queue, RateLimiter), spatie/laravel-activitylog (audit), DeepSeek API (HTTP, OpenAI-compatible), WAHA (HTTP, webhook inbound baru). Tanpa dependensi baru — `Http` facade cukup untuk DeepSeek & WAHA.

**Storage**: PostgreSQL (tabel baru `chatbot_settings`, `chat_messages`); disk `public` (avatar agent, pola `UploadCompanyMediaAction`).

**Testing**: PHPUnit (Feature test webhook, tools, booking, isolasi tenant, pengaturan); `php artisan test`; `phpunit.pgsql.xml` sebelum rilis (FK RESTRICT).

**Target Platform**: Linux server (API + queue worker); browser (FE admin).

**Project Type**: web-service (API Laravel) + web-app (TanStack Start) monorepo.

**Performance Goals**: Balasan AI <15 detik rata-rata (SC-001); webhook balas 200 <2 detik (proses asinkron via job).

**Constraints**: Isolasi tenant ketat (Prinsip III); class PHP ≤300 baris, method ≤100 baris (Prinsip V); audit log naratif (Prinsip VI); anti-halusinasi (FR-003/005); tidak menambah dependensi (Prinsip IV).

**Scale/Scope**: Multi-tenant, satu sesi WAHA per tenant; riwayat chat 10 pesan terakhir sebagai konteks; rate limit per pengirim.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Prinsip | Status | Catatan |
|---------|--------|--------|
| I. Clean Code | PASS | Nama deskriptif, SOLID (Controller→Service→Action), DRY (tool handlers terpusat), error handling di trust boundary (webhook + DeepSeek). |
| II. TDD | PASS (rencana) | Test task ditulis lebih dulu per user story di tasks.md; Feature test untuk webhook, tools, booking, pengaturan. |
| III. Multi-Tenant Isolation | PASS | Webhook resolve tenant dari `session`, bind ke container → `TenantScope` aktif; job re-bind tenant; tidak ada query lintas tenant. |
| IV. Simplicity (YAGNI) | PASS | Tanpa SDK DeepSeek (`Http` cukup); `ChatbotSetting` terpisah dari `WhatsappSetting` (2 konsumen: chatbot + admin UI); interface/factory dihindari. |
| V. Bounded Size | PASS | Class ≤300 baris, method ≤100 baris; FE halaman pengaturan ≤300 baris. Tool handlers di-extract bila perlu. |
| VI. Permission & Activity Log | PASS | Audit log naratif untuk booking via `LogAuditAction` (context `source: chatbot`); exception di-log via `Log::error`. |

Tidak ada violation yang perlu justifikasi — Complexity Tracking kosong.

## Project Structure

### Documentation (this feature)

```text
specs/013-ai-chat-booking/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── webhook-inbound.md
└── tasks.md             # /speckit-tasks (belum dibuat)
```

### Source Code (repository root)

```text
apps/api/
├── app/
│   ├── Http/Controllers/
│   │   ├── InboundMessageController.php      # webhook WAHA (publik)
│   │   └── ChatbotSettingController.php       # CRUD pengaturan admin
│   ├── Http/Requests/
│   │   ├── ChatbotSettingRequest.php
│   │   └── ChatbotAvatarRequest.php
│   ├── Http/Resources/
│   │   └── ChatbotSettingResource.php
│   ├── Services/
│   │   ├── ChatbotService.php                 # orkestrasi: riwayat, panggil AI, tool loop, balas
│   │   └── DeepSeekClient.php (App/Support)   # klien HTTP DeepSeek
│   ├── Actions/Chatbot/
│   │   ├── CreateChatBookingAction.php        # tool create_booking (reuso CreateBookingAction)
│   │   ├── SaveChatbotSettingAction.php
│   │   └── UploadChatbotAvatarAction.php
│   ├── Support/
│   │   ├── ChatTools.php                     # definisi skema tools + handler map
│   │   └── DeepSeekClient.php
│   ├── Jobs/
│   │   └── ProcessInboundMessageJob.php       # proses pesan masuk asinkron
│   ├── Models/
│   │   ├── ChatbotSetting.php
│   │   └── ChatMessage.php
│   └── Policies/
│       └── ChatbotSettingPolicy.php
├── database/migrations/
│   ├── 2026_08_19_create_chatbot_settings_table.php
│   └── 2026_08_19_create_chat_messages_table.php
└── routes/api.php                            # tambah webhook + chatbot routes

apps/web/
└── src/routes/$tenant/clinic/chatbot/
    └── settings.tsx                           # halaman pengaturan admin
```

**Structure Decision**: Web service (Laravel) + web app (TanStack) monorepo, mengikuti layout eksisting. Tool handler (`ChatTools.php`) memusatkan definisi skema + dispatch ke Action/Service ter-scoped tenant, menjaga DRY dan isolasi.

## Complexity Tracking

> Tidak ada violation konstitusi yang perlu justifikasi. Tabel kosong.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| - | - | - |
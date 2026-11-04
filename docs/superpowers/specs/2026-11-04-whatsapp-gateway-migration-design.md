# Migrasi WhatsApp ke Gateway Internal

Tanggal: 2026-11-04
Status: menunggu persetujuan

## Kenapa

Saat ini WhatsApp punya dua jalur sekaligus:

- **WAHA** (`WahaClient`, `WahaSessionState`, `WahaSetting`) untuk kotak masuk, chatbot, reminder, dan konfirmasi booking.
- **Meta Cloud API** (`CloudApiClient`, `MetaWhatsappWebhookController`) untuk broadcast, template resmi, dan sebagian pengingat.

Dua jalur berarti dua kredensial, dua webhook, dua model status, dan dua tempat yang harus diperbaiki setiap kali WhatsApp berubah perilaku. Gateway internal (`/api/v1`, Baileys worker) menggantikan keduanya: satu sesi per klinik, satu webhook bertanda tangan HMAC, satu urutan status pesan.

## Tujuan

- Semua kirim/terima WhatsApp lewat satu klien gateway.
- Tiap klinik punya tenant gateway sendiri (tidak ada plafon 50 sesi bersama), sesinya dikelola dari aplikasi: buat sesi, QR, pairing code, putuskan, mulai ulang, logout, hapus.
- Broadcast dan pengingat tetap berjalan tanpa template Meta: isi pesan ditulis admin, parameter (`nama`, `layanan_terakhir`, `klinik`, dst) dirender aplikasi.
- Status pesan (`sent`, `delivered`, `read`, `failed`) tetap terlihat di kotak masuk lewat webhook gateway.

## Non-tujuan

- Tidak membangun ulang fitur status/story WhatsApp, broadcast list, atau privasi/blocklist gateway. Endpoint itu ada di gateway tapi tidak dipakai aplikasi.
- Tidak memindahkan riwayat percakapan ke gateway; riwayat tetap di basis data klinik.
- Tidak menyimpan media di gateway. Media disimpan di disk publik aplikasi, gateway hanya menerima URL.

## Anti-tujuan

- Jangan mempertahankan `CloudApiClient`/`WahaClient` sebagai fallback. Satu jalur saja; fallback ganda justru yang sedang dibongkar.
- Jangan mengarang field gateway di luar dokumen integrasi dan source gateway di `/home/abdasis/orca/whatsapp-gateway`. Yang belum pasti ditandai di bagian "Perlu verifikasi".

## Batasan gateway yang membentuk fitur

| Kemampuan lama | Setelah migrasi |
| --- | --- |
| Template resmi Meta + approval | Body teks lokal + daftar parameter. Tidak ada status APPROVED. |
| Quote/reply (`reply_to_message_id`) | Hilang. Pesan dikirim tanpa kutipan. Data lama tetap dibaca. |
| Reaction | Hilang. Endpoint, action, dan menu reaksi dihapus. |
| Kirim media biner (base64) | URL publik. Gateway memvalidasi SSRF; `localhost`/IP privat ditolak kecuali gateway dijalankan dengan `allowPrivate`. |
| Indikator mengetik (`/api/startTyping`) | Tetap ada lewat `POST /sessions/:id/presence` (`composing`, lalu `paused`). |
| Kartu kontak | Teks `Nama: +62…` (perilaku WAHA saat ini dipertahankan). |
| Status pesan | Webhook `message.sent/delivered/read/failed`; `messageId` = UUID pesan gateway. |
| Batas 24 jam balasan | Tidak berlaku. Gateway Baileys tidak punya jendela 24 jam; `EnsuresReplyWindow` dihapus. |
| Rate limit | 60 request/menit per API key. Dengan kredensial per klinik, batas ini tidak dibagi antar klinik. Kirim broadcast sudah dijeda 3–7 detik. 429 wajib dihormati lewat `Retry-After`. |

## Keputusan yang sudah disepakati

1. WAHA dan Meta Cloud API dibuang seluruhnya.
2. Broadcast disimpan sebagai teks lokal + gambar opsional; parameter dirender sendiri.
3. Template Meta tidak dikelola lagi; halaman kelola template dihapus.
4. Tenant gateway per klinik, bukan satu tenant bersama.
5. Repo gateway ikut diubah untuk dua hal: webhook secret dikembalikan sekali saat webhook dibuat, dan `message.received` membawa `remoteJidAlt`.
6. Pengirim ber-JID `@lid` memakai `remoteJidAlt` untuk mendapat nomor asli.

## Keputusan teknis (dipilih di sini, bukan hasil diskusi)

### Kredensial dan provisioning

- `whatsapp_gateway_settings` (satu baris, bukan tenant-scoped) hanya berisi `base_url` gateway. Menggantikan `waha_settings` yang di-drop.
- Kredensial per klinik disimpan di `whatsapp_settings`, semuanya `encrypted`:
  `gateway_tenant_key`, `gateway_api_key`, `gateway_webhook_secret`, `gateway_webhook_token`, `gateway_session_id` (UUID sesi), dan `gateway_password` sementara.
- `phone_number_id`, `waba_id`, `reminder_template` di-drop.

Provisioning otomatis saat admin klinik menekan "Hubungkan WhatsApp" dan kredensialnya belum lengkap:

```
token acak 32 byte → gateway_webhook_token
POST /auth/register {name, organizationName, email: tenant-{id}@<GATEWAY_ACCOUNT_DOMAIN>,
                    password acak}
  → simpan gateway_tenant_key + gateway_password (sementara)
POST /api-keys {name: "sim-clinic"}   (Bearer accessToken)
  → simpan gateway_api_key
POST /webhooks {name, url: APP_URL + "/api/whatsapp/webhook/" + token,
                events: [semua event pesan], sessionId: null}
  → simpan gateway_webhook_secret, kosongkan gateway_password
```

Bila salah satu langkah gagal, `gateway_tenant_key` dan `gateway_password` sudah tersimpan sehingga percobaan berikutnya melanjutkan lewat `POST /auth/login`, bukan mendaftar tenant baru. Password dihapus setelah provisioning tuntas; sesudah itu autentikasi hanya lewat API key.

`GATEWAY_ACCOUNT_DOMAIN` adalah config (`config/whatsapp_gateway.php`), satu-satunya env baru.

### Klien dan webhook

- `WhatsappGatewayClient` mengirim `x-tenant-key` + `X-API-Key` milik klinik, membaca envelope `{success, data, error}`, dan memakai `Idempotency-Key`.
- `/messages/*` memakai `gateway_session_id` (UUID); webhook memakai slug `sessionId` untuk memetakan pesan ke klinik.
- Webhook per klinik: `POST /api/whatsapp/webhook/{token}`. Token dicari di `whatsapp_settings`, lalu `X-Webhook-Signature` diverifikasi dengan secret klinik itu. Satu URL bersama tidak mungkin dipakai karena setiap klinik punya secret berbeda dan request harus dipetakan ke klinik sebelum HMAC dihitung.
- Verifikasi: `sha256=HMAC(secret, timestamp + "." + rawBody)`, tolak timestamp lebih dari 5 menit, `hash_equals`, balas 200 cepat. Event dibaca dari header `X-Webhook-Event`.
- Event yang ditangani: `message.received` (teks saja; `remoteJidAlt` dipakai bila `remoteJid` berakhiran `@lid`) dan `message.sent/delivered/read/failed`. `session.*` dan `presence.updated` diabaikan — status koneksi dibaca saat layar meminta.

### Pengiriman

- `ConversationChannel::Gateway = 'gateway'`; migrasi data mengubah `conversations.channel` `'waha'` dan `'cloud_api'` menjadi `'gateway'`.
- Idempotensi broadcast: tambah `broadcast_recipients.send_attempt` (unsigned int, default 0). Kunci = `broadcast-{recipient_id}-{send_attempt}`; `RequeueFailedRecipientsAction` menaikkan `send_attempt`. Job retry memakai kunci sama sehingga tidak dobel, sedangkan kirim ulang manual setelah gagal tidak memutar hasil lama dari cache 24 jam.
- Kotak masuk dan job lain memakai `inbox-{uuid}` / `job-{class}-{id}`.
- Media kotak masuk: simpan ke disk `public`, kirim URL. `image` → `/messages/image`, `audio` → `/messages/audio`, selain itu → `/messages/document`.
- Gambar broadcast: ada gambar → `/messages/image` (caption = `broadcast_recipients.message`), tanpa gambar → `/messages/send`. Validasi menolak gambar dengan pesan lebih dari 1024 karakter (batas caption WhatsApp); teks tanpa gambar tetap 4000.
- Error gateway dipetakan ke `WhatsappGatewayException` (`code`, status, `retryAfter`). `SESSION_NOT_CONNECTED`/`SESSION_NOT_FOUND` menjeda campaign lewat `PauseStalledBroadcastAction` seperti sekarang; 429 melepas job dengan jeda `Retry-After`. Setiap catch menulis `Log::error` kontekstual.
- `ReminderEngine` dan `AutoReminderEngine` memakai `MessageTemplate.body` per klinik atau template bawaan, dirender `TemplateVariables::render`. `reminder_template` dihapus.
- Chatbot: `ProcessInboundMessageJob` memakai klien gateway; typing = presence `composing`, `paused` di `finally`.

### Sesi

`WhatsappGatewaySessionService` menggantikan `WhatsappConnectionService`:

```
state    GET  /sessions/:id/status → {sessionId,status,phoneNumber,lastSeenAt}
         status gateway: DISCONNECTED, INITIALIZING, QR_REQUIRED, CONNECTED,
         RECONNECTING, LOGGED_OUT, ERROR
connect  POST /sessions/:id/connect lalu GET /sessions/:id/qr (data URI PNG)
pairing  POST /sessions/:id/pairing-code {phoneNumber}
restart  POST /sessions/:id/restart
logout   POST /sessions/:id/logout
delete   DELETE /sessions/:id
```

Slug `session` dikunci begitu `gateway_session_id` terisi; mengubahnya hanya lewat logout + hapus sesi, karena webhook memetakan klinik dari slug. Bila sesi sudah ada di gateway tapi UUID belum tersimpan, adopsi lewat `GET /sessions` dengan slug yang sama.

### Cutover

Sebelum deploy, semua broadcast berstatus `Sending` diubah menjadi `Paused` dengan alasan "migrasi gateway". Setelah klinik memindai QR, admin melanjutkan campaign — bukan membiarkan job lama menabrak sesi yang belum tersambung dan menandai penerima gagal.

### E2E

Tidak ada infrastruktur E2E di repo dan pemindaian QR butuh ponsel nyata. Verifikasi otomatis berhenti di Feature test dengan `Http::fake()`. Uji manual: buka dialog koneksi, pindai QR, kirim teks, kirim media, jalankan satu broadcast uji.

## Alur

### 1. Pesan masuk

```
POST /api/whatsapp/webhook/{token}
  → cari WhatsappSetting by gateway_webhook_token (tanpa tenant bound)
  → verifikasi HMAC + timestamp dengan secret klinik
  → message.received: abaikan type !== TEXT; tentukan nomor dari remoteJid
    (pakai remoteJidAlt bila remoteJid berakhir @lid); abaikan non-DM
    → RecordInboundMessageAction(Gateway, messageId)
    → ProcessInboundMessageJob(tenantId, phone, content)
  → message.{sent,delivered,read,failed}: UpdateMessageStatusAction(tenantId, messageId, status)
  → selalu 200 kecuali tanda tangan tidak sah (403)
```

### 2. Kirim teks kotak masuk

`SendInboxMessageAction → client->sendText(gateway_session_id, phone, body, key)`; simpan `provider_message_id = data.messageId`, status `sent`. `replyTo` tidak lagi dikirim ke gateway.

### 3. Kirim media kotak masuk

Simpan ke disk `public` → URL → `sendImage|sendDocument|sendAudio` → simpan `media_*` + `provider_message_id`. Gagal → hapus berkas, `ValidationException`.

### 4. Broadcast

Admin isi body + params + gambar opsional → `CreateBroadcastAction` merender `message` per penerima (sudah ada) → `SendBroadcastRecipientJob` kirim gambar (caption) atau teks dengan kunci idempotensi per attempt → status penerima, campaign ditutup saat antrean habis.

### 5. Pengingat & booking

`ReminderEngine`, `AutoReminderEngine`, `SendBookingConfirmationJob`, `SendBookingReminderJob` memakai `sendText` gateway.

## Perubahan file

### Repo gateway (`/home/abdasis/orca/whatsapp-gateway`)

- `apps/api/src/modules/webhooks/webhooks.service.ts` (+ DTO/contract): kembalikan `secret` sekali pada respons `POST /webhooks`; tetap tidak muncul di list/detail/update.
- `apps/worker/src/modules/whatsapp/engine/baileys.adapter.ts`: teruskan `key.remoteJidAlt`; `IncomingMessage`, penyimpanan pesan, dan payload `message.received` membawa `remoteJidAlt`.
- Test: signature tetap hijau; test payload received memuat `remoteJidAlt`; test create webhook mengembalikan secret sekali.

### Aplikasi — dihapus

- `apps/api/app/Support/{WahaClient,WahaException,WahaSessionState,CloudApiClient,CloudApiException}.php`
- `apps/api/app/Models/WahaSetting.php`
- `apps/api/app/Http/Controllers/MetaWhatsappWebhookController.php`
- `apps/api/app/Http/Controllers/CentralWahaSettingController.php`
- `apps/api/app/Console/Commands/SetupChatbotWebhookCommand.php`
- `apps/api/config/{waha,meta_whatsapp}.php`
- `apps/api/app/Actions/Inbox/SendInboxReactionAction.php`
- Frontend: `central/waha/`, `broadcasts/templates/`, komponen template Meta (`whatsapp-template-manager`, `template-form`, `template-picker`, `template-preview`, `template-guide`, `template-image-field`), menu reaksi di kotak masuk.

`ChatbotDiagnostics` disederhanakan ke pemeriksaan status sesi gateway.

### Aplikasi — dibuat

- `apps/api/app/Support/WhatsappGatewayClient.php`
- `apps/api/app/Support/WhatsappGatewayException.php`
- `apps/api/app/Support/WhatsappGatewayWebhook.php` — verifikasi HMAC/timestamp
- `apps/api/app/Http/Controllers/WhatsappGatewayWebhookController.php`
- `apps/api/app/Http/Controllers/CentralWhatsappGatewaySettingController.php`
- `apps/api/app/Models/WhatsappGatewaySetting.php`
- `apps/api/app/Services/WhatsappGatewayProvisioningService.php`
- `apps/api/app/Services/WhatsappGatewaySessionService.php`
- `apps/api/config/whatsapp_gateway.php` (`base_url`, `account_domain`)
- Migrasi: buat `whatsapp_gateway_settings`; drop `waha_settings`; ubah `whatsapp_settings` (−3 kolom, +6 kolom gateway); ubah nilai `conversations.channel`; tambah `broadcast_recipients.send_attempt`; drop `broadcast_recipients.template_values`; jeda semua broadcast aktif.
- Tests: `tests/Feature/Whatsapp/GatewayWebhookTest.php`, `GatewaySendTest.php`, `GatewayProvisioningTest.php`, `GatewaySessionTest.php`; perbarui test broadcast/inbox/chatbot/booking yang memakai fake WAHA/Meta.

### Aplikasi — diubah

- `BroadcastController`: hapus `templates`/`manageTemplates`/`storeTemplate`/`destroyTemplate`; `settings`/`updateSettings` hanya slug sesi + batas harian; `connection`/`prepareConnection` memakai layanan baru; `dashboard` tetap.
- `BroadcastService::sendTest` → kirim teks/gambar lewat gateway.
- `SendBroadcastRecipientJob`, `ProcessInboundMessageJob`, `SendInboxMessageAction`, `SendInboxMediaAction`, `SendInboxContactAction`, `SendBookingConfirmationJob`, `SendBookingReminderJob`.
- `ReminderEngine`, `AutoReminderEngine`, `WhatsappSetting`, `WhatsappSessionRequest`, `BroadcastRequest`, `BroadcastTestRequest`, `BroadcastUpdateRequest`, `RequeueFailedRecipientsAction`.
- `ConversationChannel`, `routes/api.php`, `AppServiceProvider`, `lang/id/*`.
- Frontend: `broadcast-form`, `broadcast-edit-form`, `template-preview` → pratinjau body+params, `connection-dialog` (QR data URI + pairing code), `broadcast-settings-dialog`, `auto-reminder-dialog`, `central/waha` → `central/whatsapp-gateway`, inbox (hapus reaksi & kutipan).

## Perlu verifikasi saat implementasi

1. **URL media/webhook dari `localhost`**: gateway menolak loopback kecuali `allowPrivate` aktif. Pastikan cara uji lokal (jalankan gateway dengan `allowPrivate`, atau tunnel publik) sebelum menguji kirim media dan webhook.
2. **Batas panjang caption** di gateway tidak divalidasi; batas 1024 di aplikasi mengikuti WhatsApp. Konfirmasi sekali dengan gambar uji.
3. **Webhook `message.received` untuk media** (contoh hanya TEXT). Rencana: abaikan `type !== TEXT`, sama seperti perilaku WAHA sekarang.

## Testing

- Semua test HTTP gateway memakai `Http::fake()`.
- Test wajib: verifikasi HMAC (sah, salah, timestamp basi, token klinik salah), pesan masuk memetakan tenant dari token, `@lid` memakai `remoteJidAlt`, non-teks/non-DM diabaikan, status maju monoton, kunci idempotensi berubah setelah requeue, `SESSION_NOT_CONNECTED` menjeda campaign, media URL dibentuk dari disk `public`, pengingat merender parameter tanpa template Meta, provisioning melanjutkan dari langkah yang gagal tanpa mendaftar tenant baru.
- Suite PostgreSQL dijalankan sebelum rilis.

## Rollout

1. Migrasi skema + jeda semua broadcast aktif.
2. Gateway di-deploy dengan dua perubahan di atas; admin platform mengisi `base_url` di halaman pengaturan gateway.
3. Tiap klinik menekan "Hubungkan WhatsApp": tenant gateway dibuat otomatis, sesi dibuat, QR tampil, admin memindai sekali.
4. Webhook per klinik sudah terdaftar otomatis saat provisioning.
5. Fitur template Meta dihapus dari navigasi; broadcast lama tetap menyimpan `message` hasil render sehingga riwayatnya terbaca, dan dapat dilanjutkan setelah sesi tersambung.

## Perubahan lingkup 2026-11-04

Kotak masuk dan chatbot diputuskan tidak dibutuhkan lagi setelah Task 1-3 berjalan. Keduanya dihapus beserta seluruh kode, route, izin, lang key, dan UI-nya. Tabel `conversations`, `messages`, `chat_messages`, dan `chatbot_settings` tetap ada di database atas permintaan user; tidak ada migrasi yang men-drop tabel atau kolomnya.

Konsekuensi:

- Webhook masuk tidak ada. Verifikasi HMAC, pemetaan pesan masuk, dan pembaruan status pesan dibatalkan seluruhnya.
- Provisioning gateway hanya mendaftarkan tenant dan membuat API key; tidak membuat webhook.
- Broadcast tidak lagi mencatat pesan keluar ke kotak masuk.
- Perubahan gateway yang sudah dibuat (secret webhook sekali, `remoteJidAlt`, pemetaan `message.received`) dibiarkan dan tidak berefek selama tidak ada webhook terdaftar.
- Kolom `gateway_webhook_token` dan `gateway_webhook_secret` di-drop pada task pembersihan karena tidak dipakai.

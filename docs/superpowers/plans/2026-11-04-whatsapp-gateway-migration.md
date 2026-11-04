# Migrasi WhatsApp ke Gateway Internal — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengganti WAHA dan Meta Cloud API dengan gateway WhatsApp internal (`wacloud`), termasuk sesi per klinik, webhook bertanda tangan, kotak masuk, chatbot, broadcast, dan pengingat.

**Architecture:** Satu klien HTTP (`WhatsappGatewayClient`) dengan kredensial per klinik. Webhook per klinik memakai URL bertoken dan HMAC. Broadcast menyimpan teks lokal yang sudah dirender per penerima; gambar dikirim lewat URL disk `public`.

**Tech Stack:** Laravel 13 / PHP 8.3 (SQLite untuk test, PostgreSQL untuk rilis), TanStack Start + React 19, gateway NestJS + Prisma + worker Baileys.

**Spec:** `docs/superpowers/specs/2026-11-04-whatsapp-gateway-migration-design.md`

## Global Constraints

- Arsitektur backend: Controller → Service → Action. Service tidak menyentuh DB; Action tidak boleh inject Service. Validasi hanya lewat FormRequest. Setiap aksi yang mengubah data menulis activity log naratif (Indonesia, semi-formal).
- Nama file/folder English; teks UI Indonesia. Tanpa emoji di kode, komentar, commit, maupun output.
- PHP class maks 900 baris, method maks 100 baris. Komponen React maks 900 baris.
- Komit konvensional tanpa atribusi AI: `type(scope): deskripsi`.
- Jangan jalankan `bun run dev` / `bun run build` / `composer run dev`.
- Perintah verifikasi: `cd apps/api && php -l <file>`, `php artisan test --filter=<Nama>`, `cd apps/web && npx tsc --noEmit --incremental`.
- Suite PostgreSQL wajib sebelum rilis: `php artisan test -c phpunit.pgsql.xml` setelah `createdb sim_clinic_laravel_test`.
- Gateway: jangan mengarang endpoint/field di luar `docs` integrasi dan source di `/home/abdasis/orca/whatsapp-gateway`. Base URL dari `whatsapp_gateway_settings.base_url`, fallback `config('whatsapp_gateway.base_url')`.
- Gateway menerima `x-tenant-key` + `X-API-Key`; envelope `{success, data, error}`; `Idempotency-Key` 8–128 karakter `[A-Za-z0-9_-]`.
- Webhook gateway: `X-Webhook-Event`, `X-Webhook-Timestamp` (detik), `X-Webhook-Signature` = `sha256=HMAC(secret, timestamp.".".rawBody)`, tolak timestamp > 5 menit, bandingkan constant-time, balas 200 cepat.

## Review Focus

1. Webhook yang sama datang dua kali (retry gateway) tidak boleh menambah pesan, penghitung belum dibaca, atau notifikasi kedua.
2. Tanda tangan salah, timestamp basi, atau token klinik tidak dikenal tidak boleh memproses pesan dan tidak boleh membocorkan klinik mana pun.
3. Media yang gagal dikirim tidak meninggalkan berkas yatim; URL media harus benar-benar publik (bukan `localhost`) atau pengiriman ditolak dengan pesan jelas.
4. 429/503 di tengah blast tidak boleh menandai penerima sukses; campaign harus berhenti dengan alasan yang terbaca admin, lalu bisa dilanjutkan tanpa hasil cache idempotensi lama.
5. Nomor pengirim yang tidak bisa dipastikan (JID `@lid` tanpa alt) tidak boleh membuat percakapan dengan nomor palsu; sesi yang belum tersambung harus tampil sebagai keadaan jelas di layar, bukan galat mentah.

---

### Task 1: Gateway mengembalikan webhook secret sekali

**Files:**
- Modify: `/home/abdasis/orca/whatsapp-gateway/packages/contracts/src/webhooks.ts`
- Modify: `/home/abdasis/orca/whatsapp-gateway/apps/api/src/modules/webhooks/webhooks.service.ts`
- Test: `/home/abdasis/orca/whatsapp-gateway/apps/api/src/modules/webhooks/webhooks.service.spec.ts` (buat bila belum ada)

**Interfaces:**
- Produces: `CreateWebhookResponse extends WebhookDto { secret: string }`; `WebhooksService.create()` mengembalikan `secret` satu kali. `WebhookDto` (list/detail/update) tetap tanpa `secret`.

- [ ] **Step 1: Write the failing test**

```ts
it('mengembalikan secret sekali saat membuat webhook', async () => {
  // repository.create mengembalikan entity dengan secret 'rahasia-uji'
  const created = await service.create('user-1', {
    name: 'Sim Clinic', url: 'https://app.test/api/whatsapp/webhook/tok',
    events: ['message.received'],
  })
  expect(created.secret).toBe('rahasia-uji')
  const fetched = await service.getById('user-1', 'webhook-1')
  expect(fetched).not.toHaveProperty('secret')
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/abdasis/orca/whatsapp-gateway && pnpm --filter @whatsapp-gateway/api test webhooks.service`
Expected: FAIL — `secret` undefined / type error.

- [ ] **Step 3: Add `CreateWebhookResponse` and return secret from `create()`**

Di contracts: `export interface CreateWebhookResponse extends WebhookDto { secret: string }`. Di service: `create(...): Promise<CreateWebhookResponse>` dan `return { ...toDto(entity), secret: entity.secret }`. Jangan ubah `toDto` milik list/detail.

- [ ] **Step 4: Run test and typecheck**

Run: `pnpm --filter @whatsapp-gateway/api test webhooks.service && pnpm --filter @whatsapp-gateway/api typecheck`
Expected: PASS, tanpa error tipe.

- [ ] **Step 5: Commit**

```bash
cd /home/abdasis/orca/whatsapp-gateway && git add packages/contracts/src/webhooks.ts apps/api/src/modules/webhooks apps/api/src/modules/webhooks/webhooks.service.spec.ts && git commit -m "feat(webhooks): kembalikan secret sekali saat webhook dibuat"
```

---

### Task 2: Gateway meneruskan `remoteJidAlt` pada pesan masuk

**Files:**
- Modify: `/home/abdasis/orca/whatsapp-gateway/apps/worker/src/modules/whatsapp/engine/whatsapp-adapter.interface.ts`
- Modify: `/home/abdasis/orca/whatsapp-gateway/apps/worker/src/modules/whatsapp/engine/baileys.adapter.ts`
- Modify: `/home/abdasis/orca/whatsapp-gateway/apps/worker/src/modules/whatsapp/event-handlers/message.handler.ts`
- Test: `/home/abdasis/orca/whatsapp-gateway/apps/worker/src/modules/whatsapp/event-handlers/message.handler.spec.ts`

**Interfaces:**
- Produces: `IncomingMessage.remoteJidAlt: string | null`; payload `message.received` memuat `remoteJidAlt` (boleh `null`).
- Catatan: Baileys 6.17.16 (versi terpasang) tidak mengetik `remoteJidAlt` pada `IMessageKey`. Ambil lewat cast sempit `(raw.key as { remoteJidAlt?: string | null }).remoteJidAlt ?? null`. Bila runtime tidak mengirimnya, nilainya `null` — aplikasi akan membuang pesan `@lid` dan mencatat log. Menaikkan Baileys ke 7.x adalah pekerjaan terpisah, jangan dilakukan di task ini.

- [ ] **Step 1: Write the failing test**

```ts
it('meneruskan remoteJidAlt pada event message.received', async () => {
  // incoming: remoteJid '2399@lid', remoteJidAlt '628111@s.whatsapp.net'
  expect(published.data).toMatchObject({
    remoteJid: '2399@lid',
    remoteJidAlt: '628111@s.whatsapp.net',
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/abdasis/orca/whatsapp-gateway && pnpm --filter @whatsapp-gateway/worker test message.handler`
Expected: FAIL — `remoteJidAlt` undefined.

- [ ] **Step 3: Tambahkan field di adapter, tipe, dan publish payload**

`IncomingMessage` + `toIncomingMessage()` mengisi `remoteJidAlt`; `message.handler.ts` menyertakannya pada `publishReceived` (dan `message.create` `payload` bila perlu). Jangan mengubah dedupe.

- [ ] **Step 4: Run test and typecheck**

Run: `pnpm --filter @whatsapp-gateway/worker test message.handler && pnpm --filter @whatsapp-gateway/worker typecheck`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
cd /home/abdasis/orca/whatsapp-gateway && git add apps/worker/src/modules/whatsapp && git commit -m "feat(worker): teruskan remoteJidAlt pada pesan masuk"
```

---

### Task 3: Skema, model, dan enum untuk gateway

**Files:**
- Create: `apps/api/database/migrations/2026_11_04_090000_create_whatsapp_gateway_settings_table.php`
- Create: `apps/api/database/migrations/2026_11_04_090100_add_gateway_credentials_to_whatsapp_settings.php`
- Create: `apps/api/database/migrations/2026_11_04_090200_add_send_attempt_to_broadcast_recipients.php`
- Create: `apps/api/app/Models/WhatsappGatewaySetting.php`
- Modify: `apps/api/app/Models/WhatsappSetting.php`
- Modify: `apps/api/app/Enums/ConversationChannel.php`
- Test: `apps/api/tests/Feature/Whatsapp/GatewaySchemaTest.php`

**Interfaces:**
- Produces: `WhatsappSetting` fillable `gateway_tenant_key`, `gateway_api_key`, `gateway_webhook_token`, `gateway_webhook_secret`, `gateway_session_id`, `gateway_password`; casts `encrypted` untuk lima kolom kredensial; hidden untuk lima kolom itu; `isConfigured(): bool` = `filled(session) && filled(gateway_session_id)`; `provisioned(): bool` = tenant key + api key + webhook secret terisi.
- Produces: `ConversationChannel::Gateway = 'gateway'` (case lama dihapus di Task 12, bukan sekarang).
- Produces: `WhatsappGatewaySetting` model, fillable `base_url`.
- Produces: `broadcast_recipients.send_attempt` unsigned int default 0.

- [ ] **Step 1: Write the failing test**

```php
public function test_gateway_credentials_are_encrypted_and_hidden(): void
{
    $setting = WhatsappSetting::create([
        'tenant_id' => $this->tenant->id,
        'session' => 'klinik-uji',
        'gateway_tenant_key' => 'tenant-key-uji',
        'gateway_api_key' => 'api-key-uji',
        'gateway_webhook_secret' => 'secret-uji',
        'gateway_webhook_token' => 'token-uji',
        'gateway_session_id' => '11111111-1111-1111-1111-111111111111',
    ]);

    $raw = DB::table('whatsapp_settings')->where('id', $setting->id)->first();
    $this->assertNotSame('api-key-uji', $raw->gateway_api_key);
    $this->assertTrue($setting->provisioned());
    $this->assertTrue($setting->isConfigured());
    $this->assertArrayNotHasKey('gateway_api_key', $setting->toArray());
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd apps/api && php artisan test --filter=GatewaySchemaTest`
Expected: FAIL — kolom tidak ada / `provisioned()` tidak ada.

- [ ] **Step 3: Tulis migrasi dan model**

Tiga migrasi: tabel `whatsapp_gateway_settings` (`id`, `base_url`, timestamps); tambah enam kolom di `whatsapp_settings` (`gateway_webhook_token` unique); tambah `send_attempt` di `broadcast_recipients`. Tambah `Gateway = 'gateway'` di enum dan key `inbox.channel.gateway` di `lang/id/inbox.php`. Jangan drop kolom lama dan jangan ubah data `conversations.channel` di task ini.

- [ ] **Step 4: Run test**

Run: `php artisan test --filter=GatewaySchemaTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add apps/api/database/migrations apps/api/app/Models apps/api/app/Enums/ConversationChannel.php apps/api/lang/id/inbox.php apps/api/tests/Feature/Whatsapp/GatewaySchemaTest.php && git commit -m "feat(whatsapp): tambah skema dan model kredensial gateway"
```

---

### Task 4: Klien HTTP gateway

**Files:**
- Create: `apps/api/app/Support/WhatsappGatewayException.php`
- Create: `apps/api/app/Support/WhatsappGatewayClient.php`
- Create: `apps/api/config/whatsapp_gateway.php`
- Modify: `apps/api/app/Providers/AppServiceProvider.php`
- Test: `apps/api/tests/Feature/Whatsapp/GatewayClientTest.php`

**Interfaces:**
- Produces: `new WhatsappGatewayClient(string $baseUrl)`. Base URL dinormalkan: buang `/` di akhir dan `/api/v1` bila ada, lalu klien selalu menambahkan `/api/v1`. Binding container memakai `WhatsappGatewaySetting::query()->value('base_url') ?: config('whatsapp_gateway.base_url')`.
- Produces methods (semua `array` = isi `data`):
  `register(string $name, string $organization, string $email, string $password): array` → `{tenant_key, access_token, refresh_token}`;
  `login(string $email, string $password): array` (bentuk sama);
  `createApiKey(string $tenantKey, string $accessToken, string $name): string`;
  `createWebhook(string $tenantKey, string $apiKey, string $url, array $events): array` → `{id, secret}`;
  `sessionStatus(WhatsappSetting $s): array`, `listSessions(WhatsappSetting $s): array`, `createSession(WhatsappSetting $s, string $name, string $slug): array`, `connectSession(WhatsappSetting $s): array`, `qrCode(WhatsappSetting $s): array`, `pairingCode(WhatsappSetting $s, string $phone): array`, `restartSession(WhatsappSetting $s): void`, `disconnectSession(WhatsappSetting $s): void`, `logoutSession(WhatsappSetting $s): void`, `deleteSession(WhatsappSetting $s): void`;
  `sendText(WhatsappSetting $s, string $phone, string $text, string $idempotencyKey): ?string`, `sendImage(WhatsappSetting $s, string $phone, string $url, ?string $caption, string $idempotencyKey): ?string`, `sendDocument(WhatsappSetting $s, string $phone, string $url, ?string $filename, ?string $caption, string $idempotencyKey): ?string`, `sendAudio(WhatsappSetting $s, string $phone, string $url, string $idempotencyKey): ?string` (return `data.messageId`);
  `presence(WhatsappSetting $s, string $presence, ?string $phone = null): void` (tidak pernah melempar; log saat gagal).
- Produces: `WhatsappGatewayException` dengan `code: string`, `status: int`, `retryAfter: ?int`, `details: array`, `static fromResponse(Response $response): self`, `static network(Throwable $e): self`.
- Produces: `config/whatsapp_gateway.php` → `'base_url' => env('WHATSAPP_GATEWAY_BASE_URL', 'http://localhost:5173')`, `'account_domain' => env('WHATSAPP_GATEWAY_ACCOUNT_DOMAIN', 'gateway.local')`.

- [ ] **Step 1: Write the failing test**

```php
public function test_send_text_posts_headers_payload_and_returns_message_id(): void
{
    config(['whatsapp_gateway.base_url' => 'https://gateway.test']);
    Http::fake(['gateway.test/api/v1/messages/send' => Http::response([
        'success' => true, 'message' => 'OK',
        'data' => ['messageId' => '4d5e6f70-1a2b-4c3d-8e9f-0a1b2c3d4e5f', 'status' => 'QUEUED'],
    ], 202)]);

    $client = app(WhatsappGatewayClient::class);
    $id = $client->sendText($this->gatewaySetting(), '081234567890', 'Halo', 'inbox-uji-123');

    $this->assertSame('4d5e6f70-1a2b-4c3d-8e9f-0a1b2c3d4e5f', $id);
    Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/v1/messages/send'
        && $request->hasHeader('x-tenant-key', 'tenant-uji')
        && $request->hasHeader('X-API-Key', 'api-uji')
        && $request->hasHeader('Idempotency-Key', 'inbox-uji-123')
        && $request['sessionId'] === '11111111-1111-1111-1111-111111111111'
        && $request['to'] === '081234567890');
}

public function test_error_envelope_becomes_gateway_exception(): void
{
    Http::fake(['*' => Http::response([
        'success' => false, 'message' => 'Sesi belum terhubung.',
        'error' => ['code' => 'SESSION_NOT_CONNECTED', 'details' => []],
    ], 422)]);

    try {
        app(WhatsappGatewayClient::class)->sendText($this->gatewaySetting(), '0812', 'Halo', 'inbox-uji-123');
        $this->fail('Seharusnya melempar.');
    } catch (WhatsappGatewayException $e) {
        $this->assertSame('SESSION_NOT_CONNECTED', $e->code);
        $this->assertSame(422, $e->status);
    }
}

public function test_rate_limit_carries_retry_after(): void
{
    Http::fake(['*' => Http::response([
        'success' => false, 'message' => 'Kuota habis.',
        'error' => ['code' => 'RATE_LIMIT_EXCEEDED'],
    ], 429, ['Retry-After' => '17'])]);
    // assert retryAfter === 17
}
```

Helper test `gatewaySetting()` mengembalikan `WhatsappSetting` tersimpan dengan kredensial + `gateway_session_id` tetap.

- [ ] **Step 2: Run test to verify it fails**

Run: `cd apps/api && php artisan test --filter=GatewayClientTest`
Expected: FAIL — kelas belum ada.

- [ ] **Step 3: Implementasikan klien, exception, config, dan binding**

Bentuk request: `Http::withHeaders([...])->acceptJson()->post($base.'/api/v1'.$path, $body)`. `register`/`login` menormalkan envelope menjadi `{tenant_key: data.tenant.key ?? data.tenantKey, access_token: data.token.accessToken, refresh_token: data.token.refreshToken}`. `sessionStatus` memakai path `/sessions/{uuid}/status`; `qrCode` `/sessions/{uuid}/qr`; presence `/sessions/{uuid}/presence`. Respons non-2xx atau `success !== true` → exception. Timeout 15 detik, `->throw()` tidak dipakai supaya envelope bisa dibaca.

- [ ] **Step 4: Run test**

Run: `php artisan test --filter=GatewayClientTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add apps/api/app/Support/WhatsappGateway*.php apps/api/config/whatsapp_gateway.php apps/api/app/Providers/AppServiceProvider.php apps/api/tests/Feature/Whatsapp/GatewayClientTest.php && git commit -m "feat(whatsapp): tambah klien HTTP gateway internal"
```

---

### Task 5: Webhook gateway — verifikasi, pesan masuk, status

**Files:**
- Create: `apps/api/app/Support/WhatsappGatewayWebhook.php`
- Create: `apps/api/app/Http/Controllers/WhatsappGatewayWebhookController.php`
- Modify: `apps/api/routes/api.php` (tambah `Route::post('/whatsapp/webhook/{token}', ...)` di grup publik)
- Test: `apps/api/tests/Feature/Whatsapp/GatewayWebhookTest.php`

**Interfaces:**
- Produces: `WhatsappGatewayWebhook::signatureIsValid(string $secret, string $timestamp, string $rawBody, ?string $signature): bool` (tolak timestamp bukan angka atau selisih > 300 detik).
- Produces route `POST /api/whatsapp/webhook/{token}`; `token` = `whatsapp_settings.gateway_webhook_token` (cari dengan `withoutGlobalScopes()`).
- Consumes: `RecordInboundMessageAction`, `ProcessInboundMessageJob`, `UpdateMessageStatusAction` dengan `ConversationChannel::Gateway`.

- [ ] **Step 1: Write the failing tests**

```php
public function test_valid_signature_records_inbound_and_dispatches_job(): void
{
    Queue::fake();
    $this->postJson('/api/whatsapp/webhook/'.$this->token, [
        'messageId' => '0f8fad5b-d9cb-469f-a165-70867728950e',
        'sessionId' => 'klinik-uji',
        'remoteJid' => '6281234567890@s.whatsapp.net',
        'type' => 'TEXT',
        'content' => 'Halo dari pelanggan',
        'at' => '2026-11-04T10:00:00.000Z',
    ], $this->signedHeaders())->assertOk();

    $this->assertDatabaseHas('messages', ['provider_message_id' => '0f8fad5b-d9cb-469f-a165-70867728950e']);
    Queue::assertPushed(ProcessInboundMessageJob::class);
}

public function test_stale_timestamp_is_rejected(): void
{
    // header timestamp 10 menit lalu → 403, tidak ada pesan tercatat
}

public function test_lid_sender_uses_remote_jid_alt(): void
{
    // remoteJid '2399@lid', remoteJidAlt '6281234567890@s.whatsapp.net'
    // → conversations.contact_phone = '6281234567890'
}

public function test_lid_without_alt_is_dropped_without_conversation(): void
{
    // remoteJid '2399@lid' tanpa alt → 200, conversations kosong, Log::channel('chatbot') warning
}

public function test_group_message_is_ignored(): void
{
    // remoteJid '1203630@g.us' → 200, tidak ada conversations
}

public function test_status_event_advances_message(): void
{
    // pesan outbound provider_message_id sama; event message.read → status read
}

public function test_replayed_received_event_does_not_duplicate_message(): void
{
    // kirim payload yang sama dua kali → jumlah messages dan unread_count tidak berubah
}

public function test_unknown_token_returns_404(): void
{
    $this->postJson('/api/whatsapp/webhook/tidak-dikenal', [])->assertNotFound();
}
```

`signedHeaders()` membuat header `X-Webhook-Event`, `X-Webhook-Timestamp` = detik sekarang, dan `X-Webhook-Signature` dari HMAC secret setting.

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd apps/api && php artisan test --filter=GatewayWebhookTest`
Expected: FAIL — route 404.

- [ ] **Step 3: Implementasikan verifier dan controller**

Alur: cari setting by token → 404 bila tidak ada; verifikasi → 403 dan `Log::channel('chatbot')->warning`; baca `X-Webhook-Event`; `message.received` abaikan `type !== 'TEXT'` dan non-DM, pilih nomor `remoteJidAlt` bila `remoteJid` berakhir `@lid`, log warning lalu 200 bila nomor tidak bisa dipastikan; status: petakan `message.sent → Sent`, `delivered → Delivered`, `read → Read`, `failed → Failed` lewat `UpdateMessageStatusAction`. Selalu balas `['data' => ['status' => $reason], 'meta' => []]`.

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=GatewayWebhookTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add apps/api/app/Support/WhatsappGatewayWebhook.php apps/api/app/Http/Controllers/WhatsappGatewayWebhookController.php apps/api/routes/api.php apps/api/tests/Feature/Whatsapp/GatewayWebhookTest.php && git commit -m "feat(whatsapp): terima webhook gateway dengan verifikasi HMAC"
```

---

### Task 6: Provisioning tenant gateway per klinik

**Files:**
- Create: `apps/api/app/Services/WhatsappGatewayProvisioningService.php`
- Test: `apps/api/tests/Feature/Whatsapp/GatewayProvisioningTest.php`

**Interfaces:**
- Produces: `WhatsappGatewayProvisioningService::ensure(WhatsappSetting $setting): WhatsappSetting` — idempoten dan bisa dilanjutkan.
- Consumes: `WhatsappGatewayClient::register/login/createApiKey/createWebhook`.

- [ ] **Step 1: Write the failing tests**

```php
public function test_first_provisioning_registers_tenant_api_key_and_webhook(): void
{
    Http::fake([
        'gateway.test/api/v1/auth/register' => Http::response(['success' => true, 'data' => [
            'tenant' => ['id' => 't1', 'name' => 'Klinik', 'key' => 'tenant-uji'],
            'token' => ['accessToken' => 'access-uji', 'refreshToken' => 'refresh-uji'],
            'user' => [],
        ]], 201),
        'gateway.test/api/v1/api-keys' => Http::response(['success' => true, 'data' => ['id' => 'k1', 'key' => 'api-uji']], 201),
        'gateway.test/api/v1/webhooks' => Http::response(['success' => true, 'data' => ['id' => 'w1', 'secret' => 'secret-uji']], 201),
    ]);

    $setting = app(WhatsappGatewayProvisioningService::class)->ensure($this->setting());

    $this->assertSame('tenant-uji', $setting->gateway_tenant_key);
    $this->assertSame('api-uji', $setting->gateway_api_key);
    $this->assertSame('secret-uji', $setting->gateway_webhook_secret);
    $this->assertNull($setting->gateway_password);
    Http::assertSent(fn ($r) => str_ends_with($r['url'], '/api/whatsapp/webhook/'.$setting->gateway_webhook_token));
}

public function test_retry_after_failed_api_key_step_logs_in_instead_of_registering_again(): void
{
    // setting punya gateway_tenant_key + gateway_password, api_key null
    // → hanya /auth/login, /api-keys, /webhooks yang dipanggil
}

public function test_complete_credentials_do_not_call_the_gateway(): void
{
    // sudah lengkap → Http::assertNothingSent()
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd apps/api && php artisan test --filter=GatewayProvisioningTest`
Expected: FAIL — kelas belum ada.

- [ ] **Step 3: Implementasikan `ensure()`**

Urutan: buat `gateway_webhook_token` bila kosong (`Str::random(40)`); register bila tenant key kosong (email `tenant-{id}@{account_domain}`, password `Str::password(24)`, nama klinik dari relasi tenant; simpan key + password terenkripsi); bila api key kosong, login (`gateway_password`) untuk access token bila register tidak baru; buat API key dan simpan; buat webhook dengan event `message.received`, `message.sent`, `message.delivered`, `message.read`, `message.failed`; simpan secret webhook; kosongkan password. Setiap kegagalan: `Log::error` dengan konteks, biarkan exception naik.

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=GatewayProvisioningTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add apps/api/app/Services/WhatsappGatewayProvisioningService.php apps/api/tests/Feature/Whatsapp/GatewayProvisioningTest.php && git commit -m "feat(whatsapp): provisioning tenant gateway per klinik"
```

---

### Task 7: Layanan sesi gateway dan endpoint koneksi

**Files:**
- Create: `apps/api/app/Services/WhatsappGatewaySessionService.php`
- Create: `apps/api/app/Http/Requests/WhatsappPairingRequest.php`
- Modify: `apps/api/app/Http/Controllers/BroadcastController.php` (method `connection`, `prepareConnection`, tambah `pairing`, `restartConnection`, `disconnectConnection`, `logoutConnection`)
- Modify: `apps/api/routes/api.php`
- Modify: `apps/api/app/Http/Requests/WhatsappSessionRequest.php` (hapus field Meta, kunci slug bila sesi gateway ada)
- Test: `apps/api/tests/Feature/Whatsapp/GatewaySessionTest.php`

**Interfaces:**
- Produces: `WhatsappGatewaySessionService::state(): array` (`available`, `connected`, `qr`, `number`, `name`, `provisioned`, `error`), `prepare(): array`, `pairing(string $phone): array` (`pairing_code`, `expires_at`), `restart(): void`, `disconnect(): void`, `logout(): void`.
- Produces route: `POST /{tenant}/clinic/broadcasts/connection/pairing` body `{phone}`; `POST .../connection/restart`; `POST .../connection/disconnect`; `POST .../connection/logout`.

- [ ] **Step 1: Write the failing tests**

```php
public function test_state_reports_connected_session(): void
{
    Http::fake(['*' => Http::response(['success' => true, 'data' => [
        'sessionId' => 'klinik-uji', 'status' => 'CONNECTED',
        'phoneNumber' => '628111', 'lastSeenAt' => null,
    ]])]);

    $this->actingAsClinicUser();
    $this->getJson($this->tenantUrl('broadcasts/connection'))
        ->assertOk()
        ->assertJsonPath('data.connected', true)
        ->assertJsonPath('data.number', '628111');
}

public function test_prepare_creates_missing_session_then_returns_qr(): void
{
    // setting belum punya gateway_session_id
    // GET /sessions → items kosong; POST /sessions → id uuid; POST connect; GET status QR_REQUIRED; GET qr → data URI
    // assert data.qr diawali 'data:image/png;base64,'
}

public function test_prepare_adopts_existing_session_with_same_slug(): void
{
    // GET /sessions mengembalikan sessionId 'klinik-uji' → tidak memanggil POST /sessions
}

public function test_pairing_code_is_returned_to_the_admin(): void
{
    // POST pairing-code → 202 {pairingCode:'ABCD1234', expiresAt}
    // response data.pairing_code === 'ABCD1234'
}

public function test_slug_cannot_be_edited_once_gateway_session_exists(): void
{
    // PUT settings session 'nama-lain' → 422
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd apps/api && php artisan test --filter=GatewaySessionTest`
Expected: FAIL — route 404 / service belum ada.

- [ ] **Step 3: Implementasikan service dan endpoint**

`state()`: bila belum provisioned → `available=false, provisioned=false`. Panggil `sessionStatus`; petakan `CONNECTED` → connected; `QR_REQUIRED` → ambil `qrCode`; status lain → connected false dengan `qr=null`. `prepare()`: `ensure()` provisioning → adopsi/buat sesi → `connectSession` bila status bukan `CONNECTED` → `state()`. Jaga cooldown 30 detik dengan `Cache::add('gateway:prepare:{tenant}', true, 30)` seperti versi lama. `pairing()` memerlukan `gateway_session_id`. `WhatsappSessionRequest`: `session` wajib `regex:/^[a-z0-9-]{3,64}$/`, unique; tolak perubahan bila `gateway_session_id` terisi.

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=GatewaySessionTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add apps/api/app/Services/WhatsappGatewaySessionService.php apps/api/app/Http/Requests/WhatsappPairingRequest.php apps/api/app/Http/Requests/WhatsappSessionRequest.php apps/api/app/Http/Controllers/BroadcastController.php apps/api/routes/api.php apps/api/tests/Feature/Whatsapp/GatewaySessionTest.php && git commit -m "feat(whatsapp): kelola sesi gateway dari dialog koneksi"
```

---

### Task 8: Kirim kotak masuk lewat gateway

**Files:**
- Modify: `apps/api/app/Actions/Inbox/SendInboxMessageAction.php`
- Modify: `apps/api/app/Actions/Inbox/SendInboxMediaAction.php`
- Modify: `apps/api/app/Actions/Inbox/SendInboxContactAction.php`
- Delete: `apps/api/app/Actions/Inbox/SendInboxReactionAction.php`
- Modify: `apps/api/app/Services/InboxService.php`, `apps/api/routes/api.php` (hapus route reaksi)
- Modify: `apps/api/app/Actions/Inbox/*` (hapus trait `EnsuresReplyWindow`; file trait dihapus di Task 12)
- Test: `apps/api/tests/Feature/Inbox/InboxGatewaySendTest.php`

**Interfaces:**
- Consumes: `WhatsappGatewayClient` (Task 4).
- Produces: tidak ada perubahan signature publik `InboxService::send/sendMedia/sendContact`; `react()` dihapus; `reply_to_message_id` tidak lagi dikirim ke gateway.
- Media: URL dari `Storage::disk('public')->url($path)`; `image` → `sendImage`, `audio` → `sendAudio`, selain itu → `sendDocument`.

- [ ] **Step 1: Write the failing tests**

```php
public function test_text_reply_sends_to_gateway_and_stores_provider_id(): void
{
    Http::fake(['*' => Http::response(['success' => true, 'data' => [
        'messageId' => 'uuid-pesan', 'status' => 'QUEUED',
    ]], 202)]);

    $this->postJson($this->tenantUrl("inbox/conversations/{$conversation->id}/messages"), ['body' => 'Halo'])
        ->assertCreated();

    $this->assertDatabaseHas('messages', ['body' => 'Halo', 'provider_message_id' => 'uuid-pesan']);
}

public function test_image_reply_uses_public_url_and_cleans_file_on_failure(): void
{
    Storage::fake('public');
    config(['app.url' => 'https://app.test']);
    Http::fake(['*' => Http::response(['success' => false, 'error' => [
        'code' => 'MEDIA_URL_BLOCKED',
    ]], 422)]);

    $this->postJson(..., ['file' => UploadedFile::fake()->image('foto.jpg')])
        ->assertStatus(422);

    $this->assertEmpty(Storage::disk('public')->allFiles('inbox-media'));
}

public function test_media_payload_sends_public_url_not_local_path(): void
{
    Storage::fake('public');
    config(['app.url' => 'https://app.test']);
    Http::fake(['*' => Http::response(['success' => true, 'data' => [
        'messageId' => 'uuid-media', 'status' => 'QUEUED',
    ]], 202)]);

    $this->postJson(..., ['file' => UploadedFile::fake()->image('foto.jpg')])->assertCreated();

    Http::assertSent(fn ($r) => str_starts_with((string) $r['url'], 'https://app.test/storage/inbox-media/'));
}

public function test_audio_reply_uses_audio_endpoint(): void
{
    // Http::assertSent ke /messages/audio
}

public function test_reaction_route_no_longer_exists(): void
{
    $this->postJson(...'/reaction', ['emoji' => '👍'])->assertNotFound();
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd apps/api && php artisan test --filter=InboxGatewaySendTest`
Expected: FAIL.

- [ ] **Step 3: Ganti transport dan hapus reaksi/kutipan**

Teks: `sendText($setting, $phone, $body, 'inbox-'.Str::uuid())`. Kontak tetap teks `Nama: +62…`. Media: simpan → URL → endpoint sesuai tipe. Hapus `EnsuresReplyWindow` dari ketiga action (gateway tidak punya jendela 24 jam), hapus `SendInboxReactionAction`, method `react()` pada `InboxService`, dan route reaksi. `reply_to_message_id` tetap divalidasi untuk data lama tetapi tidak diteruskan ke gateway.

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=InboxGatewaySendTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add apps/api/app/Actions/Inbox apps/api/app/Services/InboxService.php apps/api/routes/api.php apps/api/tests/Feature/Inbox/InboxGatewaySendTest.php && git rm apps/api/app/Concerns/EnsuresReplyWindow.php && git commit -m "feat(inbox): kirim balasan lewat gateway internal"
```

---

### Task 9: Chatbot dan diagnostik memakai gateway

**Files:**
- Modify: `apps/api/app/Jobs/ProcessInboundMessageJob.php`
- Modify: `apps/api/app/Support/ChatbotDiagnostics.php`
- Modify: `apps/api/app/Console/Commands/*` yang masih menyebut Waha (cek `grep -rn "WahaClient" app`)
- Test: `apps/api/tests/Feature/Chatbot/ChatbotGatewayTest.php`, `apps/api/tests/Feature/Chatbot/ChatbotDiagnosticsTest.php`

**Interfaces:**
- Produces: job memakai `WhatsappGatewayClient`; typing = `presence($setting, 'composing', $phone)` lalu `presence($setting, 'paused', $phone)` di `finally`.
- Produces: `ChatbotDiagnostics` checks `chatbot_active`, `ai_configured`, `gateway_configured` (`provisioned()` + `base_url`), `session_connected` (status gateway), `webhook_configured` (`filled(gateway_webhook_secret)`).

- [ ] **Step 1: Write the failing test**

```php
public function test_chatbot_shows_typing_and_sends_reply_via_gateway(): void
{
    Http::fake([
        'gateway.test/api/v1/sessions/*/presence' => Http::response(['success' => true, 'data' => []], 202),
        'gateway.test/api/v1/messages/send' => Http::response(['success' => true, 'data' => [
            'messageId' => 'uuid-balasan', 'status' => 'QUEUED',
        ]], 202),
    ]);

    (new ProcessInboundMessageJob($this->tenant->id, '628123', 'Halo', ConversationChannel::Gateway))->handle();

    Http::assertSent(fn ($r) => str_contains($r->url(), '/presence') && $r['presence'] === 'composing');
    Http::assertSent(fn ($r) => str_contains($r->url(), '/presence') && $r['presence'] === 'paused');
    $this->assertDatabaseHas('messages', ['provider_message_id' => 'uuid-balasan']);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd apps/api && php artisan test --filter=ChatbotGatewayTest`
Expected: FAIL.

- [ ] **Step 3: Implementasikan transport dan diagnostik**

Hapus cabang `CloudApiClient`; `gateway()` mengembalikan klien + setting klinik yang sudah provisioned, atau `null` dengan log. `send()` memakai `sendText` dan menyimpan `provider_message_id`. `ChatbotDiagnostics::webhookConfigured` membaca secret gateway; `sessionConnected` memakai `sessionStatus`.

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=Chatbot`
Expected: PASS (perbarui test lama yang memalsukan WAHA di task ini juga).

- [ ] **Step 5: Commit**

```bash
git add apps/api/app/Jobs/ProcessInboundMessageJob.php apps/api/app/Support/ChatbotDiagnostics.php apps/api/app/Console apps/api/tests/Feature/Chatbot && git commit -m "feat(chatbot): balas lewat gateway dan perbarui diagnostik"
```

---

### Task 10: Broadcast tanpa template Meta

**Files:**
- Modify: `apps/api/app/Jobs/SendBroadcastRecipientJob.php`
- Modify: `apps/api/app/Services/BroadcastService.php`
- Modify: `apps/api/app/Actions/Broadcast/{CreateBroadcastAction,UpdateBroadcastAction,RequeueFailedRecipientsAction,BuildBroadcastRecipientsAction}.php`
- Modify: `apps/api/app/Http/Controllers/BroadcastController.php`
- Modify: `apps/api/app/Http/Requests/{BroadcastRequest,BroadcastUpdateRequest,BroadcastTestRequest}.php`
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/Broadcast/BroadcastGatewaySendTest.php`

**Interfaces:**
- Produces: payload broadcast `template = {body: string, params: string[]}`; tidak ada `name`, `language`, `header_type`.
- Produces: kunci idempotensi `broadcast-{recipientId}-{sendAttempt}`; `RequeueFailedRecipientsAction` menaikkan `send_attempt` setiap requeue.
- Produces: `sendTest(string $phone, array $template)` mengirim teks hasil render contoh parameter lewat gateway; `meta.cloud_api_ready` diganti `meta.gateway_ready` = `setting->provisioned() && app(WhatsappGatewaySessionService::class)->state()['connected']`.
- Menghapus: endpoint `broadcasts/templates`, `broadcasts/templates/manage`, `BroadcastsTemplateService` bila tak terpakai.
- Validasi: `template.body` wajib; `template.params.*` ∈ `TemplateVariables::KEYS`; jumlah placeholder = jumlah params; bila ada `image`, `template.body` maks 1024 karakter.

- [ ] **Step 1: Write the failing tests**

```php
public function test_job_sends_rendered_text_with_attempt_scoped_key(): void
{
    Http::fake(['*' => Http::response(['success' => true, 'data' => [
        'messageId' => 'uuid-blast', 'status' => 'QUEUED',
    ]], 202)]);

    (new SendBroadcastRecipientJob($recipient->id, $this->tenant->id))->handle();

    Http::assertSent(fn ($r) => $r->url() === 'https://gateway.test/api/v1/messages/send'
        && $r['text'] === 'Halo Budi'
        && $r->hasHeader('Idempotency-Key', 'broadcast-'.$recipient->id.'-0'));
}

public function test_image_broadcast_uses_image_endpoint_with_caption(): void
{
    // broadcast image_path terisi → /messages/image, caption = recipient->message
}

public function test_requeue_increments_send_attempt_so_idempotency_key_changes(): void
{
    // recipient failed attempt 0 → requeue → send_attempt 1
    // job berikutnya mengirim dengan key broadcast-{id}-1
}

public function test_session_not_connected_pauses_campaign(): void
{
    Http::fake(['*' => Http::response(['success' => false, 'error' => [
        'code' => 'SESSION_NOT_CONNECTED',
    ]], 422)]);

    (new SendBroadcastRecipientJob($recipient->id, $this->tenant->id))->handle();

    $this->assertSame(BroadcastStatus::Paused, $broadcast->fresh()->status);
    $this->assertSame(BroadcastRecipientStatus::Pending, $recipient->fresh()->status);
}

public function test_template_endpoints_are_gone(): void
{
    $this->actingAsClinicUser();
    $this->getJson($this->tenantUrl('broadcasts/templates'))->assertNotFound();
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd apps/api && php artisan test --filter=BroadcastGatewaySendTest`
Expected: FAIL.

- [ ] **Step 3: Implementasikan pengiriman dan bentuk payload baru**

Job: ambil setting klinik; `SESSION_NOT_CONNECTED`/`SESSION_NOT_FOUND` → `PauseStalledBroadcastAction` + recipient tetap `Pending` + log; `RATE_LIMIT_EXCEEDED` → `$this->release($e->retryAfter ?? 60)`; sukses → recipient `Sent` + `RecordOutboundMessageAction(Gateway, messageId)`. `sendTest` memakai klien langsung. Perbarui FormRequest dan controller (hapus empat method template). `CreateBroadcastAction`/`UpdateBroadcastAction` menyimpan `{body, params}` dan merender `message` seperti sekarang.

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=Broadcast`
Expected: PASS setelah test broadcast lama diperbarui (fake HTTP, bukan `CloudApiClient`).

- [ ] **Step 5: Commit**

```bash
git add apps/api/app/Jobs/SendBroadcastRecipientJob.php apps/api/app/Services/BroadcastService.php apps/api/app/Actions/Broadcast apps/api/app/Http apps/api/routes/api.php apps/api/tests/Feature/Broadcast && git commit -m "feat(broadcast): kirim teks lokal lewat gateway tanpa template Meta"
```

---

### Task 11: Pengingat dan konfirmasi booking

**Files:**
- Modify: `apps/api/app/Support/ReminderEngine.php`
- Modify: `apps/api/app/Support/AutoReminderEngine.php`
- Modify: `apps/api/app/Jobs/{SendBookingConfirmationJob,SendBookingReminderJob}.php`
- Modify: `apps/api/app/Console/Commands/*` bila masih menyebut klien lama
- Test: `apps/api/tests/Feature/Broadcast/ReminderGatewayTest.php`

**Interfaces:**
- Produces: body pengingat = `MessageTemplate.body` klinik (`ReminderRule->template` / `BroadcastReminderSetting->template`) atau template bawaan, dirender `TemplateVariables::render`. Tidak ada `reminder_template`.
- Consumes: `WhatsappGatewayClient::sendText`.

- [ ] **Step 1: Write the failing test**

```php
public function test_reminder_rows_render_clinic_template_without_meta_template(): void
{
    // MessageTemplate klinik body 'Halo {nama}, jadwal {tanggal} di {klinik}'
    // → broadcast_recipients.message berisi nama pasien terisi, tanpa panggilan Cloud API
}

public function test_booking_confirmation_is_sent_via_gateway(): void
{
    Http::fake(['*' => Http::response(['success' => true, 'data' => [
        'messageId' => 'uuid-booking', 'status' => 'QUEUED',
    ]], 202)]);
    // jalankan job → Http::assertSent ke /messages/send
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd apps/api && php artisan test --filter=ReminderGatewayTest`
Expected: FAIL.

- [ ] **Step 3: Ganti transport dan sumber template**

Hapus cabang `CloudApiClient`/`reminder_template`; pertahankan `TemplateVariables::render` untuk isi pesan. Booking job memakai `sendText` dengan kunci `job-{class}-{id}`.

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=Reminder`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add apps/api/app/Support/ReminderEngine.php apps/api/app/Support/AutoReminderEngine.php apps/api/app/Jobs apps/api/app/Console apps/api/tests/Feature/Broadcast/ReminderGatewayTest.php && git commit -m "feat(reminder): pakai template lokal dan gateway internal"
```

---

### Task 12: Hapus jalur lama dan rapikan sisa

**Files:**
- Delete: `apps/api/app/Support/{WahaClient,WahaException,WahaSessionState,CloudApiClient,CloudApiException}.php`
- Delete: `apps/api/app/Models/WahaSetting.php`
- Delete: `apps/api/app/Http/Controllers/{MetaWhatsappWebhookController,CentralWahaSettingController}.php`
- Delete: `apps/api/app/Console/Commands/SetupChatbotWebhookCommand.php`
- Delete: `apps/api/config/{waha,meta_whatsapp}.php`
- Delete: `apps/api/app/Services/{WhatsappConnectionService,WhatsappTemplateService}.php`
- Delete: `apps/api/app/Concerns/EnsuresReplyWindow.php`
- Create: `apps/api/app/Http/Controllers/CentralWhatsappGatewaySettingController.php`, `apps/api/app/Http/Requests/WhatsappGatewaySettingRequest.php`
- Create: `apps/api/database/migrations/2026_11_04_090300_drop_legacy_whatsapp_columns.php`
- Modify: `apps/api/app/Enums/ConversationChannel.php` (hapus `Waha`/`CloudApi`), `apps/api/app/Providers/AppServiceProvider.php`, `apps/api/routes/api.php`, `apps/api/lang/id/*`, `.env.example`
- Test: `apps/api/tests/Feature/Whatsapp/LegacyRemovalTest.php`

**Interfaces:**
- Produces: `GET/PUT /central/whatsapp-gateway` dengan `{base_url}` (platform admin).
- Produces: migrasi drop `waha_settings`, `whatsapp_settings.{phone_number_id,waba_id,reminder_template}`, `broadcast_recipients.template_values`; `UPDATE conversations SET channel='gateway'`; jeda semua broadcast `sending` (`status='paused'`, `paused_reason='Migrasi ke gateway WhatsApp.')`.
- Produces: `config/whatsapp_gateway.php` satu-satunya konfigurasi gateway.

- [ ] **Step 1: Write the failing test**

```php
public function test_legacy_clients_and_routes_are_gone(): void
{
    $this->assertFileDoesNotExist(app_path('Support/WahaClient.php'));
    $this->assertFileDoesNotExist(app_path('Support/CloudApiClient.php'));
    $this->getJson('/api/whatsapp/meta/webhook')->assertNotFound();
    $this->actingAsPlatformAdmin();
    $this->putJson('/api/central/whatsapp-gateway', ['base_url' => 'https://gateway.test'])
        ->assertOk()
        ->assertJsonPath('data.base_url', 'https://gateway.test');
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd apps/api && php artisan test --filter=LegacyRemovalTest`
Expected: FAIL.

- [ ] **Step 3: Hapus file, route, kolom, dan config lama**

Hapus file di daftar; ganti route central; hapus route `whatsapp/meta/webhook`, `whatsapp/webhook/{token}` WAHA, `broadcasts/templates*`; perbarui `AppServiceProvider` (hapus binding klien lama, tambah binding `WhatsappGatewayClient` dan singleton base URL); hapus env `WAHA_*`, `META_WHATSAPP_*`; tambah `WHATSAPP_GATEWAY_BASE_URL`, `WHATSAPP_GATEWAY_ACCOUNT_DOMAIN`. Setelah itu `grep -rn "Waha\|CloudApi\|Cloud API" app routes config tests` harus kosong kecuali teks migrasi.

- [ ] **Step 4: Run full suite**

Run: `cd apps/api && vendor/bin/pint && php artisan test`
Expected: PASS. Perbaiki test lama yang masih memalsukan klien lama.

- [ ] **Step 5: Commit**

```bash
git add -A apps/api && git commit -m "refactor(whatsapp): hapus WAHA dan Cloud API Meta"
```

---

### Task 13: Frontend — dialog koneksi, pengaturan, halaman central

**Files:**
- Modify: `apps/web/src/routes/$tenant/clinic/broadcasts/components/whatsapp-tools.tsx`
- Modify: `apps/web/src/routes/$tenant/clinic/broadcasts/components/broadcast-settings-dialog.tsx`
- Modify: `apps/web/src/routes/central/route.tsx`
- Delete: `apps/web/src/routes/central/waha/index.tsx`
- Create: `apps/web/src/routes/central/whatsapp-gateway/index.tsx`
- Modify: `apps/web/src/routeTree.gen.ts`: jangan disentuh manual — jalankan `bun run generate-routes`
- Test: `apps/web/src/routes/$tenant/clinic/broadcasts/components/connection-dialog.test.tsx`

**Interfaces:**
- `ConnectionState` baru: `{ available: boolean; provisioned: boolean; connected: boolean; qr: string | null; number: string | null; name: string | null; error: boolean }`.
- `POST /broadcasts/connection/pairing` body `{ phone }` → `{ pairing_code, expires_at }`.
- Settings payload hanya `{ session, daily_message_limit }`.

- [ ] **Step 1: Write/patch failing test**

```tsx
it('menampilkan QR gateway sebagai gambar dan mengirim kode pairing', async () => {
  // state: { available: true, provisioned: true, connected: false, qr: 'data:image/png;base64,AAA' }
  expect(await screen.findByAltText('QR WhatsApp')).toHaveAttribute('src', 'data:image/png;base64,AAA')

  await user.type(screen.getByLabelText('Nomor WhatsApp'), '081234567890')
  await user.click(screen.getByRole('button', { name: 'Minta kode pairing' }))
  expect(await screen.findByText('ABCD1234')).toBeInTheDocument()
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd apps/web && bun run test connection-dialog`
Expected: FAIL.

- [ ] **Step 3: Implementasikan UI**

Dialog koneksi: tampilkan `qr` sebagai `<img src alt="QR WhatsApp">` dengan border subtle; form pairing (input nomor + tombol, tooltip + `<kbd>`), tampilkan kode + masa berlaku; tombol sambungkan ulang memanggil `prepare`. Pengaturan: hapus `phone_number_id`, `waba_id`, `reminder_template`; slug read-only bila `provisioned`. Halaman central baru: satu input `base_url` + tombol simpan, breadcrumb "Pengaturan / Gateway WhatsApp"; hapus menu WAHA dan file lama. Ikuti gaya visual yang sudah ada (Linear-like, border tipis, tooltip wajib, teks Indonesia).

- [ ] **Step 4: Run test and typecheck**

Run: `cd apps/web && bun run test connection-dialog && npx tsc --noEmit --incremental`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add apps/web/src/routes/\$tenant/clinic/broadcasts/components/whatsapp-tools.tsx apps/web/src/routes/\$tenant/clinic/broadcasts/components/broadcast-settings-dialog.tsx apps/web/src/routes/central apps/web/src/routeTree.gen.ts apps/web/src/routes/\$tenant/clinic/broadcasts/components/connection-dialog.test.tsx && git commit -m "feat(broadcast): dialog koneksi gateway dan pengaturan central"
```

---

### Task 14: Frontend — form broadcast teks lokal

**Files:**
- Modify: `apps/web/src/routes/$tenant/clinic/broadcasts/components/broadcast-form.tsx`
- Modify: `apps/web/src/routes/$tenant/clinic/broadcasts/components/broadcast-edit-form.tsx`
- Modify: `apps/web/src/routes/$tenant/clinic/broadcasts/new.tsx`, `$id_.edit.tsx`
- Modify: `apps/web/src/routes/$tenant/clinic/broadcasts/components/template-preview.tsx`
- Delete: `apps/web/src/routes/$tenant/clinic/broadcasts/components/{template-picker.tsx,template-form.tsx,template-guide.tsx,template-image-field.tsx,whatsapp-template-manager.tsx,whatsapp-template-row.tsx}`
- Delete: `apps/web/src/routes/$tenant/clinic/broadcasts/templates/new.tsx`
- Modify: `apps/web/src/routes/$tenant/clinic/broadcasts/components/broadcast-types.ts`
- Test: `apps/web/src/routes/$tenant/clinic/broadcasts/components/broadcast-form.test.tsx` (sesuaikan test yang ada)

**Interfaces:**
- `BroadcastTemplate` = `{ body: string; params: string[] }`.
- Form: textarea body (maks 1024 karakter bila gambar dipilih, 4000 tanpa gambar), pemilih parameter (`TemplateVariables::KEYS` yang sudah dipakai UI), field gambar yang sudah ada.
- Payload multipart: `template[body]`, `template[params][n]`, `image`, sama untuk edit.

- [ ] **Step 1: Patch failing test**

```tsx
it('mengirim body dan parameter tanpa nama/language template', async () => {
  // isi body 'Halo {{1}}', pilih parameter 'nama', submit
  // expect fetch body fields: template.body, template.params[0] = 'nama'
  // expect tidak ada template.name / template.language
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd apps/web && bun run test broadcast-form`
Expected: FAIL.

- [ ] **Step 3: Ganti picker template dengan editor body + parameter**

Hapus permintaan `/broadcasts/templates`. Pertahankan pemilihan audiens dan unggah gambar. Pratinjau memakai `template-preview.tsx` yang dirender dari body + contoh nilai parameter (tidak lagi memanggil Meta). Hapus halaman pengelolaan template dari navigasi dan route.

- [ ] **Step 4: Run test and typecheck**

Run: `cd apps/web && bun run test broadcast && npx tsc --noEmit --incremental`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A apps/web/src/routes/\$tenant/clinic/broadcasts && git commit -m "feat(broadcast): susun pesan teks lokal tanpa template Meta"
```

---

### Task 15: Frontend — hapus reaksi dan kutipan di kotak masuk

**Files:**
- Delete: `apps/web/src/routes/$tenant/clinic/inbox/hooks/use-send-reaction.ts`
- Modify: `apps/web/src/routes/$tenant/clinic/inbox/components/message-context-menu.tsx`
- Modify: `apps/web/src/routes/$tenant/clinic/inbox/index.tsx`, `hooks/use-inbox-thread.ts`, `inbox-thread.ts`, `types.ts`
- Test: `apps/web/src/routes/$tenant/clinic/inbox/inbox-thread.test.ts`, test komponen konteks menu bila ada

**Interfaces:**
- `use-inbox-thread.ts` mutation hanya `{ body }`; optimistic message tanpa `reply_to`.
- `message-context-menu.tsx` hanya aksi Salin, Info, Hapus-kutipan-bila-masih-dipakai; tidak memanggil endpoint reaksi.

- [ ] **Step 1: Patch failing test**

```ts
it('tidak lagi mengirim reaction atau reply_to_message_id', async () => {
  // mutation { body: 'Halo' } → body request hanya { body: 'Halo' }
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd apps/web && bun run test inbox-thread`
Expected: FAIL.

- [ ] **Step 3: Hapus alur reaksi dan kutipan yang tidak didukung gateway**

Buang `replyTarget` dari composer dan `reply_to` dari payload; pertahankan tampilan `reply_to` pada pesan lama. Hapus menu reaksi beserta hook-nya.

- [ ] **Step 4: Run test and typecheck**

Run: `cd apps/web && bun run test inbox && npx tsc --noEmit --incremental`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A apps/web/src/routes/\$tenant/clinic/inbox && git commit -m "refactor(inbox): hapus reaksi dan kutipan yang tidak didukung gateway"
```

---

### Task 16: Verifikasi akhir

**Files:** tidak ada file baru.

- [ ] **Step 1: Backend statis dan suite penuh**

Run: `cd apps/api && vendor/bin/pint && php artisan test`
Expected: PASS tanpa warning baru.

- [ ] **Step 2: Typecheck frontend**

Run: `cd apps/web && npx tsc --noEmit --incremental`
Expected: PASS.

- [ ] **Step 3: Suite PostgreSQL**

Run: `docker compose exec db createdb -U postgres sim_clinic_laravel_test` (sekali saja), lalu `cd apps/api && php artisan test -c phpunit.pgsql.xml`
Expected: PASS.

- [ ] **Step 4: Uji manual sekali dengan gateway hidup**

Nyalakan gateway + aplikasi sendiri (jangan lewat agen), lalu: buka dialog koneksi → sambungkan → pindai QR → kirim teks dari ponsel → balas dari kotak masuk → kirim media → jalankan broadcast uji satu penerima. Catat bila gateway menolak URL media lokal (`MEDIA_URL_BLOCKED`) — itu perilaku yang diharapkan; gunakan tunnel publik atau jalankan gateway dengan `allowPrivate`.

- [ ] **Step 5: Commit sisa perbaikan bila ada**

```bash
git status --short  # pastikan hanya perubahan yang disengaja
git add -A && git commit -m "chore(whatsapp): rapikan sisa migrasi gateway"
```

---

### Task 18: Lengkapi method klien untuk inbox dan chatbot

Dijalankan setelah Task 7, sebelum Task 5. Task 4 selesai dengan method minimal; inbox dan chatbot memerlukan empat method tambahan.

**Files:**
- Modify: `apps/api/app/Support/WhatsappGatewayClient.php`
- Test: `apps/api/tests/Feature/Whatsapp/GatewayClientTest.php`

**Interfaces:**
- `createWebhook(string $tenantKey, string $apiKey, string $url, array $events): array` → `{id, secret}`; `POST /webhooks` body `{name, url, events}`.
- `presence(WhatsappSetting $setting, string $presence, ?string $phone = null): void`; `POST /sessions/{uuid}/presence` body `{presence}` + `to` bila nomor diisi. Tidak pernah melempar; gagal dicatat `Log::warning`.
- `sendDocument(WhatsappSetting $setting, string $phone, string $url, ?string $filename, ?string $caption, string $idempotencyKey): ?string`; `POST /messages/document`.
- `sendAudio(WhatsappSetting $setting, string $phone, string $url, string $voiceNote, string $idempotencyKey): ?string`; `POST /messages/audio`.

- [ ] **Step 1: Write the failing tests**

```php
public function test_create_webhook_returns_id_and_secret(): void
{
    // POST /webhooks {name,url,events} + x-tenant-key + X-API-Key
    // data {id:'w1', secret:'secret-uji'} → ['id'=>'w1','secret'=>'secret-uji']
}

public function test_presence_posts_composing_with_target_phone(): void
{
    // POST /sessions/{uuid}/presence {presence:'composing', to:'628123'}
}

public function test_presence_failure_is_logged_and_swallowed(): void
{
    // 422 → tidak melempar; Log::warning terpanggil
}

public function test_document_and_audio_use_their_endpoints(): void
{
    // /messages/document {url, filename, caption}; /messages/audio {url, voiceNote}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd apps/api && php artisan test --filter=GatewayClientTest`
Expected: FAIL — method belum ada.

- [ ] **Step 3: Implementasikan empat method**

Ikuti pola `sendImage`/`request` yang sudah ada; `presence` memakai try/catch sendiri dan tidak memakai jalur throw. `createWebhook` mengirim header klinik dan mengembalikan `data`.

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=GatewayClientTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add apps/api/app/Support/WhatsappGatewayClient.php apps/api/tests/Feature/Whatsapp/GatewayClientTest.php && git commit -m "feat(whatsapp): lengkapi klien untuk webhook, presence, dan media"
```

---

### Task 19: Provisioning membuat webhook gateway

Dijalankan tepat setelah Task 18. Kotak masuk dan chatbot membutuhkan webhook `message.received` dan status pesan.

**Files:**
- Modify: `apps/api/app/Services/WhatsappGatewayProvisioningService.php`
- Modify: `apps/api/app/Models/WhatsappSetting.php` (`provisioned()` kembali mensyaratkan webhook secret)
- Create: `apps/api/app/Actions/Whatsapp/StoreGatewayWebhookAction.php`
- Test: `apps/api/tests/Feature/Whatsapp/GatewayProvisioningTest.php`

**Interfaces:**
- `provisioned()` = `filled(gateway_tenant_key) && filled(gateway_api_key) && filled(gateway_webhook_secret)`.
- `ensure()` setelah API key: buat `gateway_webhook_token` (`Str::random(40)`) bila kosong; `POST /webhooks` ke `rtrim(config('app.url'), '/').'/api/whatsapp/webhook/'.$token` dengan event `message.received`, `message.sent`, `message.delivered`, `message.read`, `message.failed`; simpan `gateway_webhook_secret` lewat `StoreGatewayWebhookAction`.
- Bila tenant key + api key sudah ada tetapi secret kosong, hanya langkah webhook yang dijalankan (tidak ada register/login ulang).

- [ ] **Step 1: Write the failing tests**

```php
public function test_webhook_is_created_after_api_key_with_message_events(): void
{
    // fake register + api-keys + webhooks
    // assert gateway_webhook_secret tersimpan, URL berakhiran '/api/whatsapp/webhook/'.$token,
    // events memuat message.received..message.failed
}

public function test_missing_webhook_secret_resumes_without_registering_again(): void
{
    // tenant key + api key terisi, secret kosong → hanya POST /webhooks, provisioned() true setelahnya
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd apps/api && php artisan test --filter=GatewayProvisioningTest`
Expected: FAIL.

- [ ] **Step 3: Implementasikan langkah webhook**

Tambahkan `createWebhook` ke alur `ensure()` dan Action penyimpanan; jangan menaruh nilai secret di log.

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=GatewayProvisioningTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add apps/api/app/Services/WhatsappGatewayProvisioningService.php apps/api/app/Models/WhatsappSetting.php apps/api/app/Actions/Whatsapp/StoreGatewayWebhookAction.php apps/api/tests/Feature/Whatsapp/GatewayProvisioningTest.php && git commit -m "feat(whatsapp): provisioning webhook pesan masuk"
```

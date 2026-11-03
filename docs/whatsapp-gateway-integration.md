# Titik sambung WhatsApp di meba

Peta jalur WhatsApp di `apps/api` sebagaimana adanya pada `7d4c78e`, disusun untuk
menjawab satu pertanyaan: **apa saja yang harus berubah kalau penyedia WhatsApp
diganti gateway sendiri.**

Dokumen ini hanya membaca kode meba. Kontrak gateway-nya belum dibaca sama
sekali, jadi bagian "pertanyaan yang belum terjawab" di bawah sengaja dibiarkan
terbuka — jangan diisi dengan dugaan.

## Keadaan sekarang: dua penyedia berdampingan, tanpa kontrak bersama

| | `WahaClient` | `CloudApiClient` |
|---|---|---|
| jenis | WAHA self-hosted, sesi QR | Meta Cloud API resmi |
| kredensial | `WahaSetting` (central: `base_url`, `api_key`) + `WhatsappSetting.session` (per klinik) | `config('meta_whatsapp.access_token')` (central) + `WhatsappSetting.phone_number_id` / `waba_id` (per klinik) |
| dibuat di | `AppServiceProvider` (`bind`, boleh `null`) | `AppServiceProvider` (`bind`, boleh `null`) |
| webhook masuk | `POST /whatsapp/webhook/{token}` → `InboundMessageController` | `GET|POST /whatsapp/meta/webhook` → `MetaWhatsappWebhookController` |
| bukti keaslian webhook | token di ruas URL (`config('waha.webhook_token')`, `hash_equals`) | tanda tangan HMAC pada isi permintaan |

Keduanya di-`bind` sebagai **nullable**: belum dikonfigurasi berarti `app(X::class)`
mengembalikan `null`, dan tiap pemanggil memeriksanya dengan `instanceof` lalu
menolak dengan pesan yang bisa dibaca orang. Pola itu bagus dan harus
dipertahankan penyedia ketiga.

Yang **tidak** ada: satu pun interface. Tidak ada `app/Contracts/`. Pilihan
penyedia dilakukan dengan `if` yang ditulis ulang di tiap pemanggil.

## Lima titik percabangan yang sama, ditulis lima kali

Kanal percakapan — bukan pilihan bebas — yang menentukan transportnya: pesan
yang masuk lewat Cloud API hanya bisa dibalas lewat Cloud API. Aturannya benar,
tapi penegakannya tersebar:

```
app/Actions/Inbox/SendInboxMessageAction.php:65
app/Actions/Inbox/SendInboxMediaAction.php:95
app/Actions/Inbox/SendInboxContactAction.php:59
app/Actions/Inbox/SendInboxReactionAction.php:81
app/Concerns/EnsuresReplyWindow.php:19
```

Masing-masing berbentuk:

```php
if ($conversation->channel === ConversationChannel::CloudApi) {
    $client = app(CloudApiClient::class);
    if (! $client instanceof CloudApiClient) { /* tolak */ }
    return $client->sendText(...);
}
$client = app(WahaClient::class);
if (! $client instanceof WahaClient) { /* tolak */ }
$client->send(...);
```

**Menambah penyedia ketiga berarti menyentuh kelima tempat itu**, dan tiap
tempat punya peluang sendiri untuk lupa satu cabang. Ini yang perlu diputuskan
lebih dulu, sebelum satu baris kode gateway ditulis.

## Jalur keluar yang masih terikat satu penyedia

| jalur | berkas | penyedia | catatan |
|---|---|---|---|
| balasan kotak masuk | `app/Actions/Inbox/Send*Action.php` | keduanya, per kanal | sudah bercabang |
| broadcast kampanye | `app/Jobs/SendBroadcastRecipientJob.php` | **Cloud API saja** | lewat template; WAHA sudah ditinggalkan di sini |
| konfirmasi booking | `app/Jobs/SendBookingConfirmationJob.php:86` | **WAHA saja** | `app(WahaClient::class)` langsung |
| pengingat booking | `app/Jobs/SendBookingReminderJob.php:132` | **WAHA saja** | idem |
| pengingat treatment | `app/Console/Commands/SendTreatmentReminders.php` | Cloud API | |
| pengingat otomatis | `app/Console/Commands/SendAutoReminders.php` | Cloud API | |
| chatbot | `app/Support/DeepSeekClient.php`, `app/Services/IdleChatClosingService.php` | WAHA | termasuk indikator "sedang menulis" |

Dua jalur pengingat booking adalah yang paling terasa kalau WAHA dimatikan:
keduanya memanggil `WahaClient` tanpa cabang apa pun, jadi klinik yang pindah
ke gateway baru akan berhenti mengirim konfirmasi dan pengingat **tanpa pesan
galat di layar mana pun** — hanya `Log::error` di server.

## Kemampuan yang tidak sama antar penyedia

Gateway baru harus menyediakan yang mana, tergantung jalur mana yang dipindahkan:

**Hanya ada di `WahaClient`** (dipakai chatbot dan halaman koneksi):

- `startTyping()` / `stopTyping()` — indikator sedang menulis
- `sessionStatus()`, `sessionState()`, `isConnected()` — keadaan sesi
- `qrCode()` — QR untuk memindai
- `startSession()`, `createSession()`, `restartSession()`, `logout()`
- `setWebhook(url)` — mendaftarkan alamat webhook dari aplikasi
- penyaringan sumber: status/story, grup, saluran, daftar siaran dibuang di
  `IGNORED_SOURCES` — gateway baru harus melakukan hal yang sama atau inbox
  akan kebanjiran pesan grup

**Hanya ada di `CloudApiClient`**:

- `sendTemplate()`, `approvedTemplates()`, `allTemplates()`, `createTemplate()`,
  `deleteTemplate()` — seluruh dunia template Meta
- `uploadMedia()` — media diunggah lebih dulu, baru dikirim lewat id-nya
- `canSend()`
- jendela balas 24 jam (`EnsuresReplyWindow`) — batas Meta, bukan batas kami

**Ada di keduanya**, dengan tanda tangan berbeda:

| | WAHA | Cloud API |
|---|---|---|
| teks | `send($phone, $message, $replyTo): void` | `sendText($phone, $message, $contextMessageId): ?string` |
| reaksi | `sendReaction($phone, $messageId, $reaction): void` | `sendReaction($phone, $messageId, $emoji): ?string` |
| media | `sendImage()` / `sendFile()` dengan biner langsung | `uploadMedia()` lalu `sendMedia()` |

Perhatikan **nilai kembalinya**: Cloud API memberi id pesan dari penyedia, WAHA
tidak. `SendInboxMessageAction` menyimpannya di `messages.provider_message_id`,
dan id itulah yang dipakai untuk membalas-dengan-kutipan dan untuk reaksi. Jadi
gateway yang tidak memberi id pesan akan kehilangan kedua fitur itu.

## Yang saya sarankan dikerjakan lebih dulu

Sebelum gateway disambungkan, satu perubahan struktural kecil akan menghemat
banyak: **angkat kontrak pengiriman jadi interface**, mis.
`App\Contracts\WhatsAppTransport` dengan `sendText`, `sendMedia`,
`sendReaction`, `sendContact`, dan sebuah `supports()` untuk kemampuan yang
tidak dimiliki semua penyedia. Lalu:

- satu resolver yang menerjemahkan `ConversationChannel` → transport, menggantikan
  lima `if` yang sekarang tersebar;
- `ConversationChannel` dapat case baru untuk gateway sendiri — kolomnya sudah
  menyimpan string, jadi migrasinya aditif;
- jalur pengingat booking ikut lewat resolver, bukan `app(WahaClient::class)`
  langsung.

Catatan kejujuran soal aturan proyek: CLAUDE.md melarang "interface dengan satu
implementasi". Di sini implementasinya **tiga**, dan percabangannya sudah
terbukti disalin lima kali — jadi ini bukan abstraksi yang didahulukan, ini
abstraksi yang sudah terlambat.

## Pertanyaan yang hanya bisa dijawab repo gateway

Belum saya isi karena saya belum bisa membaca `abdasis/whatsapp-gateway`:

1. Apakah gateway memberi **id pesan** pada tiap kiriman? (menentukan reaksi dan
   balas-dengan-kutipan)
2. Bentuk **payload webhook** pesan masuk, dan bagaimana membuktikan keasliannya
   — token di URL seperti WAHA, atau HMAC seperti Meta?
3. Bagaimana **kliniknya dikenali** dari pesan masuk? WAHA memakai nama sesi di
   payload; Cloud API memakai `phone_number_id`.
4. Satu server untuk semua klinik (seperti WAHA) atau satu instans per klinik?
   Ini menentukan kredensialnya di `WahaSetting`-sejenis (central) atau di
   `WhatsappSetting` (per klinik).
5. Apakah ada **manajemen sesi** (QR, restart, status)? Kalau ya, halaman
   koneksi yang ada bisa dipakai ulang; kalau tidak, `WhatsappConnectionService`
   perlu cabang sendiri.
6. **Media**: biner langsung seperti WAHA, atau unggah-dulu seperti Cloud API?
7. Apakah gateway **menyaring** status/story, grup, saluran, dan daftar siaran?
   Kalau tidak, penyaringnya harus dipindah ke sisi meba.
8. Ada **template** atau pesan bebas saja? Ini menentukan apakah broadcast bisa
   pindah ke gateway atau tetap di Cloud API.
9. Apakah ada batas laju yang dilaporkan secara eksplisit (mis. HTTP 429)?
   `CloudApiException::isThrottle()` sudah membedakannya; gateway baru perlu
   padanannya.

## Temuan sampingan: empat issue broadcast sudah selesai di kode

Saat memetakan ini saya menemukan bahwa empat issue yang masih terbuka
ternyata sudah terimplementasi:

- **#323** jitter — `BroadcastService` memakai `random_int(3, 7)` detik
  (`MIN_SECONDS_BETWEEN_MESSAGES` / `MAX_SECONDS_BETWEEN_MESSAGES`)
- **#324** batas harian — `App\Support\DailyMessageQuota` +
  `whatsapp_settings.daily_message_limit`
- **#325** throttle 429 — `CloudApiException::isThrottle()` mengenali status 429
  dan kode galat `130429`, `131056`, `80007`, diperiksa **sebelum** cabang
  penolakan nomor
- **#320** lanjutkan yang gagal — `php artisan` command `ResumeStalledBroadcasts`,
  dan broadcast kini lewat Cloud API, bukan WAHA (sumber 422-nya hilang)

Begitu juga rangkaian inbox: **#337, #338, #339, #341, #342** sudah ada
(`Conversation`, `Message`, tujuh route `inbox/*`, halaman `inbox/`,
`use-inbox-realtime.ts`, `MetaWhatsappWebhookController`).

Yang **belum**: **#340** — jumlah belum dibaca di sidebar. `unread_count` sudah
ada per percakapan (model, resource, dan lencana di `conversation-list.tsx`),
`SidebarMenuBadge` juga sudah ada di primitif tapi belum dipakai sama sekali.
Yang kurang satu angka total untuk seluruh klinik, yang bisa dibaca shell dari
halaman mana pun.

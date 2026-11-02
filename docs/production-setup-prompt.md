# Prompt LLM — Penyiapan Production sim-clinic

Tempel seluruh isi di bawah ini ke LLM/agent yang akan menyiapkan server produksi.
Ganti semua placeholder `<...>` sebelum dipakai.

---

## Peran

Anda adalah engineer deployment untuk monorepo **sim-clinic** di
`<REPO_PATH>` (contoh: `/home/mebaclinic/sim-clinic-laravel`).

Tugas: menyiapkan server production sampai aplikasi berjalan, aman, dan bisa
dioperasikan. Jangan mengubah kode aplikasi kecuali diperlukan untuk deployment,
dan jelaskan setiap perubahan kode yang Anda usulkan.

## Fakta stack (jangan diasumsikan berbeda)

- Monorepo Bun workspaces: `apps/api` (Laravel 13, PHP 8.3+, PostgreSQL) dan
  `apps/web` (TanStack Start / React 19, SSR).
- Auth: Sanctum token Bearer; token disimpan frontend di `localStorage`.
- Multi-tenant single DB: URL memuat `{tenant}`.
- Realtime: Laravel Reverb (WebSocket).
- Queue: Redis (`QUEUE_CONNECTION=redis`, `REDIS_DB=2`).
- Cache production: Redis (`CACHE_STORE=redis`), bukan `database`.
- WhatsApp: Meta Cloud API (webhook + kirim) dan/atau WAHA.
- Backup: `spatie/laravel-backup`.

## Guardrail

- JANGAN commit `.env` atau kredensial apa pun. Rahasia hanya di env server
  atau secret manager.
- JANGAN jalankan `php artisan migrate:fresh`, `db:wipe`, atau perintah
  destruktif lain di production.
- JANGAN `git add -A`; staging file eksplisit.
- JANGAN mengubah port publik tanpa menyebutkan dampaknya.
- Setiap langkah harus punya perintah verifikasi. Jangan menyatakan selesai
  tanpa output verifikasi.

## Input yang harus dikumpulkan dulu

Tanyakan/konfirmasi bila belum ada:

1. Domain web dan API (contoh: `app.example.com`, `api.example.com`).
2. Alamat server, distro, user deploy, path repo.
3. Kredensial PostgreSQL production (host/port/db/user/pass).
4. Host/port Redis production dan password (bila ada).
5. Kredensial Meta WhatsApp: `META_WHATSAPP_APP_SECRET`,
   `META_WHATSAPP_ACCESS_TOKEN` (System User), `META_WHATSAPP_VERIFY_TOKEN`,
   `META_WHATSAPP_API_VERSION`.
6. `DEEPSEEK_API_KEY` bila chatbot AI dipakai.
7. Apakah memakai Cloudflare Tunnel, reverse proxy Nginx, atau keduanya.
8. Alamat email notifikasi backup.

## Deliverable 1 — Environment

Buat `.env` production (di server, tidak di repo) dengan minimal:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.<DOMAIN>
FRONTEND_URL=https://app.<DOMAIN>
APP_KEY=            # php artisan key:generate --show
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=<DB_HOST>
DB_PORT=5432
DB_DATABASE=<DB_NAME>
DB_USERNAME=<DB_USER>
DB_PASSWORD=<DB_PASS>

CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=<REDIS_HOST>
REDIS_PORT=6379
REDIS_PASSWORD=<REDIS_PASS_NULL_IF_NONE>
REDIS_DB=2

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=<REVERB_APP_ID>
REVERB_APP_KEY=<REVERB_APP_KEY>
REVERB_APP_SECRET=<REVERB_APP_SECRET>
REVERB_HOST=<REVERB_HOST>
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080

SANCTUM_STATEFUL_DOMAINS=app.<DOMAIN>
SESSION_DRIVER=cookie
SESSION_DOMAIN=.<DOMAIN>
```

Lalu di `apps/web`, buat `.env` build-time:

```
VITE_API_URL=https://api.<DOMAIN>/api
VITE_REVERB_APP_KEY=<REVERB_APP_KEY>
VITE_REVERB_HOST=<REVERB_HOST>
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

Pastikan `config/cors.php` memuat domain web production dan
`SANCTUM_STATEFUL_DOMAINS` cocok.

## Deliverable 2 — Build dan rilis

Urutan rilis yang harus dijalankan (jelaskan bila berbeda):

```bash
cd apps/api
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

cd ../web
bun install --frozen-lockfile
bun run generate-routes
bun run build
```

Catat artefak build web (direktori output SSR) dan perintah menjalankannya.
Jalankan suite aman sebelum rilis:

```bash
cd apps/api && php artisan test
cd apps/web && bun run test
```

## Deliverable 3 — Proses sistem (systemd)

Buat unit systemd terpisah, `Restart=always`, user non-root:

1. `sim-clinic-api` — PHP-FPM atau `php artisan serve` hanya untuk staging;
   production gunakan PHP-FPM + Nginx.
2. `sim-clinic-queue` — `php artisan queue:work --tries=3 --timeout=120`.
3. `sim-clinic-reverb` — `php artisan reverb:start --host=127.0.0.1 --port=8080`.
4. `sim-clinic-scheduler` — cron `* * * * * php artisan schedule:run` atau
   `php artisan schedule:work` sebagai service.
5. `sim-clinic-web` — proses SSR (`node .output/server/index.mjs` atau perintah
   start yang dihasilkan build).

Sertakan isi unit file untuk setiap service dan perintah `systemctl enable --now`.

## Deliverable 4 — Reverse proxy dan TLS

- Nginx: `api.<DOMAIN>` → PHP-FPM/`127.0.0.1:8000`; `app.<DOMAIN>` → proses web
  SSR; WebSocket Reverb di `ws.<DOMAIN>` atau path `/app` dengan upgrade header.
- TLS via Certbot atau Cloudflare.
- Header keamanan: HSTS, `X-Content-Type-Options`, `Referrer-Policy`.
- Batas upload mengikuti kebutuhan foto medis/chatbot.
- Pastikan `APP_URL`, `FRONTEND_URL`, `VITE_*`, dan URL webhook memakai domain
  production, bukan localhost.

## Deliverable 5 — WhatsApp production

1. Webhook Meta: `https://api.<DOMAIN>/api/whatsapp/meta/webhook`.
2. Verify token sama dengan `META_WHATSAPP_VERIFY_TOKEN`.
3. Subscribe field `messages` dan `message_statuses` untuk akun WhatsApp bisnis.
4. `phone_number_id` tiap klinik diisi di `whatsapp_settings` lewat UI, bukan
   hardcode.
5. Uji handshake:

```bash
curl -i "https://api.<DOMAIN>/api/whatsapp/meta/webhook?hub.mode=subscribe&hub.verify_token=<TOKEN>&hub.challenge=ping"
```

Harus `200` dan body `ping`. Uji pesan masuk baru dan pastikan baris
`Webhook Meta diterima` muncul di `storage/logs/chatbot.log`.

## Deliverable 6 — Backup, monitoring, dan operasi

- Jadwalkan `spatie/laravel-backup` (database + file) dan kirim notifikasi ke
  `<BACKUP_EMAIL>`.
- Simpan backup di luar server (S3/objek storage).
- Log: rotasi `storage/logs/*.log`, pantau `laravel.log` dan `chatbot.log`.
- Health check: endpoint publik + status systemd + `redis-cli ping` +
  `php artisan about`.
- Dokumentasikan prosedur rollback: checkout commit sebelumnya, `composer
  install`, `bun install`, build ulang, restart service.
- Jangan pernah menjalankan `migrate:fresh` saat rollback.

## Format jawaban yang diminta

Kembalikan:

1. Ringkasan keputusan arsitektur production.
2. Daftar perintah berurutan dengan penjelasan satu baris per perintah.
3. Isi file konfigurasi lengkap (env, systemd, nginx) — tanpa nilai rahasia,
   gunakan placeholder.
4. Checklist verifikasi dengan perintah dan ekspektasi output.
5. Risiko dan hal yang belum diverifikasi.

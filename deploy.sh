#!/bin/bash
# Auto-deploy script — Sim Clinic (mebaclinic.com)
# Dipanggil dari GitHub webhook. TIDAK pakai set -e biar tiap step error ketahuan.

COMPOSE="docker compose -f docker-compose.prod.yml"

# Skrip ini ikut ter-update oleh git pull di bawah. Bash membaca berkasnya
# sambil jalan, jadi menarik versi baru di tengah eksekusi bisa membuat sisa
# perintah dibaca dari offset yang sudah bergeser. Karena itu: tarik kode
# dulu, lalu jalankan ulang diri sendiri dari berkas yang sudah baru.
if [ -z "$SIM_CLINIC_DEPLOY_STAGE2" ]; then
  cd /home/mebaclinic/repo || exit 1

  echo "📥 Pulling latest code... (rebase + autostash)"
  if git pull --rebase --autostash; then
    export SIM_CLINIC_PULL_FAILED=""
  else
    export SIM_CLINIC_PULL_FAILED="1"
  fi

  export SIM_CLINIC_DEPLOY_STAGE2=1
  exec bash "$0" "$@"
fi

cd /home/mebaclinic/repo || exit 1
ERRORS=""

[ -n "$SIM_CLINIC_PULL_FAILED" ] && ERRORS+="git pull gagal; "

echo "🔨 Building API..."
$COMPOSE build api || ERRORS+="build API gagal; "

echo "🔨 Building Web..."
$COMPOSE build web || ERRORS+="build Web gagal; "

echo "🔨 Building Nginx..."
$COMPOSE build nginx || ERRORS+="build Nginx gagal; "

echo "🚀 Restarting containers..."
$COMPOSE up -d || ERRORS+="restart container gagal; "

# Cache config/route/paket menempel di volume bootstrap/cache dan ikut
# terbawa lintas build. Kalau tidak dibersihkan, image baru masih membaca
# konfigurasi image lama.
echo "🧽 Membersihkan cache Laravel..."
$COMPOSE exec -T api php artisan optimize:clear || ERRORS+="bersihkan cache gagal; "

# Migrasi WAJIB tiap deploy. Tanpa ini, kolom baru tidak pernah sampai ke
# server dan halaman yang memakainya gagal memuat data. Dicoba beberapa kali
# karena PHP-FPM bisa siap sedikit lebih dulu daripada koneksi database.
echo "🗄️  Menjalankan migrasi database..."
MIGRATED=""
for attempt in 1 2 3 4 5; do
  if $COMPOSE exec -T api php artisan migrate --force; then
    MIGRATED="1"
    break
  fi

  echo "⏳ Migrasi belum berhasil (percobaan $attempt), menunggu database..."
  sleep 5
done

[ -z "$MIGRATED" ] && ERRORS+="migrasi database gagal; "

# Izin peran klinik dipasang sekali saat kliniknya dibuat dan tidak pernah
# ditengok lagi. Modul yang ditambahkan sesudah itu tidak akan pernah sampai
# ke klinik yang sudah berjalan, karena yang membuat izinnya adalah seeder --
# dan seeder tidak pernah ikut deploy. Perintah ini hanya menambah, tidak
# pernah mencabut, jadi aman dijalankan tiap kali.
echo "🔑 Melengkapi izin peran klinik..."
$COMPOSE exec -T api php artisan clinic:sync-permissions || ERRORS+="sinkron izin peran gagal; "

echo "🧹 Cleaning old images..."
docker image prune -f >/dev/null 2>&1 || true

# Notif
COMMIT=$(git log -1 --format='%h - %s' 2>/dev/null || echo "unknown")
CONTAINERS=$(docker ps --format '{{.Names}} {{.Status}}' | grep sim-clinic 2>/dev/null)

if [ -z "$ERRORS" ]; then
  echo "✅ Deploy selesai — $COMMIT"
  sudo -n /usr/local/lib/hermes-agent/venv/bin/hermes send --to telegram "✅ Deploy Sim Clinic sukses via webhook!
Commit: $COMMIT
$CONTAINERS
Web: https://mebaclinic.com" 2>&1 || true
else
  echo "❌ Deploy gagal: $ERRORS"
  sudo -n /usr/local/lib/hermes-agent/venv/bin/hermes send --to telegram "❌ Deploy Sim Clinic GAGAL: $ERRORS
Commit: $COMMIT" 2>&1 || true
fi

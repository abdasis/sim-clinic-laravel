#!/usr/bin/env bash
#
# Apakah situs yang sedang hidup benar-benar memakai kode terbaru?
#
# Dijalankan DI SERVER. Menjawab satu pertanyaan yang selama ini hanya bisa
# ditebak: perbaikan yang sudah masuk `main` sudah sampai ke container yang
# melayani pengunjung, atau belum.
#
#   bash scripts/cek-deploy.sh
#
# Pertanyaannya penting karena deploy yang gagal terlihat persis seperti
# deploy yang tidak membawa perubahan apa pun: aset lama tetap tersimpan di
# volume bersama dan nginx tetap menyajikannya dengan `Cache-Control:
# immutable` selama 30 hari, jadi situsnya tetap hidup dan tetap lama.
#
# Hanya membaca. Tidak membangun, tidak menarik kode, tidak menjalankan ulang
# apa pun.
set -uo pipefail

PROJECT="${COMPOSE_PROJECT:-sim-clinic}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

judul() { printf '\n\033[1;36m== %s\033[0m\n' "$1"; }
ya()    { printf '  \033[1;32m[v]\033[0m %s\n' "$1"; }
tidak() { printf '  \033[1;31m[x]\033[0m %s\n' "$1"; }
info()  { printf '      %s\n' "$1"; }

# Penanda yang hanya ada di kode setelah perbaikan sidebar tablet: ambang
# 32rem untuk rel ikon. Kalau CSS yang disajikan tidak memuatnya, container
# itu menjalankan versi sebelum perbaikan — berapa kali pun kodenya diperbaiki
# di repo.
# Tailwind v4 menulis ambangnya sebagai `width>=32rem`, bukan `min-width:32rem`
# — diambil dari CSS hasil build sungguhan, bukan dari dugaan.
PENANDA="width>=32rem"

judul "Repo di server"
if git rev-parse --git-dir >/dev/null 2>&1; then
    info "HEAD lokal   : $(git rev-parse --short HEAD)  $(git log -1 --format=%s | cut -c1-60)"
    git fetch origin main --quiet 2>/dev/null \
        && info "origin/main  : $(git rev-parse --short origin/main)  $(git log -1 --format=%s origin/main | cut -c1-60)"

    if [ "$(git rev-parse HEAD)" = "$(git rev-parse origin/main 2>/dev/null)" ]; then
        ya "Repo di server sudah sama dengan origin/main."
    else
        tidak "Repo di server TERTINGGAL dari origin/main."
        info "Webhook deploy-nya tidak jalan, atau gagal sebelum sempat pull."
        info "Periksa: journalctl -u sim-clinic-webhook -n 50 --no-pager"
    fi
else
    tidak "Bukan repo git."
fi

judul "Container"
if ! command -v docker >/dev/null; then
    tidak "docker tidak ada di PATH; sisa pemeriksaan dilewati."
    exit 1
fi

docker compose -p "$PROJECT" ps --format '      {{.Service}}  {{.Status}}' 2>/dev/null \
    || tidak "Tidak bisa membaca container project '$PROJECT'."

judul "Umur image web"
DIBUAT="$(docker inspect --format '{{.Created}}' "$(docker compose -p "$PROJECT" images -q web 2>/dev/null | head -1)" 2>/dev/null)"
if [ -n "$DIBUAT" ]; then
    info "Image web dibangun: $DIBUAT"
    info "Kalau tanggalnya lebih tua dari commit terakhir, image-nya tidak pernah dibangun ulang."
else
    tidak "Image web tidak ditemukan."
fi

judul "Isi build yang sedang dilayani"
# Inilah pemeriksaan yang menentukan: bukan apa yang ada di repo, melainkan
# apa yang ada di dalam container yang sedang melayani pengunjung.
if ! docker compose -p "$PROJECT" exec -T web true 2>/dev/null; then
    tidak "Container 'web' tidak bisa dihubungi, jadi isinya tidak diperiksa."
    info "Container-nya mati, atau nama project-nya bukan '$PROJECT'."
    info "Coba: COMPOSE_PROJECT=<nama> bash scripts/cek-deploy.sh"
    exit 1
fi

HASIL="$(docker compose -p "$PROJECT" exec -T web sh -c \
    "grep -l '$PENANDA' /app/apps/web/dist/client/assets/*.css 2>/dev/null | head -1" 2>/dev/null)"

if [ -n "$HASIL" ]; then
    ya "Perbaikan sidebar tablet ADA di CSS yang dilayani container."
    info "$HASIL"
    info "Kalau tabletnya masih bermasalah, berarti bukan deploy-nya — kabari saya."
else
    tidak "Perbaikan sidebar tablet TIDAK ADA di CSS yang dilayani container."
    info "Container web menjalankan build lama. Bangun ulang:"
    info "  docker compose -p $PROJECT build --no-cache web && docker compose -p $PROJECT up -d web"
fi

judul "Volume aset nginx"
# `cp -r` saat container web boot hanya menambah dan menimpa, tidak pernah
# menghapus. Volume yang menumpuk tidak membuat situs salah — nama berkasnya
# ber-hash — tapi jumlah berkas yang jauh lebih banyak dari satu build adalah
# tanda volume ini tidak pernah dibersihkan sejak lama.
JUMLAH="$(docker compose -p "$PROJECT" exec -T nginx sh -c \
    'ls /var/www/web-assets/assets 2>/dev/null | wc -l' 2>/dev/null | tr -d '[:space:]')"
[ -n "$JUMLAH" ] && info "Berkas di volume aset: $JUMLAH"

printf '\n'

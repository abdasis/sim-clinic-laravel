#!/bin/bash
# Auto-deploy script — Sim Clinic (mebaclinic.com)
# Dipanggil dari GitHub webhook. TIDAK pakai set -e biar tiap step error ketahuan.

cd /home/mebaclinic/repo
ERRORS=""

echo "📥 Pulling latest code... (rebase + autostash)"
git pull --rebase --autostash || ERRORS+="git pull gagal; "

echo "🔨 Building API..."
docker compose -f docker-compose.prod.yml build api || ERRORS+="build API gagal; "

echo "🔨 Building Web..."
docker compose -f docker-compose.prod.yml build web || ERRORS+="build Web gagal; "

echo "🔨 Building Nginx..."
docker compose -f docker-compose.prod.yml build nginx || ERRORS+="build Nginx gagal; "

echo "🚀 Restarting containers..."
docker compose -f docker-compose.prod.yml up -d || ERRORS+="restart container gagal; "

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

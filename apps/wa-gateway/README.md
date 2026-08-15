# wa-gateway — Sidecar WhatsApp QR

Sesi WhatsApp Web (Baileys) sebagai HTTP API kecil untuk sim-clinic.

## Menjalankan

```bash
cd apps/wa-gateway
bun install
WA_TOKEN=token-rahasia-anda bun run start   # port default 3100
```

Lalu di `apps/api/.env`:

```
WA_SIDECAR_URL=http://127.0.0.1:3100
WA_SIDECAR_TOKEN=token-rahasia-anda
```

dan pilih driver **Scan QR** di menu Broadcast WA → Pengaturan Pengiriman.

Sesi tersimpan di `apps/wa-gateway/session/` (di-gitignore) dan pulih
otomatis saat proses restart. Logout dari HP mencabut sesi — pindai ulang
QR dari halaman Koneksi.

> Peringatan: ini WhatsApp Web tidak resmi. Cocok untuk tahap awal karena
> murah, tapi ada risiko nomor ditandai bila mengirim spam. Kirim wajar,
> hormati opt-out, dan siapkan migrasi ke API resmi/gateway berbayar —
> backend sudah memakai kontrak `WhatsappClient` sehingga pindah provider
> tidak mengubah logika bisnis.

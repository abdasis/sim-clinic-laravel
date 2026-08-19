# Contracts: AI Chat Booking

**Feature**: 013-ai-chat-booking | **Date**: 2026-08-19

## 1. Webhook Inbound WAHA (sistem menerima pesan)

`POST /api/whatsapp/webhook/{token}` — route publik (no auth), token dicocokkan dengan config webhook.

**Request** (dari WAHA, event `message`):

```json
{
  "event": "message",
  "session": "meba-clinic",
  "payload": {
    "id": "true_628xxx@c.us_AAAA",
    "timestamp": 1667561485,
    "from": "6281234567890@c.us",
    "fromMe": false,
    "to": "628xxx@c.us",
    "body": "Jam buka klinik?",
    "hasMedia": false
  }
}
```

**Sistem**: resolve tenant via `WhatsappSetting::where('session', payload.session)`, bind tenant, dispatch `ProcessInboundMessageJob`. Abaikan `fromMe=true` (pesan sendiri) dan `hasMedia=true` (FR-019).

**Response**: `200 { "data": null, "meta": [] }` segera (proses asinkron).

## 2. Kontrak Tools (AI ↔ backend, function calling DeepSeek)

Format skema tool mengikuti OpenAI/DeepSeek. Sistem mengirim definisi tools di `tools` array; DeepSeek return `tool_calls`; sistem eksekusi tool lalu kirim hasil sebagai message `role: "tool"`.

### 2.1 search_services
```json
{
  "type": "function",
  "function": {
    "name": "search_services",
    "description": "Cari layanan/treatment klinik beserta tarif. Pakai untuk pertanyaan layanan dan harga.",
    "parameters": {
      "type": "object",
      "properties": {
        "query": { "type": "string", "description": "Nama layanan yang dicari (opsional)" }
      }
    }
  }
}
```
**Return**: `[{ "id": 1, "name": "Facial", "price": 150000, "duration": "60 menit" }]` atau `[]`.

### 2.2 search_staff
```json
{
  "type": "function",
  "function": {
    "name": "search_staff",
    "description": "Cari dokter/therapist klinik yang tersedia.",
    "parameters": {
      "type": "object",
      "properties": {
        "query": { "type": "string", "description": "Nama staf yang dicari (opsional)" }
      }
    }
  }
}
```
**Return**: `[{ "id": 2, "name": "dr. Andi", "role": "doctor" }]` atau `[]`.

### 2.3 get_clinic_info
```json
{
  "type": "function",
  "function": {
    "name": "get_clinic_info",
    "description": "Ambil informasi umum klinik: nama, alamat, telepon, jam operasional.",
    "parameters": { "type": "object", "properties": {} }
  }
}
```
**Return**: `{ "name": "Meba Clinic", "address": "...", "phone": "...", "operating_hours": "Sen-Jum 09:00-17:00" }`.

### 2.4 get_product_stock
```json
{
  "type": "function",
  "function": {
    "name": "get_product_stock",
    "description": "Cari stok produk klinik: nama, satuan, saldo, status menipis.",
    "parameters": {
      "type": "object",
      "properties": {
        "query": { "type": "string", "description": "Nama produk yang dicari" }
      },
      "required": ["query"]
    }
  }
}
```
**Return**: `[{ "name": "Vitamin C Serum", "unit": "botol", "stock_balance": 12, "is_low_stock": false }]` atau `[]`.

### 2.5 create_booking
```json
{
  "type": "function",
  "function": {
    "name": "create_booking",
    "description": "Buat booking janji klinik. Hanya untuk pasien terdaftar dan layanan yang boleh dibooking. Waktu harus di masa depan.",
    "parameters": {
      "type": "object",
      "properties": {
        "patient_id": { "type": "integer", "description": "ID pasien" },
        "service_id": { "type": "integer", "description": "ID layanan" },
        "assignee_id": { "type": "integer", "description": "ID dokter/therapist" },
        "start_at": { "type": "string", "description": "Waktu mulai ISO 8601" },
        "end_at": { "type": "string", "description": "Waktu selesai ISO 8601" }
      },
      "required": ["patient_id", "service_id", "assignee_id", "start_at", "end_at"]
    }
  }
}
```
**Return (sukses)**: `{ "booking_id": 42, "status": "pending", "overlap_warnings": [] }`.
**Return (gagal)**: `{ "error": "Layanan tidak dapat dibooking via chat" }` / `{ "error": "Pasien tidak terdaftar" }` / `{ "error": "Waktu tidak valid" }`.

## 3. API Admin ChatbotSetting (REST, tenant-scoped)

Dalam grup `/{tenant}/clinic` (`auth:sanctum`, `resolve.tenant`, otorisasi admin).

| Method | Path | Deskripsi |
|--------|------|-----------|
| GET | `/{tenant}/clinic/chatbot/settings` | Ambil pengaturan chatbot |
| PUT | `/{tenant}/clinic/chatbot/settings` | Simpan: `is_active`, `agent_name`, `bookable_service_ids` |
| POST | `/{tenant}/clinic/chatbot/settings/avatar` | Upload avatar (multipart, field `file`) |

**Response shape** standar `{ data, meta }`.

### GET/PUT `/{tenant}/clinic/chatbot/settings`
```json
{
  "data": {
    "is_active": true,
    "agent_name": "Asisten Meba",
    "agent_avatar_url": "https://.../chatbot/1/avatar.jpg",
    "bookable_service_ids": [1, 3, 5]
  },
  "meta": []
}
```

### POST avatar
```json
{ "data": { "path": "chatbot/1/avatar.jpg", "url": "https://..." }, "meta": ["message"] }
```

## 4. System Prompt AI (kontrak perilaku)

Disusun backend sebelum panggil DeepSeek, mengandung:
- Identitas: "Kamu adalah {agent_name}, asisten chatbot klinik {clinic_name}."
- Ruang lingkup: hanya jawab soal layanan, jam, lokasi, stok produk, booking klinik. Tolak topik lain.
- Anti-halusinasi: HANYA jawab dari hasil tool. Bila tool kosong, nyatakan tidak tersedia. Dilarang mengarang harga/jadwal/nama.
- Bahasa: Indonesia, semi-formal friendly. Gunakan nama agent saat menyapa/menandatangani.
- Booking: bila data kurang, tanya klarifikasi sebelum panggil `create_booking`. Bila pengirim bukan pasien terdaftar, arahkan mendaftar.

## 5. Balasan WhatsApp (outbound)

Via `WahaClient::send(string $phone, string $message)` eksisting. Phone dinormalisasi (`PhoneNumber::normalize`), `chatId` = `{phone}@c.us`. Pesan balasan plain text (FR-019: media tidak didukung).
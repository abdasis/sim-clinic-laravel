/**
 * Sidecar WhatsApp QR untuk sim-clinic.
 *
 * Menjalankan sesi WhatsApp Web (Baileys) dan mengeksposnya sebagai HTTP API
 * kecil dengan kontrak yang sama seperti gateway Fonnte-style, sehingga
 * backend Laravel cukup menunjuk URL ini tanpa logika khusus:
 *
 *   GET  /status  -> { connected, number, connected_at }
 *   GET  /qr      -> { qr: dataURL | null }   (hanya saat belum terhubung)
 *   POST /send    -> form/json { target, message }
 *
 * Semua endpoint butuh header Authorization berisi WA_TOKEN. Kredensial sesi
 * disimpan Baileys di WA_SESSION_DIR (bukan database, bukan plain text di
 * repo) dan dipulihkan otomatis saat proses restart.
 *
 * Jalankan:  WA_TOKEN=rahasia node index.mjs   (port: WA_PORT, default 3100)
 */
import http from "node:http"
import path from "node:path"
import { mkdirSync } from "node:fs"
import makeWASocket, {
  DisconnectReason,
  useMultiFileAuthState,
  fetchLatestBaileysVersion,
} from "@whiskeysockets/baileys"
import QRCode from "qrcode"
import pino from "pino"

const PORT = Number(process.env.WA_PORT ?? 3100)
const TOKEN = process.env.WA_TOKEN
const SESSION_DIR = process.env.WA_SESSION_DIR ?? path.join(process.cwd(), "session")

if (!TOKEN) {
  console.error("WA_TOKEN wajib diisi — token inilah yang dipakai Laravel untuk mengakses sidecar.")
  process.exit(1)
}

mkdirSync(SESSION_DIR, { recursive: true })

const logger = pino({ level: process.env.WA_LOG_LEVEL ?? "warn" })

const state = {
  sock: null,
  connected: false,
  number: null,
  connectedAt: null,
  qrDataUrl: null,
  stopping: false,
}

async function connect() {
  const { state: authState, saveCreds } = await useMultiFileAuthState(SESSION_DIR)
  const { version } = await fetchLatestBaileysVersion()

  const sock = makeWASocket({
    version,
    auth: authState,
    logger,
    // QR dirender sendiri lewat endpoint /qr, bukan ke terminal.
    printQRInTerminal: false,
  })

  state.sock = sock

  sock.ev.on("creds.update", saveCreds)

  sock.ev.on("connection.update", async (update) => {
    const { connection, lastDisconnect, qr } = update

    if (qr) {
      state.qrDataUrl = await QRCode.toDataURL(qr, { margin: 1, width: 320 })
    }

    if (connection === "open") {
      state.connected = true
      state.connectedAt = new Date().toISOString()
      state.number = sock.user?.id?.split(":")[0] ?? null
      state.qrDataUrl = null
      logger.warn({ number: state.number }, "WhatsApp terhubung")
    }

    if (connection === "close") {
      state.connected = false
      state.number = null

      const code = lastDisconnect?.error?.output?.statusCode
      const loggedOut = code === DisconnectReason.loggedOut

      logger.warn({ code, loggedOut }, "Koneksi WhatsApp terputus")

      // Logout berarti sesi dicabut dari HP — tunggu scan ulang. Selain itu,
      // sambung ulang otomatis supaya restart server tidak butuh campur tangan.
      if (!state.stopping && !loggedOut) {
        setTimeout(() => connect().catch((e) => logger.error(e)), 3000)
      }
    }
  })
}

/** Normalisasi 62xxx -> JID WhatsApp. */
function toJid(target) {
  const digits = String(target).replace(/\D+/g, "")
  return `${digits}@s.whatsapp.net`
}

function readBody(req) {
  return new Promise((resolve) => {
    let raw = ""
    req.on("data", (chunk) => (raw += chunk))
    req.on("end", () => {
      const type = req.headers["content-type"] ?? ""
      try {
        if (type.includes("application/json")) return resolve(JSON.parse(raw || "{}"))
        resolve(Object.fromEntries(new URLSearchParams(raw)))
      } catch {
        resolve({})
      }
    })
  })
}

function json(res, status, payload) {
  res.writeHead(status, { "content-type": "application/json" })
  res.end(JSON.stringify(payload))
}

const server = http.createServer(async (req, res) => {
  if (req.headers.authorization !== TOKEN) {
    return json(res, 401, { error: "unauthorized" })
  }

  const url = new URL(req.url, `http://localhost:${PORT}`)

  if (req.method === "GET" && url.pathname === "/status") {
    return json(res, 200, {
      connected: state.connected,
      number: state.number,
      connected_at: state.connectedAt,
    })
  }

  if (req.method === "GET" && url.pathname === "/qr") {
    return json(res, 200, { qr: state.connected ? null : state.qrDataUrl })
  }

  if (req.method === "POST" && url.pathname === "/send") {
    if (!state.connected || !state.sock) {
      return json(res, 503, { error: "not_connected" })
    }

    const { target, message } = await readBody(req)

    if (!target || !message) {
      return json(res, 422, { error: "target_and_message_required" })
    }

    try {
      await state.sock.sendMessage(toJid(target), { text: String(message) })
      return json(res, 200, { status: true })
    } catch (error) {
      logger.error({ err: error?.message, target }, "Gagal mengirim pesan")
      return json(res, 500, { error: "send_failed" })
    }
  }

  json(res, 404, { error: "not_found" })
})

process.on("SIGTERM", () => {
  state.stopping = true
  server.close(() => process.exit(0))
})

server.listen(PORT, () => {
  console.log(`wa-gateway siap di :${PORT} — sesi di ${SESSION_DIR}`)
})

connect().catch((error) => {
  console.error("Gagal memulai sesi WhatsApp:", error)
  process.exit(1)
})

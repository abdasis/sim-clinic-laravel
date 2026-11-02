import type Echo from "laravel-echo"

import { getToken } from "#/lib/api.ts"

type EchoClient = Echo<"reverb">

interface ChannelAuthorizationData {
  auth: string
  channel_data?: string
}

const env = import.meta.env as Record<string, string | undefined>

const rawBaseUrl = env.VITE_API_URL ?? "http://localhost:8123/api"
const apiBaseUrl = rawBaseUrl.endsWith("/api")
  ? rawBaseUrl
  : `${rawBaseUrl.replace(/\/+$/, "")}/api`
const authEndpoint = `${apiBaseUrl}/broadcasting/auth`

const wsPort = Number(env.VITE_REVERB_PORT ?? "8080")
const port = Number.isFinite(wsPort) ? wsPort : 8080
const forceTLS = (env.VITE_REVERB_SCHEME ?? "http") === "https"

let clientPromise: Promise<EchoClient | null> | null = null
let connectedToken: string | null = null
// Naik setiap token berubah; klien yang sedang diimpor tidak boleh menang.
let generation = 0

/**
 * Klien Echo untuk channel privat kotak masuk.
 *
 * Aman dipanggil dari server: tanpa `window` tidak ada koneksi. `pusher-js`
 * diimpor dinamis supaya tidak ikut dievaluasi saat SSR, dan token dibaca
 * ulang di authorizer — bukan direkatkan saat klien dibuat — agar token yang
 * diperbarui tetap dipakai tanpa membuat ulang koneksi.
 */
export async function getEcho(): Promise<EchoClient | null> {
  if (typeof window === "undefined") return null

  const key = env.VITE_REVERB_APP_KEY
  if (!key) return null

  const token = getToken()
  if (!token) return null

  if (clientPromise !== null && connectedToken === token) return clientPromise

  disconnectEcho()
  connectedToken = token

  const current = generation
  const promise = createClient(key, current)
  clientPromise = promise

  return promise
}

/** Putuskan koneksi. Aman dipanggil saat logout atau sebelum koneksi dibuat. */
export function disconnectEcho() {
  const pending = clientPromise
  clientPromise = null
  connectedToken = null
  generation += 1

  if (pending === null) return
  void pending.then((client) => client?.disconnect()).catch(() => undefined)
}

async function createClient(
  key: string,
  current: number,
): Promise<EchoClient | null> {
  const { default: Pusher } = await import("pusher-js")
  const { default: EchoClient } = await import("laravel-echo")

  if (current !== generation) return null

  return new EchoClient({
    broadcaster: "reverb",
    key,
    Pusher,
    wsHost: env.VITE_REVERB_HOST ?? "localhost",
    wsPort: port,
    wssPort: port,
    forceTLS,
    // `pusher-js` menamai transportnya "ws" untuk kedua skema: ws:// maupun
    // wss://. Menyebut hanya "wss" di sini menyisakan nol transport, sehingga
    // koneksi langsung berhenti di status `failed` tanpa satu pun percobaan
    // WebSocket — dan kotak masuk tidak pernah tersegarkan sendiri.
    enabledTransports: ["ws", "wss"],
    authEndpoint,
    authorizer: (channel) => ({
      authorize: async (socketId, callback) => {
        const bearer = getToken()
        if (!bearer) {
          callback(new Error("Sesi berakhir"), null)
          return
        }

        try {
          const response = await fetch(authEndpoint, {
            method: "POST",
            headers: {
              Accept: "application/json",
              "Content-Type": "application/json",
              Authorization: `Bearer ${bearer}`,
            },
            body: JSON.stringify({
              socket_id: socketId,
              channel_name: channel.name,
            }),
          })

          if (!response.ok) {
            callback(
              new Error(`Otorisasi channel gagal (${response.status})`),
              null,
            )
            return
          }

          callback(
            null,
            (await response.json()) as ChannelAuthorizationData,
          )
        } catch (error) {
          callback(
            error instanceof Error
              ? error
              : new Error("Otorisasi channel gagal"),
            null,
          )
        }
      },
    }),
  })
}

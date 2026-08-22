import { useEffect, useRef } from "react"

/** Berapa lama huruf kedua masih dianggap satu rangkaian dengan `g`. */
const SEQUENCE_MS = 1200

function isTyping(target: EventTarget | null): boolean {
  if (!(target instanceof HTMLElement)) return false

  return (
    target.isContentEditable ||
    ["INPUT", "TEXTAREA", "SELECT"].includes(target.tagName)
  )
}

/**
 * Pintasan gaya vim: tekan `g` lalu satu huruf.
 *
 * Bentuk dua ketukan dipilih supaya tidak ada satu pun huruf polos yang
 * direbut dari halaman — mengetik di kolom pencarian tetap mengetik, dan
 * tidak ada tabrakan dengan pintasan bawaan browser.
 */
export function useGoToShortcut(key: string, run: () => void): void {
  const handler = useRef(run)
  handler.current = run

  useEffect(() => {
    let armedAt = 0

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.metaKey || event.ctrlKey || event.altKey) return
      if (isTyping(event.target)) return

      const pressed = event.key.toLowerCase()

      if (Date.now() - armedAt < SEQUENCE_MS) {
        armedAt = 0

        if (pressed === key) {
          event.preventDefault()
          handler.current()
        }

        return
      }

      armedAt = pressed === "g" ? Date.now() : 0
    }

    window.addEventListener("keydown", onKeyDown)

    return () => window.removeEventListener("keydown", onKeyDown)
  }, [key])
}

/**
 * Pintasan satu ketukan untuk angka 1-9.
 *
 * Angka aman dipakai polos: tidak direbut browser, dan tidak ada teks yang
 * diketik di luar kolom input. Dipakai untuk berpindah tampilan — perpindahan
 * yang cukup sering sehingga rangkaian dua ketukan terasa lambat.
 */
export function useDigitShortcut(digit: string, run: () => void): void {
  const handler = useRef(run)
  handler.current = run

  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.metaKey || event.ctrlKey || event.altKey) return
      if (isTyping(event.target)) return
      if (event.key !== digit) return

      event.preventDefault()
      handler.current()
    }

    window.addEventListener("keydown", onKeyDown)

    return () => window.removeEventListener("keydown", onKeyDown)
  }, [digit])
}

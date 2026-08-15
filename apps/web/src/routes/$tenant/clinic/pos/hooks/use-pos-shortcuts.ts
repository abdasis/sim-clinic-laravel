import { useEffect, useRef } from "react"

interface PosShortcutHandlers {
  focusSearch: () => void
  stepLast: (delta: number) => void
  clearCart: () => void
  save: () => void
  toggleHelp: () => void
  /** Esc hanya mengosongkan keranjang saat tidak ada yang lebih mendesak. */
  canClear: () => boolean
}

function isTypingTarget(target: EventTarget | null): boolean {
  const element = target as HTMLElement | null
  if (!element) return false
  if (element.isContentEditable) return true

  return /^(INPUT|TEXTAREA|SELECT)$/.test(element.tagName)
}

/**
 * Pintasan kasir. Semuanya tanpa modifier kecuali simpan, dan tidak ada yang
 * menabrak bawaan browser: Mod+S/P/W/R sengaja dibiarkan.
 *
 * Saat kursor berada di kolom isian, hanya Escape dan Mod+Enter yang lewat —
 * sisanya harus bisa diketik apa adanya.
 */
export function usePosShortcuts(handlers: PosShortcutHandlers) {
  // Handler dirakit ulang tiap render; disimpan di ref supaya listener-nya
  // dipasang sekali saja, bukan dilepas-pasang setiap kali keranjang berubah.
  const latest = useRef(handlers)
  latest.current = handlers

  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      const typing = isTypingTarget(event.target)
      const mod = event.metaKey || event.ctrlKey

      if (mod && event.key === "Enter") {
        event.preventDefault()
        latest.current.save()

        return
      }

      if (event.key === "Escape") {
        // Di dalam pencarian, Escape lebih pantas mengosongkan kolomnya —
        // biarkan browser dan komponen yang menangani.
        if (typing || !latest.current.canClear()) return

        event.preventDefault()
        latest.current.clearCart()

        return
      }

      if (typing || mod || event.altKey) return

      if (event.key === "/") {
        event.preventDefault()
        latest.current.focusSearch()

        return
      }

      if (event.key === "?") {
        event.preventDefault()
        latest.current.toggleHelp()

        return
      }

      if (event.key === "+" || event.key === "=") {
        event.preventDefault()
        latest.current.stepLast(1)

        return
      }

      if (event.key === "-") {
        event.preventDefault()
        latest.current.stepLast(-1)
      }
    }

    window.addEventListener("keydown", onKey)

    return () => window.removeEventListener("keydown", onKey)
  }, [])
}

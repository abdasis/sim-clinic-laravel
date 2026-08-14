import { fileURLToPath } from "node:url"
import { defineConfig } from "vitest/config"
import viteReact from "@vitejs/plugin-react"

const src = fileURLToPath(new URL("./src", import.meta.url))

/**
 * Konfigurasi test dipisah dari vite.config.ts: plugin TanStack Start
 * membangun server SSR yang tidak dibutuhkan — dan menghambat — saat test
 * unit berjalan.
 */
export default defineConfig({
  plugins: [viteReact()],
  resolve: {
    alias: {
      "#": src,
      "@": src,
    },
  },
  test: {
    environment: "jsdom",
    globals: true,
    include: ["src/**/*.{test,spec}.{ts,tsx}"],
  },
})

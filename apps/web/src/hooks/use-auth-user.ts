import { getAuthUser, type AuthUser } from "#/lib/auth.ts"
import { useIsMounted } from "#/hooks/use-is-mounted.ts"

// ponytail: null saat SSR & first client render, update setelah mount — mencegah hydration mismatch dari baca localStorage di render.
export function useAuthUser(): AuthUser | null {
  return useIsMounted() ? getAuthUser() : null
}
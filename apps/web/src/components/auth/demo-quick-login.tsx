import { useMutation } from "@tanstack/react-query"
import { toast } from "sonner"

import { Button } from "#/components/ui/button.tsx"
import { Spinner } from "#/components/ui/spinner.tsx"
import { apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { setAuth } from "#/lib/auth.ts"
import type { AuthUser } from "#/lib/auth.ts"

/**
 * Pintu masuk sekali klik untuk akun seed. Hanya dipakai di halaman/dev guard;
 * pemanggil wajib menyembunyikannya dari build produksi (`import.meta.env.DEV`).
 */
export const DEMO_TENANT = "mebaclinic"

/**
 * Halaman masuk cepat hidup saat development, atau di production bila tenant
 * demo memang disediakan dan flag-nya dinyalakan saat build. Default mati:
 * kredensial seed tidak boleh terbuka tanpa keputusan sadar.
 */
export function isDemoLoginEnabled(): boolean {
  return import.meta.env.DEV || import.meta.env.VITE_ENABLE_DEMO_LOGIN === "true"
}

const DEMO_PASSWORD = "password123"

export type DemoAccountKind = "clinic" | "platform"

interface LoginResponse {
  data: { user: AuthUser; token: string }
  meta: { redirect_to: string }
}

interface DemoAccount {
  label: string
  email: string
  role: string
}

interface AccountGroup {
  kind: DemoAccountKind
  title: string
  description: string
  accounts: DemoAccount[]
}

const ACCOUNT_GROUPS: AccountGroup[] = [
  {
    kind: "clinic",
    title: "Klinik",
    description: `Tenant ${DEMO_TENANT}`,
    accounts: [
      {
        label: "Admin Klinik",
        email: "admin@mebaclinic.test",
        role: "Akses penuh",
      },
      { label: "Dokter", email: "dokter@mebaclinic.test", role: "Medis" },
      {
        label: "Terapis",
        email: "terapis@mebaclinic.test",
        role: "Perawatan",
      },
      { label: "Kasir", email: "kasir@mebaclinic.test", role: "Transaksi" },
    ],
  },
  {
    kind: "platform",
    title: "Platform",
    description: "Lintas tenant",
    accounts: [
      {
        label: "Platform Admin",
        email: "admin@platform.test",
        role: "Central",
      },
    ],
  },
]

function loginPath(kind: DemoAccountKind): string {
  return kind === "clinic" ? `/${DEMO_TENANT}/login` : "/central/login"
}

interface LoginTarget {
  account: DemoAccount
  kind: DemoAccountKind
}

interface DemoQuickLoginProps {
  /** Batasi grup akun; tanpa prop menampilkan semua. */
  kind?: DemoAccountKind
  /** Judul bagian. `null` menyembunyikannya, mis. di halaman demo penuh. */
  heading?: string | null
  /** Garis pemisah ke form manual di bawahnya. */
  showDivider?: boolean
}

export function DemoQuickLogin({
  kind,
  heading = "Masuk cepat (dev)",
  showDivider = false,
}: DemoQuickLoginProps) {
  const mutation = useMutation<LoginResponse, ApiError, LoginTarget>({
    mutationFn: ({ account, kind: targetKind }) =>
      apiPost<LoginResponse>(loginPath(targetKind), {
        email: account.email,
        password: DEMO_PASSWORD,
      }),
    onSuccess: (res, { account, kind: targetKind }) => {
      setAuth(res.data.token, res.data.user)
      toast.success(`Masuk sebagai ${account.label}`)

      if (typeof window !== "undefined") {
        // Admin platform tidak punya dashboard klinik: `redirect_to` sudah
        // menunjuk panel central-nya sendiri (`/central`).
        window.location.href =
          targetKind === "clinic"
            ? `/${DEMO_TENANT}/clinic`
            : res.meta.redirect_to
      }
    },
    onError: (err) => {
      // Kembali idle tanpa menyentuh isian: tidak ada form, cuma daftar tombol.
      toast.error(err.message)
    },
  })

  const busyEmail = mutation.isPending
    ? mutation.variables?.account.email
    : undefined

  const groups = kind
    ? ACCOUNT_GROUPS.filter((group) => group.kind === kind)
    : ACCOUNT_GROUPS

  return (
    <div className="space-y-5">
      {heading ? (
        <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
          {heading}
        </h3>
      ) : null}

      {groups.map((group) => (
        <section key={group.title} aria-labelledby={`demo-group-${group.kind}`}>
          <div className="mb-2 flex items-baseline justify-between gap-3">
            <h4
              id={`demo-group-${group.kind}`}
              className="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
              {group.title}
            </h4>
            <span className="text-xs text-muted-foreground">
              {group.description}
            </span>
          </div>

          <ul className="space-y-1.5">
            {group.accounts.map((account) => {
              const isBusy = busyEmail === account.email

              return (
                <li key={account.email}>
                  <Button
                    type="button"
                    variant="outline"
                    disabled={mutation.isPending}
                    aria-busy={isBusy}
                    aria-label={`Masuk sebagai ${account.label}`}
                    onClick={() =>
                      mutation.mutate({ account, kind: group.kind })
                    }
                    className="h-auto w-full justify-between gap-3 rounded-lg border-border/50 px-3 py-2.5 text-left transition-colors duration-150 ease-out hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:ring-offset-2 focus-visible:ring-offset-background active:scale-[0.96]"
                  >
                    <span className="flex min-w-0 flex-col gap-0.5">
                      <span className="text-sm font-medium text-sea-ink">
                        {account.label}
                      </span>
                      <span className="truncate text-xs text-sea-ink-soft">
                        {account.email}
                      </span>
                    </span>

                    <span className="flex shrink-0 items-center gap-2 text-xs text-muted-foreground">
                      {isBusy ? <Spinner className="size-3.5" /> : account.role}
                    </span>
                  </Button>
                </li>
              )
            })}
          </ul>
        </section>
      ))}

      {showDivider ? (
        <div className="flex items-center gap-3 pt-1">
          <span className="h-px flex-1 bg-border/60" />
          <span className="text-xs text-muted-foreground">
            atau masuk manual
          </span>
          <span className="h-px flex-1 bg-border/60" />
        </div>
      ) : null}
    </div>
  )
}

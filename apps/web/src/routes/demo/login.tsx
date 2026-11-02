import { createFileRoute, notFound } from "@tanstack/react-router"
import {
  FlashIcon,
  Shield01Icon,
  UserMultiple02Icon,
} from "@hugeicons/core-free-icons"

import { AuthLayout } from "#/components/auth/auth-layout.tsx"
import {
  DEMO_TENANT,
  DemoQuickLogin,
} from "#/components/auth/demo-quick-login.tsx"
import { TenantLoginForm } from "#/components/auth/tenant-login-form.tsx"

/**
 * Pintu masuk sekali klik untuk akun seed. Hanya hidup saat development:
 * di build produksi `beforeLoad` melempar 404 supaya kredensial seed yang
 * tertulis di berkas ini tidak pernah bisa dipakai dari internet.
 */
export const Route = createFileRoute("/demo/login")({
  beforeLoad: () => {
    if (!import.meta.env.DEV) throw notFound()
  },
  component: DemoLoginPage,
})

function DemoLoginPage() {
  return (
    <AuthLayout
      kicker="Halaman pengembangan"
      title="Masuk tanpa mengetik"
      subtitle="Pilih akun seed di bawah untuk langsung masuk. Halaman ini hanya tersedia saat development."
      points={[
        { icon: FlashIcon, label: "Satu klik, langsung masuk" },
        { icon: Shield01Icon, label: "Mati total di build produksi" },
        { icon: UserMultiple02Icon, label: "Akun dari seeder lokal" },
      ]}
      formHeading="Pilih akun"
      formSubheading="Kredensial sudah terisi otomatis. Tidak perlu mengetik apa pun."
    >
      <div className="mb-6">
        <DemoQuickLogin heading={null} showDivider />
      </div>
      <TenantLoginForm tenant={DEMO_TENANT} />
    </AuthLayout>
  )
}

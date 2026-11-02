import { createFileRoute } from "@tanstack/react-router"
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
 * Pintu masuk sekali klik untuk akun tenant demo.
 *
 * Halaman ini SENGAJA hidup di produksi atas permintaan pemilik produk: tenant
 * `demo` dipakai untuk memperlihatkan aplikasi ke calon klien. Konsekuensinya
 * kredensial akun demo ikut terbaca siapa pun yang membuka halaman ini —
 * jangan pernah menambahkan akun di luar tenant demo ke daftar di bawah.
 */
export const Route = createFileRoute("/demo/login")({
  component: DemoLoginPage,
})

function DemoLoginPage() {
  return (
    <AuthLayout
      kicker="Akun demo"
      title="Masuk tanpa mengetik"
      subtitle="Pilih peran di bawah untuk langsung masuk ke data demo klinik."
      points={[
        { icon: FlashIcon, label: "Satu klik, langsung masuk" },
        { icon: Shield01Icon, label: "Hanya berisi akun tenant demo" },
        { icon: UserMultiple02Icon, label: "Admin, dokter, dan terapis" },
      ]}
      formHeading="Pilih akun"
      formSubheading="Kredensial sudah terisi otomatis. Tidak perlu mengetik apa pun."
    >
      <div className="mb-6">
        {/* Hanya peran klinik: akun platform sengaja tidak dipajang di halaman
            publik, cukup tersedia di dev lewat /central/login. */}
        <DemoQuickLogin kind="clinic" heading={null} showDivider />
      </div>
      <TenantLoginForm tenant={DEMO_TENANT} />
    </AuthLayout>
  )
}

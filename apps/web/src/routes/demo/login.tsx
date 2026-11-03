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
 * Pintu masuk tenant demo.
 *
 * Halaman ini hidup di produksi atas permintaan pemilik produk: tenant `demo`
 * dipakai untuk memperlihatkan aplikasi ke calon klien. Tombol masuk sekali
 * klik hanya dirender di build pengembangan; di server produksi halaman ini
 * cuma menampilkan formulir biasa, sehingga sandi akun demo tidak lagi ikut
 * terkirim ke browser pengunjung.
 */
export const Route = createFileRoute("/demo/login")({
  component: DemoLoginPage,
})

function DemoLoginPage() {
  // Di-inline saat build: `false` di produksi, jadi cabang masuk cepat ikut
  // terbuang dari bundel, bukan sekadar disembunyikan lewat CSS.
  const quickLogin = import.meta.env.DEV

  return (
    <AuthLayout
      kicker="Akun demo"
      title={quickLogin ? "Masuk tanpa mengetik" : "Masuk ke akun demo"}
      subtitle={
        quickLogin
          ? "Pilih peran di bawah untuk langsung masuk ke data demo klinik."
          : "Gunakan akun demo untuk melihat data klinik. Kredensial diberikan oleh tim kami."
      }
      points={[
        { icon: FlashIcon, label: "Alur kerja klinik dari pendaftaran sampai kasir" },
        { icon: Shield01Icon, label: "Hanya berisi akun tenant demo" },
        { icon: UserMultiple02Icon, label: "Admin, dokter, dan terapis" },
      ]}
      formHeading={quickLogin ? "Pilih akun" : "Masuk"}
      formSubheading={
        quickLogin
          ? "Kredensial sudah terisi otomatis. Tidak perlu mengetik apa pun."
          : "Masukkan email dan sandi akun demo Anda."
      }
    >
      {quickLogin ? (
        <div className="mb-6">
          {/* Hanya peran klinik: akun platform sengaja tidak dipajang di halaman
              publik, cukup tersedia di dev lewat /central/login. */}
          <DemoQuickLogin kind="clinic" heading={null} showDivider />
        </div>
      ) : null}
      <TenantLoginForm tenant={DEMO_TENANT} />
    </AuthLayout>
  )
}

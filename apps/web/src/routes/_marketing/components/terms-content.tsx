import {
  LegalList,
  type LegalContact,
  type LegalSection,
} from "#/components/marketing/legal-document.tsx"

export const TERMS_EFFECTIVE_DATE = "2 November 2026"

/** Ketentuan layanan sistem klinik dan kanal pesan WhatsApp. */
export function termsSections(contact: LegalContact): LegalSection[] {
  return [
    {
      id: "definisi-layanan",
      heading: "Definisi layanan",
      body: (
        <p>
          Halaman ini mengatur penggunaan sistem manajemen klinik dan kanal
          pesan WhatsApp yang disediakan {contact.name}. Layanannya mencakup
          pendaftaran pasien, penjadwalan, catatan perawatan, transaksi, dan
          pengiriman pesan kepada pasien. Dengan mengakses atau memakai layanan,
          kamu menyetujui ketentuan ini.
        </p>
      ),
    },
    {
      id: "akun-dan-keamanan",
      heading: "Akun dan keamanan",
      body: (
        <p>
          Setiap staf memakai satu akun sendiri dan wajib menjaga kredensialnya.
          Jangan berbagi kata sandi atau token akses. Laporkan segera ke admin
          klinik bila mencurigai akun dipakai orang lain. Aktivitas yang
          dilakukan lewat akunmu dianggap dilakukan olehmu.
        </p>
      ),
    },
    {
      id: "penggunaan-terlarang",
      heading: "Penggunaan yang dilarang",
      body: (
        <>
          <p>Layanan tidak boleh dipakai untuk:</p>
          <LegalList
            items={[
              "Mengirim spam atau pesan massal kepada penerima yang belum menyetujui.",
              "Mengirim konten menyesatkan, menipu, atau melanggar hukum.",
              "Memakai layanan dengan cara yang melanggar kebijakan WhatsApp dan Meta.",
              "Membuka data pasien tanpa kepentingan layanan.",
              "Mengganggu keamanan atau ketersediaan sistem.",
            ]}
          />
        </>
      ),
    },
    {
      id: "data-pasien",
      heading: "Data pasien",
      body: (
        <p>
          Klinik bertanggung jawab memastikan ada dasar hukum saat mengumpulkan
          dan memakai data pasien, termasuk persetujuan untuk pengingat jadwal
          dan promo. Sistem menyediakan alat untuk itu, tetapi kewajiban
          memenuhinya tetap ada pada klinik.
        </p>
      ),
    },
    {
      id: "biaya",
      heading: "Biaya dan pembayaran",
      body: (
        <p>
          Biaya layanan mengikuti kesepakatan langganan antara klinik dan
          penyedia. Tagihan yang sudah jatuh tempo dapat menghentikan akses ke
          sebagian fitur. Harga yang tercatat di sistem adalah harga yang
          berlaku saat transaksi dibuat.
        </p>
      ),
    },
    {
      id: "kekayaan-intelektual",
      heading: "Kekayaan intelektual",
      body: (
        <p>
          Perangkat lunak, tampilan, dan materi di dalamnya tetap milik penyedia
          layanan. Data yang klinik masukkan tetap milik klinik, dan hanya
          dipakai untuk menjalankan layanan.
        </p>
      ),
    },
    {
      id: "batasan-tanggung-jawab",
      heading: "Batasan tanggung jawab dan force majeure",
      body: (
        <p>
          Layanan disediakan apa adanya. Kami tidak menjamin layanan selalu bebas
          gangguan, dan tidak bertanggung jawab atas kerugian tidak langsung yang
          timbul dari pemakaian layanan, sejauh diizinkan hukum. Kami juga tidak
          bertanggung jawab atas kegagalan yang disebabkan peristiwa di luar
          kendali, seperti gangguan jaringan, bencana, atau perubahan kebijakan
          pihak ketiga termasuk Meta.
        </p>
      ),
    },
    {
      id: "penghentian-layanan",
      heading: "Penghentian layanan",
      body: (
        <p>
          Klinik atau penyedia dapat menghentikan layanan bila ketentuan ini
          dilanggar atau masa langganan berakhir. Setelah berhenti, akses
          ditutup dan data ditangani sesuai kebijakan retensi.
        </p>
      ),
    },
    {
      id: "hukum-berlaku",
      heading: "Hukum yang berlaku",
      body: (
        <p>
          Ketentuan ini tunduk pada hukum Republik Indonesia. Sengketa
          diselesaikan secara musyawarah lebih dulu, dan bila tidak tercapai,
          melalui pengadilan yang berwenang.
        </p>
      ),
    },
    {
      id: "perubahan-ketentuan",
      heading: "Perubahan ketentuan",
      body: (
        <p>
          Ketentuan dapat diperbarui sewaktu-waktu. Versi terbaru selalu ada di
          halaman ini beserta tanggal berlakunya. Pemakaian layanan setelah
          perubahan berarti kamu menyetujui ketentuan yang baru.
        </p>
      ),
    },
  ]
}

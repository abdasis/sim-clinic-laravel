import {
  LegalLink,
  LegalList,
  type LegalContact,
  type LegalSection,
} from "#/components/marketing/legal-document.tsx"

export const PRIVACY_EFFECTIVE_DATE = "2 November 2026"

/**
 * Kebijakan privasi untuk publikasi aplikasi WhatsApp Meta. Isi mengikuti
 * kenyataan produk: SaaS klinik multi-tenant + kanal pesan WhatsApp.
 */
export function privacySections(contact: LegalContact): LegalSection[] {
  return [
    {
      id: "pengendali-data",
      heading: "Pengendali data",
      body: (
        <p>
          Data pribadi yang dijelaskan di halaman ini dikendalikan oleh{" "}
          {contact.name}, beralamat di {contact.address}. Pertanyaan tentang data
          pribadi bisa disampaikan lewat WhatsApp di {contact.phone} atau
          langsung di klinik.
        </p>
      ),
    },
    {
      id: "data-yang-dikumpulkan",
      heading: "Data yang kami kumpulkan",
      body: (
        <LegalList
          items={[
            "Identitas dan kontak: nama, nomor WhatsApp, dan alamat surel bila kamu memberikannya saat mendaftar atau menjadwalkan kunjungan.",
            "Jadwal dan riwayat kunjungan: tanggal, jenis perawatan, dokter atau terapis yang menangani, serta catatan medis yang relevan.",
            "Data transaksi: layanan atau produk yang diambil, nominal, metode pembayaran, dan status pembayaran.",
            "Isi percakapan WhatsApp: pesan yang kamu kirim ke nomor resmi klinik, termasuk lampiran gambar bila ada.",
            "Data teknis: waktu akses, jenis perangkat, dan alamat IP yang tercatat di log sistem untuk keperluan keamanan.",
          ]}
        />
      ),
    },
    {
      id: "tujuan-pemrosesan",
      heading: "Tujuan pemrosesan",
      body: (
        <LegalList
          items={[
            "Mendaftarkan pasien dan mengelola akun pada sistem klinik.",
            "Menjadwalkan, mengubah, dan mengingatkan jadwal kunjungan.",
            "Mengirim informasi promo dan kabar klinik bila kamu menyetujuinya.",
            "Menjawab pertanyaan dan memberikan layanan pelanggan lewat WhatsApp.",
            "Memenuhi kewajiban hukum, audit internal, dan penanganan sengketa.",
          ]}
        />
      ),
    },
    {
      id: "dasar-pemrosesan",
      heading: "Dasar pemrosesan dan persetujuan",
      body: (
        <p>
          Kami memproses data karena dibutuhkan untuk menjalankan layanan yang
          kamu minta, karena kewajiban hukum, atau karena persetujuan yang kamu
          berikan. Persetujuan untuk promo bersifat opsional dan bisa ditarik
          kapan saja: balas pesan WhatsApp dengan permintaan berhenti, atau
          hubungi klinik dan kami akan menghentikan pengirimannya.
        </p>
      ),
    },
    {
      id: "pihak-ketiga",
      heading: "Pembagian data ke pihak ketiga",
      body: (
        <>
          <p>
            Data tidak dijual. Kami hanya membagikannya kepada pihak yang
            diperlukan agar layanan berjalan:
          </p>
          <LegalList
            items={[
              "Meta Platforms, melalui WhatsApp Cloud API, untuk mengirim dan menerima pesan WhatsApp. Pesan yang dikirim ke nomor WhatsApp klinik diproses oleh Meta sesuai kebijakan privasi mereka, lalu disimpan di sistem klinik untuk keperluan layanan.",
              "Penyedia infrastruktur server dan basis data tempat sistem klinik berjalan.",
              "Pihak berwenang, bila diminta secara sah menurut hukum yang berlaku.",
            ]}
          />
        </>
      ),
    },
    {
      id: "penyimpanan",
      heading: "Penyimpanan dan retensi",
      body: (
        <p>
          Data disimpan selama akun atau hubungan layanan masih aktif, dan
          selama diwajibkan oleh hukum. Log audit dan catatan keamanan disimpan
          lebih lama untuk keperluan investigasi dan kepatuhan. Setelah tidak
          lagi diperlukan, data dihapus atau dianonimkan.
        </p>
      ),
    },
    {
      id: "keamanan",
      heading: "Keamanan data",
      body: (
        <p>
          Akses dibatasi berdasarkan peran pengguna; setiap staf hanya bisa
          membuka modul yang menjadi tugasnya. Setiap klinik punya ruang data
          sendiri sehingga data antarklinik tidak tercampur. Token akses dipakai
          untuk autentikasi, dan setiap tindakan yang mengubah data tercatat di
          log audit.
        </p>
      ),
    },
    {
      id: "hak-subjek-data",
      heading: "Hak subjek data",
      body: (
        <p>
          Kamu berhak meminta salinan data pribadimu, meminta koreksi bila ada
          yang keliru, meminta penghapusan, dan menarik persetujuan pemrosesan.
          Ajukan permintaan lewat WhatsApp atau datang langsung ke klinik; kami
          akan memverifikasi identitasmu lebih dulu dan menindaklanjuti dalam
          waktu yang wajar.
        </p>
      ),
    },
    {
      id: "perubahan-kebijakan",
      heading: "Perubahan kebijakan",
      body: (
        <p>
          Kebijakan ini dapat diperbarui bila layanan atau aturan yang berlaku
          berubah. Versi terbaru selalu ditampilkan di halaman ini beserta
          tanggal berlakunya. Perubahan penting kami sampaikan lewat kanal resmi
          klinik.
        </p>
      ),
    },
    {
      id: "kontak-privasi",
      heading: "Kontak",
      body: (
        <p>
          Pertanyaan, keberatan, atau permintaan terkait data pribadi bisa
          disampaikan ke {contact.name} melalui{" "}
          {contact.whatsappUrl ? (
            <LegalLink href={contact.whatsappUrl}>
              WhatsApp {contact.phone}
            </LegalLink>
          ) : (
            `WhatsApp ${contact.phone}`
          )}{" "}
          atau langsung di klinik, {contact.address}.
        </p>
      ),
    },
  ]
}

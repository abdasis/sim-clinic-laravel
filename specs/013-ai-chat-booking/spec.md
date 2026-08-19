# Feature Specification: AI Chat Booking

**Feature Branch**: `013-ai-chat-booking`

**Created**: 2026-08-19

**Status**: Draft

**Input**: User description: "fitur pesan bot dengan waha sekarang sudah sudah berjalan. untuk ai menggunakan deepseek (https://www.deepseek.com/) baca dokumentasi via context7. semua jawaban harus mengacu data yang di database, tidak boleh asal jawab tidak bisa menjawab pertanyaan random dari luar context database yang ada. gunakan function calling. ai chatbot bisa melakukan booking ya jadi user bisa melakukan booking dari chat ai"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Pasien menanyakan info klinik lewat chat WhatsApp (Priority: P1)

Seorang pasien (atau calon pasien) mengirim pesan WhatsApp ke nomor klinik dan menyapa, bertanya hal seperti jam buka, alamat, layanan apa saja yang tersedia, atau tarif sebuah treatment. Sistem WAHA menerima pesan masuk, meneruskannya ke AI, yang lalu memilih apakah menjawab langsung dari data klinik atau memanggil fungsi pencarian data. AI hanya menjawab berdasarkan data klinik dari database — jam operasional, daftar layanan beserta harga, alamat, dan kontak. Jika pertanyaan di luar konteks klinik (mis. berita, cuaca, opini umum), AI menyatakan bahwa ia hanya melayani pertanyaan seputar klinik dan tidak menjawab.

**Why this priority**: Percakapan tanya-jawab adalah fondasi chatbot. Tanpa kemampuan ini, tidak ada interaksi yang bisa dimulai, apalagi berlanjut ke booking. Ini memberi nilai mandiri sekaligus menjadi pintu masuk untuk story berikutnya.

**Independent Test**: Kirim pesan WhatsApp bertanya "Jam buka klinik?" dan "Layanan facial ada berapa harga?" — sistem membalas dengan jawaban konkret dari data klinik. Kirim juga pertanyaan di luar topik ("Berapa skor timnas tadi?") — sistem menolak menjawab dengan kalimat bahwa ia hanya melayani pertanyaan seputar klinik.

**Acceptance Scenarios**:

1. **Given** sesi WAHA klinik aktif dan terhubung, **When** pengirim mengirim pesan "Jam operasional klinik hari ini?", **Then** sistem membalas dalam waktu wajar dengan jam operasional sesuai data klinik.
2. **Given** database klinik berisi daftar layanan beserta harga, **When** pengirim bertanya "Treatment apa saja yang tersedia dan berapa harganya?", **Then** AI memanggil fungsi pencarian layanan dan membalas dengan daftar layanan + tarif yang persis sesuai database.
3. **Given** pengirim bertanya ketersediaan produk, **When** ia mengirim "Stok vitamin C masih ada?", **Then** AI memanggil fungsi pencarian stok produk dan membalas dengan saldo stok produk tersebut (atau menyatakan tidak tersedia bila produk tidak ada), termasuk peringatan bila stok menipis.
4. **Given** pengirim mengirim pertanyaan di luar konteks klinik, **When** AI mengevaluasi pesan tersebut, **Then** sistem membalas bahwa ia hanya bisa menjawab pertanyaan seputar layanan, jam, lokasi, pendaftaran, dan stok produk klinik — bukan topik lain.
4. **Given** nomor pengirim tidak dikenal (tidak terdaftar sebagai pasien klinik), **When** pengirim menanyakan info umum klinik, **Then** sistem tetap menjawab informasi umum (jam, alamat, layanan) tanpa memerlukan akun.

---

### User Story 2 - Pasien melakukan booking janji lewat chat WhatsApp (Priority: P1)

Seorang pasien mengirim pesan seperti "Saya mau booking treatment facial besok sore" atau "Jadwalkan saya untuk dokter Andi hari Sabtu pagi". AI mengekstrak maksud booking, lalu menanyakan data yang masih kurang (siapa pasien, layanan apa, dokter/therapist mana, tanggal, jam) bila belum lengkap. Setelah data lengkap, AI memanggil fungsi booking yang membuat janji di sistem dengan status menunggu, memeriksa ketersediaan slot, dan memberi peringatan bila ada jadwal yang tumpang tindih (sesuai perilaku booking yang sudah ada — overlap hanya peringatan, tidak memblokir). AI lalu membalas konfirmasi ringkas: layanan, dokter/therapist, tanggal & jam, dan status janji.

**Why this priority**: Booking lewat chat adalah nilai inti fitur ini sekaligus tujuan eksplisit user. Ini memanfaatkan function calling DeepSeek untuk mengeksekusi aksi nyata, bukan sekadar mengobrol.

**Independent Test**: Simulasikan pesan "Booking facial untuk besok jam 14 sama dokter Andi" — sistem menanyakan/ melengkapi data kurang, memanggil fungsi booking, lalu membalas konfirmasi. Cek di database bahwa booking baru tercipta dengan pasien, layanan, assignee, dan waktu yang benar.

**Acceptance Scenarios**:

1. **Given** pengirim adalah pasien terdaftar (nomor WhatsApp cocok di database), **When** ia mengirim "Saya mau booking facial besok jam 14", **Then** AI mengidentifikasi pasien dari nomornya, memanggil fungsi booking dengan layanan facial dan waktu yang diminta, dan membalas konfirmasi berisi detail janji.
2. **Given** pesan booking tidak menyebut layanan atau waktu, **When** AI memprosesnya, **Then** AI membalas dengan pertanyaan klarifikasi untuk melengkapi data yang kurang (layanan, dokter/therapist, tanggal, jam) sebelum membuat booking.
3. **Given** data booking sudah lengkap, **When** AI memanggil fungsi booking, **Then** sistem membuat booking baru bermetadata yang sama persis dengan booking manual (pasien, layanan, assignee, start_at, end_at, status menunggu) dan terekam di audit log.
4. **Given** waktu yang diminta sudah ada janji lain untuk dokter/therapist yang sama, **When** booking dibuat, **Then** booking tetap tercipta (overlap non-blocking) namun AI menyertakan peringatan adanya jadwal tumpang tindih dalam balasannya.
5. **Given** pengirim bukan pasien terdaftar, **When** ia meminta booking, **Then** sistem menolak membuat booking dan mengarahkan untuk mendaftar lebih dulu (karena booking butuh identitas pasien).

---

### User Story 4 - Admin klinik mengatur chatbot dan menpersonalisasi agent (Priority: P3)

Admin klinik membuka halaman pengaturan chatbot pada panel klinik. Admin dapat mengaktifkan atau menonaktifkan chatbot, memilih layanan mana saja yang boleh dibooking melalui chat, serta menetapkan nama agent (mis. "Asisten Meba") dan mengunggah avatar agent. Setelah disimpan, chatbot menggunakan nama tersebut untuk menyapa dan menandatangani balasan, dan avatar ditampilkan pada permukaan UI admin. Pengaturan ini hanya berlaku untuk klinik tersebut.

**Why this priority**: Personalisasi dan kontrol memberi admin rasa kepemilikan atas chatbot. Tanpa ini, chatbot berjalan seragam untuk semua klinik. Priority P3 karena inti nilai fitur (tanya-jawab + booking) sudah mandiri tanpa story ini; personalisasi adalah penyempurnaan.

**Independent Test**: Buka halaman pengaturan, nonaktifkan chatbot → pesan masuk tidak diproses/dibalas. Aktifkan kembali, ubah nama agent jadi "Asisten Meba" dan unggah avatar → simpan. Kirim pesan WhatsApp → AI menyapa/menandatangani dengan nama baru.

**Acceptance Scenarios**:

1. **Given** admin klinik masuk ke halaman pengaturan chatbot, **When** ia mengaktifkan/menonaktifkan toggle chatbot dan menyimpan, **Then** status chatbot klinik berubah sesuai — aktif memproses pesan masuk, nonaktif tidak memproses.
2. **Given** admin berada di halaman pengaturan, **When** ia memilih subset layanan yang boleh dibooking via chat dan menyimpan, **Then** AI hanya menawarkan/membookingkan layanan terpilih; layanan lain tetap bisa ditanyakan infonya tetapi tidak bisa dibooking lewat chat.
3. **Given** admin menetapkan nama agent "Asisten Meba", **When** pengirim mengirim pesan WhatsApp, **Then** AI menyapa dan/atau menandatangani balasan menggunakan nama agent tersebut.
4. **Given** admin mengunggah avatar agent, **When** disimpan, **Then** avatar tersimpan per tenant dan ditampilkan pada permukaan UI admin yang relevan.
5. **Given** admin klinik A membuka pengaturan, **When** ia menyimpan perubahan, **Then** perubahan tidak memengaruhi chatbot klinik lain.

AI hanya boleh menjawab berdasarkan data nyata dari database klinik. Bila ditanya sesuatu yang tidak ada datanya (mis. harga layanan yang tidak tersedia, jadwal dokter yang tidak terdaftar), AI tidak boleh mengarang; ia menyatakan bahwa informasi tersebut tidak tersedia atau tidak ditemukan. Setiap jawaban yang menyebut entitas (layanan, dokter, jam, harga) harus dapat ditelusuri ke data. Sistem prompt AI membatasi ruang lingkup ke konteks klinik dan menginstruksikan agar tidak memberi jawaban di luar data yang dipanggil via fungsi.

**Why this priority**: Kepercayaan adalah fondasi adopsi chatbot. Jawaban yang dikarang merusak kepercayaan dan berpotensi menyesatkan pasien. Priority P2 karena perilaku ini sudah terbagi di dua story di atas; story ini merumuskan eksplisit batasan anti-halusinasi sebagai kriteria terverifikasi sendiri.

**Independent Test**: Tanyakan "Berapa harga laser?" padahal klinik tidak punya layanan laser — AI membalas bahwa layanan tersebut tidak tersedia, bukan mengarang harga. Tanyakan dokter yang tidak ada — AI menyatakan tidak ada dokter tersebut.

**Acceptance Scenarios**:

1. **Given** klinik tidak memiliki layanan bernama "laser", **When** pengirim bertanya tarif laser, **Then** AI memanggil fungsi pencarian layanan, tidak menemukan, dan membalas bahwa layanan tersebut tidak tersedia — bukan mengarang harga.
2. **Given** tidak ada dokter/therapist bernama "dr. Budi", **When** pengirim meminta booking dengan dr. Budi, **Then** AI membalas bahwa dokter/therapist tersebut tidak ditemukan dan menawarkan daftar yang tersedia.
3. **Given** AI sedang menyusun jawaban, **When** tidak ada data relevan yang ditemukan oleh fungsi yang dipanggil, **Then** AI tidak mengarang detail dan menyatakan informasi tidak tersedia.

---

### Edge Cases

- Bagaimana sistem menangani pesan WhatsApp berisi media (gambar/dokumen/suara) — diabaikan dengan balasan bahwa hanya pesan teks yang didukung, atau dijawab terbatas?
- Bagaimana sistem menangani pesan masuk saat sesi WAHA klinik sedang terputus — pesan diabaikan atau ditampung?
- Bagaimana jika AI memanggil fungsi booking dengan parameter tidak lengkap/valid (mis. tanggal lampau, assignee bukan dokter/therapist) — ditolak dengan pesan klarifikasi, tidak tercipta booking cacat.
- Bagaimana penanganan ketika API DeepSeek tidak merespons / gagal — balasan fallback ramah agar pasien tidak menyangka diabaikan, disertai log error.
- Bagaimana jika satu nomor mengirim banyak pesan beruntun dalam waktu singkat — adakah pembatasan laju (rate limit) agar tidak membanjiri panggilan API?
- Bagaimana memastikan dua pesan dari pengirim yang sama dalam satu percakapan tetap kontekstual (riwayat percakapan pendek) tanpa bocor ke pengirim lain?
- Bagaimana mencegah booking di masa lampau atau di luar jam operasional klinik?
- Bagaimana menangani pasien dengan nomor WhatsApp ganda (satu keluarga, satu nomor) — identifikasi pasien mana yang sedang mengirim?
- Bagaimana jika admin menonaktifkan chatbot di tengah percakapan berjalan — sesi yang aktif dibiarkan selesai atau dihentikan, dan pesan baru bagaimana?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Sistem WAJIB menerima pesan WhatsApp masuk dari pengirim melalui webhook WAHA dan meneruskannya ke layanan AI untuk diproses.
- **FR-002**: Sistem WAJIB mengidentifikasi pengirim pesan berdasarkan nomor WhatsApp yang dinormalisasi, dan mencocokkannya dengan data pasien klinik bila ada.
- **FR-003**: AI WAJIB membatasi ruang lingkup jawaban hanya pada konteks klinik (layanan, jam operasional, lokasi, stok produk, booking) dan menolak menjawab pertanyaan di luar konteks tersebut.
- **FR-004**: AI WAJIB menggunakan function calling untuk setiap akses data klinik (mencari layanan, mencari dokter/therapist, mencari jam operasional) sehingga jawaban selalu berakar pada data database, bukan pengetahuan umum model.
- **FR-005**: AI WAJIB DILARANG mengarang data yang tidak ada di database; bila data tidak ditemukan, AI menyatakan informasi tersebut tidak tersedia.
- **FR-006**: Sistem WAJIB menyediakan fungsi (tool) pencarian daftar layanan klinik beserta nama dan tarif yang dapat dipanggil AI.
- **FR-007**: Sistem WAJIB menyediakan fungsi (tool) pencarian daftar dokter/therapist klinik yang dapat dipanggil AI.
- **FR-008**: Sistem WAJIB menyediakan fungsi (tool) pencarian jam operasional dan informasi umum klinik (alamat, kontak) yang dapat dipanggil AI.
- **FR-008a**: Sistem WAJIB menyediakan fungsi (tool) pencarian stok produk klinik — nama produk, satuan, saldo stok, dan status stok menipis — yang dapat dipanggil AI.
- **FR-009**: Sistem WAJIB menyediakan fungsi (tool) pembuatan booking yang menerima data pasien, layanan, dokter/therapist, dan waktu; fungsi ini membuat booking dengan metadata yang sama dengan booking manual (status menunggu, overlap non-blocking).
- **FR-010**: AI WAJIB menanyakan klarifikasi bila niat booking tidak lengkap (kurang layanan/dokter/waktu/pasien) sebelum memanggil fungsi booking.
- **FR-011**: Fungsi booking WAJIB menolak membuat janji dengan parameter tidak valid (tanggal lampau, assignee bukan dokter/therapist, layanan tidak ada) dan mengembalikan pesan error yang dapat disampaikan AI ke pengirim.
- **FR-012**: Sistem WAJIB memeriksa ketersediaan slot dan mengembalikan peringatan tumpang tindih jadwal bila ada, tanpa memblokir pembuatan booking (sesuai perilaku booking eksisting).
- **FR-013**: AI WAJIB membalas konfirmasi ringkas setelah booking berhasil dibuat: layanan, dokter/therapist, tanggal & jam, dan status janji.
- **FR-014**: Sistem WAJIB mengirim balasan AI kepada pengirim melalui WAHA pada nomor yang sama dengan pesan masuk.
- **FR-015**: Sistem WAJIB mencatat audit log naratif untuk setiap booking yang dibuat lewat chat AI, termasuk identifikasi bahwa booking berasal dari chatbot.
- **FR-016**: Sistem WAJIB menyimpan riwayat percakapan singkat per pengirim (dalam batas pesan tertentu) agar AI memahami konteks percakapan, dan tidak mencampurkan konteks antar pengirim.
- **FR-017**: Sistem WAJIB membatasi laju pemrosesan pesan per pengirim (rate limit) untuk mencegah penyalahgunaan dan pemborosan panggilan API.
- **FR-018**: Sistem WAJIB menyediakan balasan fallback yang ramah bila AI/API DeepSeek gagal merespons, serta mencatat error tersebut dalam log.
- **FR-019**: Sistem WAJIB mengabaikan pesan media (gambar/dokumen/suara) atau membalas bahwa hanya pesan teks yang didukung, agar tidak memproses pesan yang tidak bisa ditangani AI.
- **FR-020**: Sistem WAJIB mengisolasi data antar tenant — chatbot klinik satu hanya boleh mengakses data klinik itu sendiri; tidak boleh bocor data layanan/dokter/pasien klinik lain.
- **FR-021**: Sistem WAJIB mengaitkan pesan masuk WhatsApp ke tenant berdasarkan nama sesi WAHA yang sudah dikonfigurasi per klinik (field `session` pada pengaturan eksisting) — webhook masuk membawa nama sesi, sistem mencari tenant pemilik sesi tersebut, lalu membatasi akses data chatbot hanya ke klinik itu.
- **FR-022**: Sistem WAJIB menyediakan halaman pengaturan web sederhana untuk admin klinik: mengaktifkan/menonaktifkan chatbot, memilih layanan yang boleh dibooking via chat, serta menetapkan nama agent dan avatar agent yang dipakai chatbot dalam percakapan.
- **FR-023**: Sistem WAJIB memungkinkan admin menetapkan nama agent (mis. nama panggilan chatbot) yang dipakai AI untuk menyapa dan menandatangani balasan, sehingga identitas chatbot klinik dapat dipersonalisasi per tenant.
- **FR-024**: Sistem WAJIB memungkinkan admin mengunggah/menetapkan avatar agent (gambar) yang merepresentasikan chatbot klinik; avatar ini ditampilkan pada permukaan UI admin yang relevan dan dapat dipakai pada media pesan bila didukung.
- **FR-025**: Pengaturan chatbot (status aktif, layanan yang boleh dibooking, nama agent, avatar agent) WAJIB disimpan per tenant dan hanya dapat diubah oleh admin klinik bersangkutan.

### Key Entities *(include if feature involves data)*

- **ChatMessage**: Catatan percakapan — pengirim (nomor WhatsApp), arah (masuk/keluar), isi pesan, timestamp, tenant. Dipakai untuk riwayat percakapan singkat per pengirim.
- **ChatSession**: Sesi percakapan per pengirim per tenant — mengikat riwayat pesan agar konteks tidak tercampur; menyimpan status sesi (aktif/selesai).
- **ChatbotSetting**: Konfigurasi chatbot per tenant — status aktif/nonaktif, daftar layanan yang boleh dibooking via chat, nama agent, avatar agent. Diatur admin klinik melalui halaman pengaturan.
- **Booking** (entitas eksisting): Booking yang dibuat lewat chat tetap menggunakan entitas yang sama; ditandai sebagai berasal dari chatbot agar audit log dapat membedakan.
- **Patient** (entitas eksisting): Identitas pasien yang dicocokkan dari nomor WhatsApp untuk booking.
- **Service** (entitas eksisting): Layanan klinik yang dapat ditanyakan dan dibooking.
- **Product** (entitas eksisting): Produk klinik yang stoknya dapat ditanyakan — nama, satuan, saldo stok, dan status stok menipis.
- **User** (entitas eksisting, sebagai assignee): Dokter/therapist yang dapat ditanyakan ketersediaannya dan ditugaskan untuk booking.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Pengguna mendapat balasan AI untuk pesan teks WhatsApp dalam waktu rata-rata di bawah 15 detik pada kondisi normal.
- **SC-002**: Minimal 90% jawaban AI yang menyebut entitas klinik (layanan, harga, dokter, jam) dapat ditelusuri ke data database tanpa ada informasi yang dikarang.
- **SC-003**: Seorang pasien dapat menyelesaikan booking janji lewat chat WhatsApp dalam rata-rata kurang dari 5 pesan balasan (termasuk klarifikasi dan konfirmasi).
- **SC-004**: Setiap booking yang dibuat lewat chatbot terekam di audit log dengan keterangan naratif dan penanda bahwa booking berasal dari chatbot.
- **SC-005**: Tidak terjadi kebocoran data antar tenant — chatbot klinik A tidak pernah menampilkan data pasien, layanan, atau dokter klinik B.
- **SC-006**: Sistem tetap merespons dengan balasan fallback yang ramah pada minimal 99% kasus kegagalan API DeepSeek atau WAHA, tanpa pengirim merasa diabaikan.

## Assumptions

- WAHA mampu mengirim webhook untuk pesan masuk (inbound webhook) — dokumentasi/fitur WAHA mendukung penerimaan event pesan diterima. Implementasi saat ini hanya outbound, jadi menerima pesan masuk adalah bagian baru yang perlu ditambahkan.
- Server WAHA sudah berjalan dan satu sesi WAHA mewakili satu tenant klinik (satu nomor WhatsApp per klinik), konsisten dengan pola WAHA eksisting.
- API DeepSeek bersifat OpenAI-compatible sehingga function calling didukung lewat parameter `tools` dan respons `tool_calls`, dengan loop pemanggilan ulang hingga tidak ada lagi tool call.
- Setiap klinik hanya memiliki satu nomor WhatsApp yang terhubung ke WAHA, sehingga pesan masuk dapat dikaitkan ke tenant berdasarkan sesi WAHA-nya.
- Data pasien yang dicocokkan menggunakan nomor WhatsApp yang sudah dinormalisasi (format 62xxx) — konsisten dengan field `whatsapp` pada entitas Patient dan helper `PhoneNumber`.
- Aturan booking yang sudah ada tetap berlaku: pasien wajib terdaftar, assignee wajib dokter/therapist, overlap hanya peringatan non-blocking.
- Bahasa percakapan utama adalah Indonesia, mengikuti bahasa UI proyek; AI merespons dalam bahasa yang sama dengan pengirim (utamanya Indonesia).
- Chatbot hanya memproses pesan teks; pesan media di luar ruang lingkup versi pertama.
- Mengelola/konfigurasi chatbot via halaman web admin tidak menjadi kebutuhan versi pertama kecuali klarifikasi menentukan lain; chatbot berjalan otomatis selama sesi WAHA aktif.
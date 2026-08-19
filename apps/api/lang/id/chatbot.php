<?php

return [
    'title' => 'Chatbot WhatsApp',
    'subtitle' => 'Asisten AI yang menjawab pertanyaan pasien dan membuat booking dari chat.',

    // Setelan
    'settings' => 'Pengaturan Chatbot',
    'is_active' => 'Aktifkan chatbot',
    'is_active_hint' => 'Saat mati, pesan masuk tidak dibalas sama sekali.',
    'allow_self_registration' => 'Izinkan pasien mendaftar lewat chat',
    'allow_self_registration_hint' => 'Pasien baru bisa mendaftarkan dirinya sendiri dari WhatsApp, lalu langsung booking. Nomornya diambil dari chat, jadi tidak bisa dipalsukan.',
    'agent_name' => 'Nama Agen',
    'agent_name_hint' => 'Nama yang dipakai chatbot saat memperkenalkan diri ke pasien.',
    'agent_avatar' => 'Avatar Agen',
    'agent_avatar_hint' => 'JPG, PNG, atau WEBP, maksimal 2 MB.',
    'bookable_services' => 'Layanan yang Boleh Dibooking',
    'bookable_services_hint' => 'Kosongkan bila seluruh layanan boleh dibooking lewat chat.',
    'default_agent_name' => 'Asisten Klinik',
    'state_on' => 'Pesan masuk dijawab otomatis oleh asisten AI.',
    'state_off' => 'Pesan masuk tidak dibalas sama sekali.',
    'agent_section' => 'Identitas Agen',
    'agent_section_desc' => 'Nama dan wajah yang ditemui pasien saat mengobrol.',
    'booking_section' => 'Booking lewat Chat',
    'booking_section_desc' => 'Batasi layanan yang boleh dijadwalkan sendiri oleh pasien.',
    'clear_selection' => 'Kosongkan',
    'services_selected' => 'layanan dipilih',
    'no_service_found' => 'Tidak ada layanan yang cocok.',
    'saved' => 'Pengaturan chatbot berhasil disimpan.',
    'avatar_saved' => 'Avatar agen berhasil diperbarui.',

    // Balasan ke pasien
    'fallback' => 'Maaf, saat ini saya sedang kesulitan memproses pesan Anda. Boleh dicoba lagi sebentar lagi, atau hubungi klinik langsung ya.',
    'tool_failed' => 'Data yang diminta sedang tidak bisa diambil.',
    'closing_offer' => 'Ada lagi yang bisa saya bantu? Tinggal chat saja ya kapan saja.',

    // Penolakan booking — ditulis agar bisa langsung dibacakan ke pasien
    // Pendaftaran mandiri
    'registration_disabled' => 'Pendaftaran lewat chat sedang dinonaktifkan. Silakan hubungi klinik untuk mendaftar ya.',
    'already_registered' => 'Nomor ini sudah terdaftar sebagai pasien, jadi tidak perlu mendaftar lagi.',
    'register_patient_success' => 'Pendaftaran atas nama :name berhasil. Sekarang sudah bisa langsung membuat booking.',
    'registration_rate_limited' => 'Percobaan pendaftaran dari nomor ini sudah terlalu sering. Coba lagi satu jam lagi, atau hubungi klinik langsung ya.',
    'registration_name_required' => 'Nama lengkapnya belum jelas. Tanyakan dulu nama pasien sebelum mendaftarkan.',
    'registration_birth_date_invalid' => 'Tanggal lahirnya tidak bisa dibaca atau belum lewat. Sebutkan tanggal, bulan, dan tahunnya ya.',
    'registration_gender_invalid' => 'Jenis kelaminnya tidak dikenali. Isi laki-laki, perempuan, atau kosongkan saja.',
    'registration_invalid' => 'Data pendaftarannya belum lengkap atau tidak bisa dibaca.',
    'register_self_hint' => 'Belum terdaftar? Bisa langsung daftar lewat chat ini.',
    'register_self_confirm' => 'Konfirmasi dulu data pasien sebelum mendaftarkan.',

    'booking_patient_unknown' => 'Nomor ini belum terdaftar sebagai pasien, jadi booking lewat chat belum bisa diproses. Silakan daftar dulu di klinik.',
    'booking_service_unknown' => 'Layanan yang dimaksud tidak ditemukan atau sudah tidak tersedia.',
    'booking_service_not_bookable' => 'Layanan :service belum bisa dibooking lewat chat. Silakan hubungi klinik untuk menjadwalkannya.',
    'booking_staff_unknown' => 'Dokter atau terapis yang dimaksud tidak ditemukan.',
    'booking_time_invalid' => 'Waktu yang diminta tidak bisa dibaca. Sebutkan tanggal dan jamnya ya.',
    'booking_time_past' => 'Waktu yang diminta sudah lewat. Silakan pilih jadwal berikutnya.',
    'booking_outside_hours' => 'Klinik sedang tutup pada jam itu. Silakan pilih waktu lain di dalam jam operasional.',
    'booking_not_found' => 'Booking dengan nomor itu tidak ditemukan.',
    'booking_not_yours' => 'Booking dengan nomor itu tidak ditemukan pada data Anda.',
    'booking_not_cancellable' => 'Booking ini sudah tidak bisa dibatalkan karena statusnya sudah selesai atau memang sudah dibatalkan.',
    'booking_cancelled' => 'Booking Anda sudah dibatalkan.',

    // Pengecekan ketersediaan
    'availability_time_invalid' => 'Waktu yang diminta tidak bisa dibaca. Sebutkan tanggal dan jamnya ya.',
    'availability_time_past' => 'Waktu yang diminta sudah lewat. Silakan pilih jadwal berikutnya.',
    'availability_clinic_closed' => 'Klinik tutup pada jam itu.',
    'availability_staff_busy' => 'Staf yang diminta sudah ada jadwal lain pada jam itu.',
    'availability_no_staff' => 'Semua dokter dan terapis sudah terisi pada jam itu.',
    'no_active_promos' => 'Saat ini belum ada promo yang berjalan.',
    'no_upcoming_bookings' => 'Belum ada jadwal mendatang atas nama Anda.',
];

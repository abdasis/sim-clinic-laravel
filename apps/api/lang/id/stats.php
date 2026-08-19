<?php

return [
    // Label umum komponen statistik
    'module' => 'modul',
    'range' => 'rentang hari',
    'title' => 'Ringkasan',
    'subtitle' => 'Angka penting modul ini dan pergerakannya belakangan.',
    'last_days' => 'Data :days hari terakhir',
    'last_weeks' => 'Data :weeks minggu terakhir',
    'refresh' => 'Muat ulang statistik',
    'refresh_hint' => 'Ambil ulang angka terbaru dari server',
    'loading' => 'Menyiapkan angka...',
    'error_title' => 'Statistik belum bisa ditampilkan',
    'error_description' => 'Sambungan ke server sedang bermasalah. Coba muat ulang sebentar lagi.',
    'retry' => 'Coba lagi',
    'empty_title' => 'Belum ada aktivitas',
    'empty_description' => 'Begitu data pertama masuk, grafiknya langsung ikut bergerak di sini.',
    'chart_hint' => 'Arahkan kursor ke grafik untuk melihat angka per tanggal.',

    // Label tren per metrik
    'trend' => [
        'patients_new' => 'Pasien baru per hari',
        'service_sales' => 'Layanan terjual per hari',
        'product_movements' => 'Pergerakan stok per hari',
        'inventory_net' => 'Selisih stok masuk-keluar per hari',
        'medical_records_new' => 'Rekam medis baru per hari',
        'transactions_revenue' => 'Omzet lunas per hari',
        'bookings_new' => 'Booking dibuat per hari',
        'staff_new' => 'Staff bergabung per hari',
        'tenants_new' => 'Klinik baru per minggu',
        'activity_logs_daily' => 'Aktivitas tercatat per hari',
    ],

    // KPI per modul
    'activity_logs' => [
        'total' => 'Aktivitas tercatat',
        'today' => 'Aktivitas hari ini',
        'actors' => 'Pelaku aktif',
        'modules' => 'Modul tersentuh',
    ],

    'patients' => [
        'total' => 'Total pasien',
        'new_7d' => 'Pasien baru 7 hari',
        'new_30d' => 'Pasien baru 30 hari',
    ],
    'services' => [
        'active' => 'Layanan aktif',
        'archived' => 'Layanan diarsipkan',
        'avg_price' => 'Rata-rata harga',
    ],
    'products' => [
        'total' => 'Total produk',
        'low_stock' => 'Stok menipis',
        'archived' => 'Produk diarsipkan',
    ],
    'inventory' => [
        'movements' => 'Pergerakan stok',
        'total_in' => 'Stok masuk',
        'total_out' => 'Stok keluar',
    ],
    'medical_records' => [
        'total' => 'Total rekam medis',
        'this_week' => 'Dibuat minggu ini',
        'authors' => 'Penulis aktif',
    ],
    'transactions' => [
        'revenue' => 'Omzet lunas',
        'outstanding' => 'Sisa tagihan',
        'paid_count' => 'Transaksi lunas',
        'cancelled_count' => 'Transaksi dibatalkan',
    ],
    'bookings' => [
        'today' => 'Booking hari ini',
        'this_week' => 'Booking minggu ini',
        'pending' => 'Menunggu konfirmasi',
        'done' => 'Sudah selesai',
    ],
    'staff' => [
        'total' => 'Total staff',
        'active' => 'Staff aktif',
        'roles_filled' => 'Peran terisi',
    ],
    'central' => [
        'total' => 'Total klinik',
        'active' => 'Klinik aktif',
        'inactive' => 'Klinik nonaktif',
        'new_this_month' => 'Bergabung bulan ini',
    ],
];

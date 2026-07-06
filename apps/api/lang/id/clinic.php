<?php

return [
    'clinic' => 'Klinik',
    'forbidden' => 'Anda tidak memiliki izin untuk mengakses modul ini.',
    'role' => [
        'admin' => 'Admin',
        'doctor' => 'Dokter',
        'therapist' => 'Terapis',
        'cashier' => 'Kasir',
    ],
    'booking_status' => [
        'pending' => 'Menunggu',
        'confirmed' => 'Dikonfirmasi',
        'done' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ],
    'payment_method' => [
        'cash' => 'Tunai',
        'transfer' => 'Transfer',
        'qris' => 'QRIS',
        'debit' => 'Debit',
    ],
    'payment_status' => [
        'unpaid' => 'Belum Lunas',
        'paid' => 'Lunas',
    ],
    'stock_movement_type' => [
        'in' => 'Stok Masuk',
        'out_manual' => 'Stok Keluar',
        'sold_pos' => 'Terjual (POS)',
        'rollback' => 'Pengembalian',
    ],
    'service_status' => [
        'active' => 'Aktif',
        'archived' => 'Diarsipkan',
    ],
    'medical_photo_type' => [
        'before' => 'Sebelum',
        'after' => 'Sesudah',
    ],
    'duplicate_warning' => 'Nomor telepon ini sudah terdaftar untuk pasien lain.',
    'overlap_warning' => 'Jadwal ini beririsan dengan booking lain untuk staf yang sama.',
    'low_stock' => 'Stok menipis',
    'invalid_transition' => 'Transisi status tidak valid.',
    'insufficient_stock' => 'Stok produk tidak mencukupi.',
    'last_admin' => 'Tidak dapat menonaktifkan admin terakhir.',
];

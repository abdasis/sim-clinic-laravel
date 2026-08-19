<?php

return [
    // Alasan penolakan hapus permanen. Penunjuknya disebut konkret supaya admin
    // tahu harus membereskan apa lebih dulu, bukan sekadar "tidak bisa dihapus".
    'referenced_by_transaction' => 'Tidak bisa dihapus karena sudah terjual di nota :ref. Kalau nota itu memang data percobaan, hapus dulu notanya lewat Kasir - Riwayat Transaksi; kalau bukan, arsipkan saja agar riwayatnya tetap utuh.',
    'referenced_by_booking' => 'Tidak bisa dihapus karena sudah dijadwalkan di booking tanggal :ref. Hapus dulu bookingnya bila data percobaan, atau arsipkan saja.',
    'referenced_by_medical_record' => 'Tidak bisa dihapus karena tercatat di rekam medis pasien tanggal :ref. Arsipkan saja.',
    'referenced_by_promo' => 'Tidak bisa dihapus karena masih terdaftar di promo :ref. Keluarkan dari promo itu dulu, lalu coba hapus lagi.',
];

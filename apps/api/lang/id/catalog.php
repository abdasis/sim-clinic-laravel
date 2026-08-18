<?php

return [
    // Alasan penolakan hapus permanen; disebut spesifik supaya admin tahu
    // harus melihat ke mana, bukan sekadar "tidak bisa dihapus".
    'referenced_by_transaction' => 'Tidak bisa dihapus karena sudah pernah terjual. Arsipkan saja agar riwayat transaksinya tetap utuh.',
    'referenced_by_booking' => 'Tidak bisa dihapus karena sudah pernah dijadwalkan di booking. Arsipkan saja.',
    'referenced_by_medical_record' => 'Tidak bisa dihapus karena tercatat di rekam medis pasien. Arsipkan saja.',
    'referenced_by_promo' => 'Tidak bisa dihapus karena masih terdaftar di promo. Keluarkan dari promo dulu, atau arsipkan saja.',
];

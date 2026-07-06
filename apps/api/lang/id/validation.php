<?php

return [
    'required' => 'Kolom :attribute wajib diisi.',
    'email' => 'Kolom :attribute harus berupa alamat email yang valid.',
    'unique' => 'Kolom :attribute sudah digunakan.',
    'min' => [
        'string' => 'Kolom :attribute minimal :min karakter.',
        'numeric' => 'Kolom :attribute minimal :min.',
    ],
    'max' => [
        'string' => 'Kolom :attribute maksimal :max karakter.',
        'numeric' => 'Kolom :attribute maksimal :max.',
    ],
    'gte' => [
        'numeric' => 'Kolom :attribute tidak boleh kurang dari :value.',
    ],
    'gt' => [
        'numeric' => 'Kolom :attribute harus lebih besar dari :value.',
    ],
    'after' => 'Kolom :attribute harus setelah :date.',
    'before' => 'Kolom :attribute harus sebelum :date.',
    'date' => 'Kolom :attribute bukan tanggal yang valid.',
    'exists' => 'Data :attribute yang dipilih tidak valid.',
    'in' => 'Data :attribute yang dipilih tidak valid.',
    'image' => 'Kolom :attribute harus berupa gambar.',
    'mimes' => 'Kolom :attribute harus berupa file: :values.',
    'regex' => 'Format kolom :attribute tidak valid.',
    'password_complexity' => 'Kata sandi harus mengandung huruf dan angka, minimal 8 karakter.',
];

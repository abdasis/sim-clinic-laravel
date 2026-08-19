<?php

namespace App\Support;

use App\Actions\Chatbot\CancelMyBookingAction;
use App\Actions\Chatbot\CheckAvailabilityAction;
use App\Actions\Chatbot\CreateChatBookingAction;
use App\Actions\Chatbot\GetActivePromosAction;
use App\Actions\Chatbot\GetClinicInfoAction;
use App\Actions\Chatbot\GetProductInfoAction;
use App\Actions\Chatbot\GetProductStockAction;
use App\Actions\Chatbot\ListPatientBookingsAction;
use App\Actions\Chatbot\RegisterPatientAction;
use App\Actions\Chatbot\SearchServicesAction;
use App\Actions\Chatbot\SearchStaffAction;
use App\Models\Patient;
use App\Services\BookingOverlapService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Daftar kemampuan yang boleh dipakai chatbot, beserta cara menjalankannya.
 *
 * Semua jawaban chatbot berakar di sini: AI tidak boleh menyebut harga, jadwal,
 * atau stok yang tidak keluar dari salah satu tool ini. Karena itu tiap tool
 * mengembalikan bentuk yang sudah pasti — daftar kosong tetap daftar kosong,
 * bukan null — supaya "tidak ada" tidak pernah tersamar jadi "belum diperiksa".
 *
 * Kelas ini infra penghubung, bukan Action: ia memilih Action mana yang jalan.
 * Booking jadi satu-satunya yang juga menyentuh BookingOverlapService, karena
 * peringatan bentroknya lahir setelah bookingnya jadi dan tetap tidak memblokir.
 */
class ChatTools
{
    /**
     * Skema tool bergaya OpenAI. Deskripsinya ditulis untuk dibaca AI, jadi
     * sengaja menyebut batasannya sekalian — kapan sebuah tool TIDAK dipakai.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            self::tool(
                'search_services',
                'Cari layanan/treatment yang tersedia di klinik beserta harga dan durasinya. Pakai ini sebelum menyebut harga atau nama treatment apa pun.',
                ['keyword' => ['type' => 'string', 'description' => 'Kata kunci nama layanan. Kosongkan untuk mengambil seluruh layanan aktif.']],
            ),
            self::tool(
                'search_staff',
                'Cari dokter atau terapis yang bisa menangani pasien. Pakai ini untuk mendapatkan id staf sebelum membuat booking.',
                ['keyword' => ['type' => 'string', 'description' => 'Kata kunci nama staf. Kosongkan untuk mengambil seluruh dokter dan terapis aktif.']],
            ),
            self::tool(
                'get_clinic_info',
                'Ambil nama, nomor telepon, alamat, dan jam operasional klinik. Pakai ini sebelum menyebut lokasi atau kontak klinik.',
                [],
            ),
            self::tool(
                'get_product_stock',
                'Periksa ketersediaan dan harga produk skincare di klinik. Pakai ini sebelum menyatakan sebuah produk ada atau habis.',
                ['keyword' => ['type' => 'string', 'description' => 'Kata kunci nama produk. Kosongkan untuk mengambil seluruh produk.']],
            ),
            self::tool(
                'get_product_info',
                'Ambil informasi dan manfaat produk. Pakai ini saat pasien bertanya kegunaan, manfaat, atau detail sebuah produk — BUKAN untuk stok atau harga.',
                ['keyword' => ['type' => 'string', 'description' => 'Kata kunci nama produk. Kosongkan untuk mengambil seluruh produk.']],
            ),
            self::tool(
                'get_active_promos',
                'Ambil promo atau diskon yang sedang berjalan hari ini. Pakai saat pasien bertanya soal promo.',
                [],
            ),
            self::tool(
                'check_availability',
                'Cek apakah slot waktu benar-benar kosong dan klinik buka. WAJIB dipanggil sebelum create_booking. Kosongkan assignee_id untuk mendapatkan daftar staf yang bebas di jam itu.',
                [
                    'service_id' => ['type' => 'integer', 'description' => 'Id layanan dari search_services.'],
                    'assignee_id' => ['type' => 'integer', 'description' => 'Id dokter atau terapis dari search_staff. Kosongkan untuk mencari staf yang bebas.'],
                    'start_at' => ['type' => 'string', 'description' => 'Waktu mulai, format Y-m-d H:i (contoh 2026-09-01 14:00).'],
                ],
                ['service_id', 'start_at'],
            ),
            self::tool(
                'list_my_bookings',
                'Tampilkan booking mendatang milik pasien yang sedang chat. Pakai saat pasien menanyakan jadwalnya, dan untuk mendapatkan booking_id sebelum membatalkan.',
                [],
            ),
            self::tool(
                'cancel_my_booking',
                'Batalkan booking pasien yang sedang chat. Hanya miliknya sendiri dan yang belum selesai. booking_id WAJIB berasal dari list_my_bookings, jangan ditebak.',
                ['booking_id' => ['type' => 'integer', 'description' => 'Id booking dari list_my_bookings.']],
                ['booking_id'],
            ),
            self::tool(
                'create_booking',
                'Buat jadwal booking untuk pasien yang sedang chat. Panggil HANYA jika layanan, staf, dan waktu sudah pasti — tanyakan dulu ke pasien bila ada yang belum jelas.',
                [
                    'service_id' => ['type' => 'integer', 'description' => 'Id layanan dari search_services.'],
                    'assignee_id' => ['type' => 'integer', 'description' => 'Id dokter atau terapis dari search_staff.'],
                    'start_at' => ['type' => 'string', 'description' => 'Waktu mulai, format Y-m-d H:i (contoh 2026-09-01 14:00).'],
                ],
                ['service_id', 'assignee_id', 'start_at'],
            ),
            self::tool(
                'register_patient',
                'Daftarkan pengirim chat sebagai pasien baru klinik. Panggil HANYA setelah pasien menyebut namanya dan menyatakan setuju mendaftar — rangkum dulu datanya dan minta konfirmasi. Nomor WhatsApp diambil otomatis dari pengirim, jangan pernah menanyakannya.',
                [
                    'name' => ['type' => 'string', 'description' => 'Nama lengkap pasien seperti yang ia sebutkan.'],
                    'birth_date' => ['type' => 'string', 'description' => 'Tanggal lahir format Y-m-d. Kosongkan bila pasien tidak menyebutkannya.'],
                    'gender' => ['type' => 'string', 'description' => 'Jenis kelamin: male, female, atau other. Kosongkan bila tidak disebut.'],
                    'address' => ['type' => 'string', 'description' => 'Alamat pasien. Kosongkan bila tidak disebut.'],
                ],
                ['name'],
            ),
        ];
    }

    /**
     * Jalankan satu tool dan kembalikan hasilnya sebagai larik siap-JSON.
     *
     * Nama tool yang tidak dikenal tidak dianggap galat fatal: model kadang
     * mengarang nama, dan yang benar adalah memberitahunya supaya ia mengoreksi
     * diri, bukan menjatuhkan seluruh percakapan.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>|array<int, mixed>
     */
    public static function execute(
        string $name,
        array $arguments,
        ?Patient $patient,
        ?string $senderPhone = null,
    ): array {
        $keyword = is_string($arguments['keyword'] ?? null) ? $arguments['keyword'] : null;

        try {
            return match ($name) {
                'search_services' => app(SearchServicesAction::class)->handle($keyword),
                'search_staff' => app(SearchStaffAction::class)->handle($keyword),
                'get_clinic_info' => app(GetClinicInfoAction::class)->handle(),
                'get_product_stock' => app(GetProductStockAction::class)->handle($keyword),
                'get_product_info' => app(GetProductInfoAction::class)->handle($keyword),
                'get_active_promos' => app(GetActivePromosAction::class)->handle(),
                'check_availability' => app(CheckAvailabilityAction::class)->handle(
                    (int) ($arguments['service_id'] ?? 0),
                    isset($arguments['assignee_id']) ? (int) $arguments['assignee_id'] : null,
                    (string) ($arguments['start_at'] ?? ''),
                ),
                'list_my_bookings' => $patient === null
                    ? ['error' => __('chatbot.booking_patient_unknown')]
                    : app(ListPatientBookingsAction::class)->handle($patient),
                'cancel_my_booking' => $patient === null
                    ? ['error' => __('chatbot.booking_patient_unknown')]
                    : app(CancelMyBookingAction::class)->handle($patient, (int) ($arguments['booking_id'] ?? 0)),
                'create_booking' => self::book($arguments, $patient),
                'register_patient' => self::register($arguments, $patient, $senderPhone),
                default => ['error' => 'Tool tidak dikenal: '.$name],
            };
        } catch (Throwable $e) {
            Log::error('Tool chatbot gagal dijalankan.', [
                'exception' => $e,
                'tool' => $name,
                'arguments' => $arguments,
            ]);

            return ['error' => __('chatbot.tool_failed')];
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function book(array $arguments, ?Patient $patient): array
    {
        if ($patient === null) {
            return ['error' => __('chatbot.booking_patient_unknown')];
        }

        $result = app(CreateChatBookingAction::class)->handle(
            $patient,
            (int) ($arguments['service_id'] ?? 0),
            (int) ($arguments['assignee_id'] ?? 0),
            (string) ($arguments['start_at'] ?? ''),
        );

        if ($result['booking'] === null) {
            return ['error' => $result['error']];
        }

        $booking = $result['booking'];

        return [
            'booking_id' => $booking->id,
            'status' => $booking->status->value,
            'service' => $booking->service?->name,
            'staff' => $booking->assignee?->name,
            'start_at' => $booking->start_at?->format('Y-m-d H:i'),
            // Bentrok jadwal tidak membatalkan booking — klinik memang kerap
            // menumpuk jadwal singkat — tapi pasien berhak diberi tahu.
            'overlap_warnings' => app(BookingOverlapService::class)->detect($booking),
        ];
    }

    /**
     * Nomor pengirim tidak pernah diambil dari $arguments. Itu bukan
     * kehati-hatian berlebih: satu-satunya bukti kepemilikan nomor di sini
     * adalah fakta bahwa pesannya datang dari sana.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function register(array $arguments, ?Patient $patient, ?string $senderPhone): array
    {
        if ($patient !== null) {
            return ['error' => __('chatbot.already_registered')];
        }

        if ($senderPhone === null) {
            return ['error' => __('chatbot.registration_disabled')];
        }

        $result = app(RegisterPatientAction::class)->handle($senderPhone, $arguments);

        if ($result['patient'] === null) {
            return ['error' => $result['error']];
        }

        return [
            'patient_id' => $result['patient']->id,
            'name' => $result['patient']->name,
            'message' => __('chatbot.register_patient_success', ['name' => $result['patient']->name]),
        ];
    }

    /**
     * @param  array<string, array<string, string>>  $properties
     * @param  array<int, string>  $required
     * @return array<string, mixed>
     */
    private static function tool(string $name, string $description, array $properties, array $required = []): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => [
                    'type' => 'object',
                    // Skema OpenAI menolak properties kosong berbentuk larik
                    // biasa; objek kosong yang dikehendaki harus dipaksa.
                    'properties' => $properties === [] ? new \stdClass : $properties,
                    'required' => $required,
                ],
            ],
        ];
    }
}

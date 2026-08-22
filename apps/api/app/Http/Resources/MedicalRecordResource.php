<?php

namespace App\Http\Resources;

use App\Support\PatientPurchases;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicalRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'patient_id' => $this->patient_id,
            'author_id' => $this->author_id,
            'author_name' => $this->author?->name,
            'patient_name' => $this->patient?->name,
            // Catatan walk-in tidak punya kunjungan, jadi relasinya bisa
            // dimuat namun bernilai null — whenLoaded tetap memanggil
            // closure-nya, jadi penjagaannya harus di dalam sini.
            'booking' => $this->whenLoaded('booking', fn () => $this->booking === null ? null : [
                'id' => $this->booking->id,
                'status' => $this->booking->status,
                'start_at' => $this->booking->start_at?->toIso8601String(),
            ]),
            // Nota kunjungan, dipakai tabel riwayat untuk kolom tindakan
            // berbayar dan OBT/HCP. Hanya ikut saat memang dimuat: halaman
            // lain yang memakai resource ini tidak membutuhkannya, dan
            // memuatnya diam-diam berarti satu query per baris.
            'transaction' => $this->transactionPayload($request),
            'anamnesis' => $this->anamnesis,
            'skincare_history' => $this->skincare_history,
            'allergy_history' => $this->allergy_history,
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            // Daftar (index) tidak memuat anak-anaknya; tanpa penjagaan ini
            // tiap baris akan menembak query sendiri.
            'treatments' => $this->relationLoaded('treatmentRecords')
                ? $this->treatmentRecords->map(fn ($treatment) => [
                    'id' => $treatment->id,
                    'service_name' => $treatment->service_name,
                    'notes' => $treatment->notes,
                ])
                : [],
            'photos' => $this->relationLoaded('medicalPhotos')
                ? MedicalPhotoResource::collection($this->medicalPhotos)
                : [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Nota yang menempel pada kunjungan ini.
     *
     * Sumbernya PatientPurchases — pasien dan tanggal, bukan booking. Dulu
     * dibaca dari `booking->transaction`, sehingga produk hanya muncul kalau
     * kasir kebetulan memilih booking saat menjual; catatan walk-in yang
     * memang tidak punya booking tidak pernah bisa menampilkan apa pun.
     *
     * `name` diambil dari baris nota, bukan dari relasi produk/layanan:
     * namanya sudah disalin saat transaksi dibuat, jadi riwayat lama tetap
     * menunjukkan nama yang berlaku waktu itu meski produknya kemudian
     * diganti nama atau dihapus.
     *
     * @return array<string, mixed>|null
     */
    private function transactionPayload(Request $request): ?array
    {
        $purchases = $request->attributes->get('patient_purchases');

        if (! $purchases instanceof PatientPurchases) {
            return null;
        }

        $transactions = $purchases->forRecord($this->resource);

        if ($transactions === []) {
            return null;
        }

        $items = [];
        $performers = [];

        foreach ($transactions as $transaction) {
            foreach ($transaction->items as $item) {
                $items[] = [
                    'name' => $item->name,
                    'kind' => $item->product_id !== null ? 'product' : 'service',
                    'unit_price' => (float) $item->unit_price,
                    'qty' => (int) $item->qty,
                    'subtotal' => (float) $item->subtotal,
                ];
            }

            foreach ($transaction->performers as $performer) {
                $performers[$performer->id] = ['name' => $performer->name];
            }
        }

        return [
            'id' => $transactions[0]->id,
            'performers' => array_values($performers),
            'items' => $items,
        ];
    }
}

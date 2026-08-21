<?php

namespace App\Http\Resources;

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
            'booking' => $this->whenLoaded('booking', fn () => [
                'id' => $this->booking->id,
                'status' => $this->booking->status,
                'start_at' => $this->booking->start_at?->toIso8601String(),
            ]),
            // Nota kunjungan, dipakai tabel riwayat untuk kolom tindakan
            // berbayar dan OBT/HCP. Hanya ikut saat memang dimuat: halaman
            // lain yang memakai resource ini tidak membutuhkannya, dan
            // memuatnya diam-diam berarti satu query per baris.
            'transaction' => $this->transactionPayload(),
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
                ? $this->medicalPhotos->map(fn ($photo) => [
                    'id' => $photo->id,
                    'type' => $photo->type,
                    'type_label' => $photo->type?->label(),
                    'path' => $photo->path,
                    'url' => $photo->url,
                ])
                : [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Nota kunjungan bila ada, null bila catatan ini tidak lahir dari booking
     * berbayar atau relasinya memang tidak dimuat.
     *
     * `name` diambil dari baris nota, bukan dari relasi produk/layanan:
     * namanya sudah disalin saat transaksi dibuat, jadi riwayat lama tetap
     * menunjukkan nama yang berlaku waktu itu meski produknya kemudian
     * diganti nama atau dihapus.
     *
     * @return array<string, mixed>|null
     */
    private function transactionPayload(): ?array
    {
        if (! $this->relationLoaded('booking') || $this->booking === null) {
            return null;
        }

        if (! $this->booking->relationLoaded('transaction') || $this->booking->transaction === null) {
            return null;
        }

        $transaction = $this->booking->transaction;

        return [
            'id' => $transaction->id,
            'performers' => $transaction->relationLoaded('performers')
                ? $transaction->performers->map(fn ($performer) => ['name' => $performer->name])->values()
                : [],
            'items' => $transaction->relationLoaded('items')
                ? $transaction->items->map(fn ($item) => [
                    'name' => $item->name,
                    'kind' => $item->product_id !== null ? 'product' : 'service',
                    'unit_price' => (float) $item->unit_price,
                    'qty' => (int) $item->qty,
                    'subtotal' => (float) $item->subtotal,
                ])->values()
                : [],
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'patient_id' => $this->patient_id,
            'patient_name' => $this->patient?->name,
            'cashier_name' => $this->cashier?->name,
            // Daftar, bukan satu nama: satu kunjungan bisa dikerjakan
            // terapis dan dokter sekaligus.
            'performers' => $this->relationLoaded('performers')
                ? $this->performers->map(fn ($staff) => [
                    'id' => $staff->id,
                    'name' => $staff->name,
                ])->values()
                : [],
            'booking_id' => $this->booking_id,
            'subtotal' => $this->subtotal,
            'paid_amount' => $this->paid_amount,
            'outstanding_amount' => $this->outstandingAmount(),
            'payment_status' => $this->payment_status,
            'payment_status_label' => $this->payment_status?->label(),
            'issued_at' => $this->issued_at?->toIso8601String(),
            'print_count' => $this->print_count,
            'last_printed_at' => $this->last_printed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                // Nota mengelompokkan baris per jenis, seperti nota cetak
                // yang memisahkan tindakan dari produk yang dibawa pulang.
                'kind' => $item->service_id !== null ? 'service' : 'product',
                'unit_price' => $item->unit_price,
                'qty' => $item->qty,
                'subtotal' => $item->subtotal,
                'offered_by' => $item->offered_by,
                'offered_by_name' => $item->relationLoaded('offeredBy')
                    ? $item->offeredBy?->name
                    : null,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

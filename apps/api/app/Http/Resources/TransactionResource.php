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
            'subtotal' => $this->subtotal,
            'payment_status' => $this->payment_status,
            'payment_status_label' => $this->payment_status?->label(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'unit_price' => $item->unit_price,
                'qty' => $item->qty,
                'subtotal' => $item->subtotal,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

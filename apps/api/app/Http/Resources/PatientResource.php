<?php

namespace App\Http\Resources;

use App\Support\PatientReferences;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'gender' => $this->gender,
            'gender_label' => $this->gender ? __('patient.gender_'.$this->gender) : null,
            'whatsapp' => $this->whatsapp,
            'referred_by' => $this->referred_by,
            'referrer_name' => $this->referrer?->name,
            'whatsapp_opt_in' => (bool) $this->whatsapp_opt_in,
            'membership_tier_id' => $this->membership_tier_id,
            'member_since' => $this->member_since?->format('Y-m-d'),
            'member_until' => $this->member_until?->format('Y-m-d'),
            // Keanggotaan yang benar-benar berlaku hari ini, bukan sekadar
            // tingkat yang tercatat: layar kasir memakainya untuk menyebut
            // potongan sebelum tombol bayar ditekan, dan tingkat nonaktif
            // atau masa berlaku yang lewat tidak boleh terlihat sama dengan
            // keanggotaan yang masih hidup.
            'membership' => $this->whenLoaded(
                'membershipTier',
                fn () => $this->activeMembership()
                    ? new MembershipTierResource($this->activeMembership())
                    : null,
            ),
            'membership_tier_name' => $this->membershipTier?->name,
            'loyalty_points' => $this->loyalty_points,
            'address' => $this->address,
            'notes' => $this->notes,
            // Pasien yang sudah punya jejak hanya diarsipkan; dialognya perlu
            // tahu lebih dulu supaya kalimatnya jujur sebelum tombol ditekan.
            'can_delete' => ! $this->references($request)->has($this->id),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function references(Request $request): PatientReferences
    {
        $references = $request->attributes->get('patient_references');

        return $references instanceof PatientReferences ? $references : new PatientReferences;
    }
}

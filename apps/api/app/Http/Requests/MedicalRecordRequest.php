<?php

namespace App\Http\Requests;

use App\Models\Booking;
use App\Rules\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MedicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('medical_record.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            // Kunjungan dan pasien penentu milik siapa catatan ini, jadi
            // keduanya tidak bisa dipindah setelah rekam medisnya dibuat.
            ...$this->isMethod('POST')
                ? $this->creationRules()
                : ['booking_id' => ['prohibited'], 'patient_id' => ['prohibited']],
            'anamnesis' => ['nullable', 'string'],
            'skincare_history' => ['nullable', 'string'],
            'allergy_history' => ['nullable', 'string'],
        ];
    }

    /**
     * Dua jalur masuk, satu formulir (issue #319).
     *
     * Lewat kunjungan, pasiennya mengikuti booking. Tanpa kunjungan
     * (walk-in), pasien wajib disebut — tidak ada sumber lain yang bisa
     * menjawabnya.
     *
     * @return array<string, mixed>
     */
    private function creationRules(): array
    {
        return [
            'booking_id' => ['nullable', TenantRule::exists('bookings')],
            'patient_id' => ['required_without:booking_id', 'nullable', TenantRule::exists('patients')],
        ];
    }

    /**
     * Formulir mengirim pasien terpilih apa adanya, termasuk saat kunjungan
     * ikut dipilih. Yang dijaga di sini bukan keberadaannya melainkan
     * kecocokannya: pasien yang berbeda dari pemilik kunjungan berarti
     * catatan klinis akan mendarat di berkas orang lain.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $bookingId = $this->input('booking_id');
            $patientId = $this->input('patient_id');

            if (! $this->isMethod('POST') || blank($bookingId) || blank($patientId)) {
                return;
            }

            $ownerId = Booking::query()->whereKey($bookingId)->value('patient_id');

            if ($ownerId !== null && (int) $ownerId !== (int) $patientId) {
                $validator->errors()->add('patient_id', __('medical_record.patient_mismatch'));
            }
        });
    }

    public function attributes(): array
    {
        return [
            'booking_id' => __('medical_record.booking'),
            'patient_id' => __('medical_record.patient'),
            'anamnesis' => __('medical_record.anamnesis'),
            'skincare_history' => __('medical_record.skincare_history'),
            'allergy_history' => __('medical_record.allergy_history'),
        ];
    }
}

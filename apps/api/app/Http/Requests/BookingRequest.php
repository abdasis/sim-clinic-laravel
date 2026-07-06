<?php

namespace App\Http\Requests;

use App\Enums\ClinicRole;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class BookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'exists:patients,id'],
            'service_id' => ['required', 'exists:services,id'],
            'assignee_id' => ['required', 'exists:users,id'],
            'start_at' => ['required', 'date', 'after:now'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $assigneeId = $this->input('assignee_id');

            if ($assigneeId === null) {
                return;
            }

            $assignee = User::find($assigneeId);

            if ($assignee === null || ! $assignee->hasClinicRole(ClinicRole::Doctor, ClinicRole::Therapist)) {
                $validator->errors()->add('assignee_id', __('booking.invalid_assignee'));
            }
        });
    }

    public function attributes(): array
    {
        return [
            'patient_id' => __('booking.patient'),
            'service_id' => __('booking.service'),
            'assignee_id' => __('booking.assignee'),
            'start_at' => __('booking.start_at'),
            'end_at' => __('booking.end_at'),
            'notes' => __('booking.notes'),
        ];
    }
}

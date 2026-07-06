<?php

namespace App\Http\Requests;

use App\Enums\MedicalPhotoType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class MedicalPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'type' => ['required', new Enum(MedicalPhotoType::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'file' => __('medical_record.file'),
            'type' => __('medical_record.photo_type'),
        ];
    }
}

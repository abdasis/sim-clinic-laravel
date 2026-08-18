<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('service.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:2048'],
        ];
    }

    public function attributes(): array
    {
        return ['file' => __('import.file')];
    }
}

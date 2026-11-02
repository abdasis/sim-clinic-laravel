<?php

namespace App\Http\Requests;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendInboxMediaRequest extends FormRequest
{
    private const TYPES = ['document', 'image', 'video', 'audio', 'sticker'];

    public function authorize(): bool
    {
        return $this->user()?->can('update', Conversation::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(self::TYPES)],
            'file' => ['required', 'file', ...$this->fileRules()],
            'caption' => ['nullable', 'string', 'max:1024'],
        ];
    }

    /**
     * Batas ukuran mengikuti batas Cloud API Meta per jenis media. Satuan
     * `max` untuk berkas adalah kilobyte.
     *
     * @return array<int, string>
     */
    private function fileRules(): array
    {
        return match ((string) $this->input('type')) {
            'image' => ['mimetypes:image/jpeg,image/png', 'max:5120'],
            'video' => ['mimetypes:video/mp4,video/3gpp', 'max:16384'],
            'audio' => ['mimetypes:audio/aac,audio/mp4,audio/mpeg,audio/amr,audio/ogg', 'max:16384'],
            'document' => [
                'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,'
                    .'application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,'
                    .'application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,'
                    .'text/plain,text/csv',
                'max:102400',
            ],
            'sticker' => ['mimetypes:image/webp', 'max:512'],
            default => [],
        };
    }

    public function attributes(): array
    {
        return [
            'type' => __('inbox.media_type'),
            'file' => __('inbox.media_file'),
            'caption' => __('inbox.media_caption'),
        ];
    }
}

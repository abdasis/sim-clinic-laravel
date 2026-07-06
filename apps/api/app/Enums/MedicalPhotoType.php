<?php

namespace App\Enums;

enum MedicalPhotoType: string
{
    case Before = 'before';
    case After = 'after';

    public function label(): string
    {
        return __('clinic.medical_photo_type.'.$this->value);
    }
}

<?php

namespace Database\Factories;

use App\Models\ChatbotSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatbotSetting>
 */
class ChatbotSettingFactory extends Factory
{
    protected $model = ChatbotSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Aktif secara bawaan di factory — yang menguji chatbot hampir
            // selalu butuh chatbot yang menyala; yang menguji keadaan mati
            // menyebutnya eksplisit lewat state inactive().
            'is_active' => true,
            'agent_name' => 'Asisten '.fake()->firstName(),
            'agent_avatar_path' => null,
            'bookable_service_ids' => null,
            // Mati secara bawaan seperti di produksi: yang mengujinya
            // menyalakannya eksplisit lewat state selfRegistration().
            'allow_self_registration' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function selfRegistration(): static
    {
        return $this->state(fn (): array => ['allow_self_registration' => true]);
    }
}

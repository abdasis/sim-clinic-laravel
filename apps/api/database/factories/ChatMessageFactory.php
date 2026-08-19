<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sender_phone' => '62'.fake()->numerify('81##########'),
            'direction' => 'in',
            'content' => fake()->sentence(),
            'role' => 'user',
            'tool_name' => null,
            'tool_call_id' => null,
        ];
    }

    public function outgoing(): static
    {
        return $this->state(fn (): array => ['direction' => 'out', 'role' => 'assistant']);
    }
}

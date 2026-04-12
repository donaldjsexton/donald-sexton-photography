<?php

namespace Database\Factories;

use App\Models\Inquiry;
use App\Models\InquiryMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InquiryMessage>
 */
class InquiryMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'inquiry_id' => Inquiry::factory(),
            'direction' => 'outbound',
            'body' => fake()->paragraph(),
            'sender_name' => 'Donald Sexton',
            'sender_email' => config('mail.from.address'),
            'sent_at' => now(),
        ];
    }

    public function inbound(): static
    {
        return $this->state(fn () => [
            'direction' => 'inbound',
            'sender_name' => null,
            'sender_email' => null,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Inquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inquiry>
 */
class InquiryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'primary_name' => fake()->firstName().' '.fake()->lastName(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'event_type' => 'wedding',
            'event_date' => fake()->dateTimeBetween('+2 months', '+14 months'),
            'venue_name' => fake()->company().' Estate',
            'location_city' => fake()->randomElement(['Clearwater', 'Tampa', 'St. Petersburg', 'Sarasota']),
            'guest_count_range' => fake()->randomElement(['Under 50', '50-100', '100-150', '150-200', '200+']),
            'message' => fake()->paragraph(),
            'status' => 'new',
            'source' => 'site_form',
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    public function followUp(): static
    {
        return $this->state(fn () => ['status' => 'follow_up']);
    }

    public function booked(): static
    {
        return $this->state(fn () => ['status' => 'booked']);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => 'archived']);
    }
}

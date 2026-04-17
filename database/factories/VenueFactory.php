<?php

namespace Database\Factories;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Estate',
            'slug' => fake()->unique()->slug(3),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'region' => fake()->randomElement(['Clearwater', 'Tampa', 'St. Petersburg', 'Sarasota']),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'city' => fake()->randomElement(['Clearwater', 'Tampa', 'St. Petersburg', 'Sarasota']),
            'state' => 'FL',
            'postal_code' => fake()->postcode(),
            'country' => 'US',
        ];
    }

    public function withPartner(): static
    {
        return $this->state(fn () => [
            'partner_first_name' => fake()->firstName(),
            'partner_last_name' => fake()->lastName(),
        ]);
    }

    public function withPortalAccess(): static
    {
        return $this->state(fn () => [
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
    }
}

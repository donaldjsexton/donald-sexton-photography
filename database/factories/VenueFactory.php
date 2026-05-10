<?php

namespace Database\Factories;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
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

    public function billable(): static
    {
        return $this->state(fn () => [
            'business_name' => fake()->company().' LLC',
            'billing_email' => fake()->unique()->safeEmail(),
            'billing_contact_name' => fake()->name(),
            'billing_address_line_1' => fake()->streetAddress(),
            'billing_city' => fake()->city(),
            'billing_state' => 'FL',
            'billing_postal_code' => fake()->postcode(),
            'billing_country' => 'US',
            'net_payment_terms' => 'Net 30',
        ]);
    }

    public function withPortalAccess(): static
    {
        return $this->billable()->state(fn () => [
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
    }
}

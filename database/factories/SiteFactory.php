<?php

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subdomain = $this->faker->unique()->domainWord();

        return [
            'name' => $this->faker->company(),
            'vendor_type' => 'photographer',
            'subdomain' => $subdomain,
            'primary_domain' => null,
            'is_default' => false,
            'status' => 'active',
        ];
    }

    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }
}

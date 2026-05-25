<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\PortalActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortalActivity>
 */
class PortalActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_type' => Client::class,
            'actor_id' => Client::factory(),
            'type' => PortalActivity::TYPE_LOGIN,
            'subject_type' => null,
            'subject_id' => null,
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => 'Mozilla/5.0',
        ];
    }

    public function contractViewed(): static
    {
        return $this->state(fn () => [
            'type' => PortalActivity::TYPE_CONTRACT_VIEWED,
        ]);
    }

    public function invoiceViewed(): static
    {
        return $this->state(fn () => [
            'type' => PortalActivity::TYPE_INVOICE_VIEWED,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    public function definition(): array
    {
        return [
            'billable_type' => Client::class,
            'billable_id' => Client::factory(),
            'status' => Contract::STATUS_DRAFT,
            'title' => 'Wedding Photography Agreement',
            'body' => "This agreement covers wedding photography services.\n\nTerms and conditions apply.",
            'issue_date' => Carbon::today(),
            'expires_at' => Carbon::today()->addDays(30),
        ];
    }

    public function forVenue(?Venue $venue = null): static
    {
        return $this->state(fn () => [
            'billable_type' => Venue::class,
            'billable_id' => $venue?->id ?? Venue::factory(),
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => Contract::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    public function signed(): static
    {
        return $this->state(fn () => [
            'status' => Contract::STATUS_SIGNED,
            'sent_at' => now()->subDay(),
            'signed_at' => now(),
            'signer_name' => $this->faker->name(),
            'signer_email' => $this->faker->safeEmail(),
            'signer_ip' => $this->faker->ipv4(),
            'signer_user_agent' => 'Mozilla/5.0',
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn () => [
            'status' => Contract::STATUS_DECLINED,
            'sent_at' => now()->subDay(),
            'declined_at' => now(),
        ]);
    }

    public function void(): static
    {
        return $this->state(fn () => [
            'status' => Contract::STATUS_VOID,
            'voided_at' => now(),
        ]);
    }
}

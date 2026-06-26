<?php

namespace Database\Factories;

use App\Models\Gallery;
use App\Models\ShareToken;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShareToken>
 */
class ShareTokenFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token' => Str::random(48),
            'shareable_type' => Gallery::class,
            'shareable_id' => Gallery::factory(),
            'expires_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function passwordProtected(string $password = 'secret'): static
    {
        return $this->state(fn (): array => [
            'password' => $password,
        ]);
    }
}

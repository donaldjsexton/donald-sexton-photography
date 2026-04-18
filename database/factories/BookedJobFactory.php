<?php

namespace Database\Factories;

use App\Models\BookedJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookedJob>
 */
class BookedJobFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstNames = ['Sarah', 'Emily', 'Jessica', 'Amanda', 'Ashley'];
        $partnerNames = ['Michael', 'David', 'James', 'Robert', 'Daniel'];

        $first = fake()->randomElement($firstNames);
        $partner = fake()->randomElement($partnerNames);

        return [
            'google_event_id' => fake()->uuid(),
            'summary' => "{$first} & {$partner} Wedding",
            'couple_names' => "{$first} & {$partner}",
            'event_date' => fake()->dateTimeBetween('now', '+6 months'),
            'event_time' => fake()->randomElement(['10:00 AM', '2:00 PM', '4:30 PM', '5:00 PM', null]),
            'location' => fake()->randomElement(['Hyatt Regency', 'The Breakers', 'Flagler Museum', null]),
            'coordinator' => fake()->randomElement(['FL Destination Weddings', null]),
            'ceremony_notes' => fake()->optional()->paragraph(),
            'status' => 'confirmed',
            'raw_description' => null,
            'synced_at' => now(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled']);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => 'completed',
            'event_date' => fake()->dateTimeBetween('-6 months', '-1 day'),
        ]);
    }
}

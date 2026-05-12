<?php

namespace Database\Factories;

use App\Models\ContractTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractTemplate>
 */
class ContractTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Wedding Default',
            'title' => 'Wedding Photography Agreement',
            'description' => 'Default wedding photography contract.',
            'body' => "This agreement is between {{photographer_name}} and {{client_name}}.\n\nEvent date: {{event_date}}\nEvent location: {{event_location}}\n\nFull terms and conditions apply.",
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}

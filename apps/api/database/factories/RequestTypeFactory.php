<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RequestType;
use App\Models\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestType>
 */
class RequestTypeFactory extends Factory
{
    protected $model = RequestType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->words(2, true)),
            'code' => $this->faker->unique()->slug(2, false),
            'description' => null,
            'icon' => null,
            'color' => '#2678C4',
            'workflow_id' => Workflow::factory(),
            'form_schema' => [
                ['key' => 'reason', 'label' => 'السبب', 'type' => 'textarea', 'required' => true],
            ],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $schema
     */
    public function withSchema(array $schema): static
    {
        return $this->state(fn (array $attributes) => ['form_schema' => $schema]);
    }
}

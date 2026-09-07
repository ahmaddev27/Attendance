<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Setting;
use App\Shared\Enums\SettingType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    protected $model = Setting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(2, false),
            'value' => $this->faker->word(),
            'type' => SettingType::String,
        ];
    }
}

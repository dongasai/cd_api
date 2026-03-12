<?php

namespace Database\Factories;

use App\Enums\SettingGroup;
use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class SystemSettingFactory extends Factory
{
    protected $model = SystemSetting::class;

    public function definition(): array
    {
        return [
            'group' => $this->faker->randomElement(SettingGroup::cases())->value,
            'key' => $this->faker->unique()->word(),
            'value' => $this->faker->word(),
            'type' => $this->faker->randomElement([
                SystemSetting::TYPE_STRING,
                SystemSetting::TYPE_INTEGER,
                SystemSetting::TYPE_BOOLEAN,
            ]),
            'label' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'is_public' => $this->faker->boolean(20),
            'sort_order' => $this->faker->numberBetween(0, 100),
        ];
    }
}
<?php

namespace Database\Factories;

use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class SystemSettingFactory extends Factory
{
    protected $model = SystemSetting::class;

    public function definition(): array
    {
        return [
            'group' => $this->faker->randomElement([
                SystemSetting::GROUP_SYSTEM,
                SystemSetting::GROUP_QUOTA,
                SystemSetting::GROUP_SECURITY,
                SystemSetting::GROUP_FEATURES,
            ]),
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

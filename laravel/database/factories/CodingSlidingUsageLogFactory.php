<?php

namespace Database\Factories;

use App\Models\CodingAccount;
use App\Models\CodingSlidingUsageLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CodingSlidingUsageLog>
 */
class CodingSlidingUsageLogFactory extends Factory
{
    protected $model = CodingSlidingUsageLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => CodingAccount::factory(),
            'window_id' => null,
            'channel_id' => null,
            'request_id' => fake()->uuid(),
            'requests' => fake()->numberBetween(1, 10),
            'tokens_input' => fake()->numberBetween(100, 10000),
            'tokens_output' => fake()->numberBetween(50, 5000),
            'tokens_total' => fn (array $attributes) => $attributes['tokens_input'] + $attributes['tokens_output'],
            'model' => fake()->randomElement(['gpt-4', 'gpt-3.5-turbo', 'claude-3']),
            'model_multiplier' => 1.00,
            'status' => CodingSlidingUsageLog::STATUS_SUCCESS,
            'metadata' => null,
            'created_at' => now(),
        ];
    }

    /**
     * Indicate that the log is for a failed request.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CodingSlidingUsageLog::STATUS_FAILED,
        ]);
    }
}

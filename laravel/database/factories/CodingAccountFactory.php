<?php

namespace Database\Factories;

use App\Models\CodingAccount;
use App\Services\CodingStatus\Drivers\SlidingRequestCodingStatusDriver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CodingAccount>
 */
class CodingAccountFactory extends Factory
{
    protected $model = CodingAccount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'platform' => CodingAccount::PLATFORM_INFINI,
            'driver_class' => SlidingRequestCodingStatusDriver::class,
            'credentials' => [],
            'status' => CodingAccount::STATUS_ACTIVE,
            'quota_config' => [
                'limits' => ['requests' => 1200],
                'window_type' => '5h',
                'thresholds' => [
                    'warning' => 0.80,
                    'critical' => 0.90,
                    'disable' => 0.95,
                ],
            ],
            'quota_cached' => null,
            'config' => [],
            'last_sync_at' => null,
            'sync_error' => null,
            'sync_error_count' => 0,
            'expires_at' => null,
        ];
    }

    /**
     * Indicate that the account is exhausted.
     */
    public function exhausted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CodingAccount::STATUS_EXHAUSTED,
        ]);
    }

    /**
     * Indicate that the account is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CodingAccount::STATUS_EXPIRED,
            'expires_at' => now()->subDay(),
        ]);
    }
}

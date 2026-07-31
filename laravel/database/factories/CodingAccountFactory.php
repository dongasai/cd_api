<?php

namespace Database\Factories;

use App\Models\CodingAccount;
use App\Services\CodingStatus\Drivers\SlidingRequestCodingStatusDriver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CodingAccount>
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
            'config' => [],
            'period_control_enabled' => false,
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

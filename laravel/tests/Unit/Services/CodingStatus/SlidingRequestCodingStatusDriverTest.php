<?php

namespace Tests\Unit\Services\CodingStatus;

use App\Models\CodingAccount;
use App\Models\CodingSlidingUsageLog;
use App\Services\CodingStatus\Drivers\SlidingRequestCodingStatusDriver;
use App\Services\CodingStatus\SlidingWindowRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlidingRequestCodingStatusDriverTest extends TestCase
{
    use RefreshDatabase;

    private SlidingRequestCodingStatusDriver $driver;

    private CodingAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $repository = new SlidingWindowRepository;
        $this->driver = new SlidingRequestCodingStatusDriver($repository);
        $this->account = CodingAccount::factory()->create([
            'platform' => 'infini',
            'driver_class' => SlidingRequestCodingStatusDriver::class,
            'quota_config' => [
                'limits' => ['requests' => 100],
                'window_type' => '5h',
                'thresholds' => [
                    'warning' => 0.80,
                    'critical' => 0.90,
                    'disable' => 0.95,
                ],
            ],
        ]);
        $this->driver->setAccount($this->account);
    }

    public function test_get_name(): void
    {
        $this->assertEquals('SlidingRequestCodingStatus', $this->driver->getName());
    }

    public function test_get_supported_metrics(): void
    {
        $metrics = $this->driver->getSupportedMetrics();

        $this->assertArrayHasKey('requests', $metrics);
    }

    public function test_check_quota_sufficient(): void
    {
        $result = $this->driver->checkQuota(['requests' => 10]);

        $this->assertTrue($result['sufficient']);
    }

    public function test_check_quota_insufficient(): void
    {
        // Create usage near limit
        CodingSlidingUsageLog::create([
            'account_id' => $this->account->id,
            'requests' => 95,
            'created_at' => now()->subHour(),
        ]);

        $result = $this->driver->checkQuota(['requests' => 10]);

        $this->assertFalse($result['sufficient']);
        $this->assertContains('requests', $result['insufficient_metrics']);
    }

    public function test_consume_records_usage(): void
    {
        $this->driver->consume([
            'requests' => 5,
            'model' => 'gpt-4',
        ]);

        $this->assertDatabaseHas('coding_sliding_usage_logs', [
            'account_id' => $this->account->id,
            'requests' => 5,
        ]);
    }

    public function test_get_status_active(): void
    {
        $status = $this->driver->getStatus();

        $this->assertEquals('active', $status['status']);
    }

    public function test_get_status_exhausted(): void
    {
        CodingSlidingUsageLog::create([
            'account_id' => $this->account->id,
            'requests' => 100,
            'created_at' => now()->subHour(),
        ]);

        $status = $this->driver->getStatus();

        $this->assertEquals('exhausted', $status['status']);
    }

    public function test_should_disable_when_exhausted(): void
    {
        CodingSlidingUsageLog::create([
            'account_id' => $this->account->id,
            'requests' => 100,
            'created_at' => now()->subHour(),
        ]);

        $this->assertTrue($this->driver->shouldDisable());
    }

    public function test_period_info_is_sliding(): void
    {
        $periodInfo = $this->driver->getPeriodInfo();

        $this->assertTrue($periodInfo['is_sliding']);
        $this->assertEquals('5h', $periodInfo['type']);
    }
}

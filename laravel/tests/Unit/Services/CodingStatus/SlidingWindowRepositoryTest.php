<?php

namespace Tests\Unit\Services\CodingStatus;

use App\Models\CodingAccount;
use App\Models\CodingSlidingUsageLog;
use App\Services\CodingStatus\SlidingWindowRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlidingWindowRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private SlidingWindowRepository $repository;

    private CodingAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SlidingWindowRepository;
        $this->account = CodingAccount::factory()->create([
            'platform' => 'infini',
            'quota_config' => [
                'limits' => ['requests' => 1200],
                'window_type' => '5h',
            ],
        ]);
    }

    public function test_get_period_info_returns_sliding_window(): void
    {
        $periodInfo = $this->repository->getPeriodInfo('5h');

        $this->assertEquals('5h', $periodInfo['type']);
        $this->assertEquals(5 * 3600, $periodInfo['seconds']);
        $this->assertTrue($periodInfo['is_sliding']);
    }

    public function test_record_usage_creates_log(): void
    {
        $log = $this->repository->recordUsage($this->account, [
            'requests' => 10,
            'tokens_input' => 1000,
            'tokens_output' => 500,
            'tokens_total' => 1500,
            'model' => 'gpt-4',
            'model_multiplier' => 1.0,
        ]);

        $this->assertDatabaseHas('coding_sliding_usage_logs', [
            'id' => $log->id,
            'account_id' => $this->account->id,
            'requests' => 10,
            'tokens_input' => 1000,
        ]);
    }

    public function test_get_request_count_in_window(): void
    {
        // Create usage logs
        CodingSlidingUsageLog::create([
            'account_id' => $this->account->id,
            'requests' => 100,
            'created_at' => now()->subHours(2),
        ]);
        CodingSlidingUsageLog::create([
            'account_id' => $this->account->id,
            'requests' => 50,
            'created_at' => now()->subHours(4),
        ]);
        // This one is outside the window
        CodingSlidingUsageLog::create([
            'account_id' => $this->account->id,
            'requests' => 200,
            'created_at' => now()->subHours(6),
        ]);

        $count = $this->repository->getRequestCountInWindow($this->account, '5h');

        $this->assertEquals(150, $count);
    }

    public function test_get_token_usage_in_window(): void
    {
        CodingSlidingUsageLog::create([
            'account_id' => $this->account->id,
            'tokens_input' => 1000,
            'tokens_output' => 500,
            'tokens_total' => 1500,
            'created_at' => now()->subHours(2),
        ]);

        $usage = $this->repository->getTokenUsageInWindow($this->account, '5h');

        $this->assertEquals(1000, $usage['tokens_input']);
        $this->assertEquals(500, $usage['tokens_output']);
        $this->assertEquals(1500, $usage['tokens_total']);
    }

    public function test_cleanup_expired_records(): void
    {
        // Create old records
        CodingSlidingUsageLog::create([
            'account_id' => $this->account->id,
            'requests' => 100,
            'created_at' => now()->subDays(40),
        ]);
        // Create recent records
        CodingSlidingUsageLog::create([
            'account_id' => $this->account->id,
            'requests' => 50,
            'created_at' => now()->subDays(10),
        ]);

        $deleted = $this->repository->cleanupExpiredRecords(35);

        $this->assertEquals(1, $deleted);
        $this->assertDatabaseCount('coding_sliding_usage_logs', 1);
    }
}

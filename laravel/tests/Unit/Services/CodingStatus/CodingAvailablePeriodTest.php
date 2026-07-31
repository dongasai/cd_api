<?php

namespace Tests\Unit\Services\CodingStatus;

use App\Models\CodingAccount;
use App\Models\CodingAvailablePeriod;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coding 可用时段功能测试
 */
class CodingAvailablePeriodTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 测试正常时段 - 在时段内
     */
    public function test_is_currently_active_within_normal_period(): void
    {
        $account = CodingAccount::factory()->create();
        $period = CodingAvailablePeriod::create([
            'coding_account_id' => $account->id,
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'is_enabled' => true,
        ]);

        // 12:00 在 09:00-18:00 内
        $this->assertTrue($period->isCurrentlyActive(Carbon::parse('2026-07-31 12:00:00')));
        // 09:00 刚好在开始时间
        $this->assertTrue($period->isCurrentlyActive(Carbon::parse('2026-07-31 09:00:00')));
        // 17:59 在结束时间前
        $this->assertTrue($period->isCurrentlyActive(Carbon::parse('2026-07-31 17:59:00')));
    }

    /**
     * 测试正常时段 - 在时段外
     */
    public function test_is_currently_active_outside_normal_period(): void
    {
        $account = CodingAccount::factory()->create();
        $period = CodingAvailablePeriod::create([
            'coding_account_id' => $account->id,
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'is_enabled' => true,
        ]);

        // 08:59 在时段外
        $this->assertFalse($period->isCurrentlyActive(Carbon::parse('2026-07-31 08:59:00')));
        // 18:00 等于结束时间，不在时段内
        $this->assertFalse($period->isCurrentlyActive(Carbon::parse('2026-07-31 18:00:00')));
        // 23:00 在时段外
        $this->assertFalse($period->isCurrentlyActive(Carbon::parse('2026-07-31 23:00:00')));
    }

    /**
     * 测试跨午夜时段 - 22:00-06:00
     */
    public function test_is_currently_active_cross_midnight_period(): void
    {
        $account = CodingAccount::factory()->create();
        $period = CodingAvailablePeriod::create([
            'coding_account_id' => $account->id,
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'is_enabled' => true,
        ]);

        // 23:00 在 22:00-06:00 内
        $this->assertTrue($period->isCurrentlyActive(Carbon::parse('2026-07-31 23:00:00')));
        // 22:00 刚好在开始时间
        $this->assertTrue($period->isCurrentlyActive(Carbon::parse('2026-07-31 22:00:00')));
        // 03:00 在跨午夜时段内
        $this->assertTrue($period->isCurrentlyActive(Carbon::parse('2026-07-31 03:00:00')));
        // 05:59 在结束时间前
        $this->assertTrue($period->isCurrentlyActive(Carbon::parse('2026-07-31 05:59:00')));
        // 12:00 在时段外
        $this->assertFalse($period->isCurrentlyActive(Carbon::parse('2026-07-31 12:00:00')));
        // 06:00 等于结束时间，不在时段内
        $this->assertFalse($period->isCurrentlyActive(Carbon::parse('2026-07-31 06:00:00')));
    }

    /**
     * 测试星期筛选
     */
    public function test_is_currently_active_with_weekdays_filter(): void
    {
        $account = CodingAccount::factory()->create();
        $period = CodingAvailablePeriod::create([
            'coding_account_id' => $account->id,
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'weekdays' => [1, 2, 3, 4, 5], // 周一到周五
            'is_enabled' => true,
        ]);

        // 2026-07-31 是周五 (dayOfWeekIso=5)
        $this->assertTrue($period->isCurrentlyActive(Carbon::parse('2026-07-31 12:00:00')));
        // 2026-08-01 是周六 (dayOfWeekIso=6)
        $this->assertFalse($period->isCurrentlyActive(Carbon::parse('2026-08-01 12:00:00')));
    }

    /**
     * 测试禁用的时段
     */
    public function test_disabled_period_not_active(): void
    {
        $account = CodingAccount::factory()->create([
            'period_control_enabled' => true,
        ]);
        CodingAvailablePeriod::create([
            'coding_account_id' => $account->id,
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'is_enabled' => false,
        ]);

        // 禁用的时段不应被考虑
        $this->assertTrue($account->isWithinAvailablePeriod(Carbon::parse('2026-07-31 08:00:00')));
    }

    /**
     * 测试 CodingAccount 未启用时段控制
     */
    public function test_account_without_period_control_always_available(): void
    {
        $account = CodingAccount::factory()->create([
            'period_control_enabled' => false,
        ]);

        // 未启用时段控制，始终可用
        $this->assertTrue($account->isWithinAvailablePeriod());
    }

    /**
     * 测试 CodingAccount 启用时段控制但无时段配置
     */
    public function test_account_with_period_control_but_no_periods(): void
    {
        $account = CodingAccount::factory()->create([
            'period_control_enabled' => true,
        ]);

        // 无时段配置，视为始终可用
        $this->assertTrue($account->isWithinAvailablePeriod());
    }

    /**
     * 测试 CodingAccount 多时段 - 任一时段匹配即可
     */
    public function test_account_with_multiple_periods(): void
    {
        $account = CodingAccount::factory()->create([
            'period_control_enabled' => true,
        ]);

        CodingAvailablePeriod::create([
            'coding_account_id' => $account->id,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'is_enabled' => true,
        ]);

        CodingAvailablePeriod::create([
            'coding_account_id' => $account->id,
            'start_time' => '14:00:00',
            'end_time' => '18:00:00',
            'is_enabled' => true,
        ]);

        // 10:00 在第一个时段内
        $this->assertTrue($account->isWithinAvailablePeriod(Carbon::parse('2026-07-31 10:00:00')));
        // 15:00 在第二个时段内
        $this->assertTrue($account->isWithinAvailablePeriod(Carbon::parse('2026-07-31 15:00:00')));
        // 13:00 在两个时段之间
        $this->assertFalse($account->isWithinAvailablePeriod(Carbon::parse('2026-07-31 13:00:00')));
        // 20:00 在所有时段外
        $this->assertFalse($account->isWithinAvailablePeriod(Carbon::parse('2026-07-31 20:00:00')));
    }

    /**
     * 测试 isDisabledByPeriodControl
     */
    public function test_is_disabled_by_period_control(): void
    {
        $account = CodingAccount::factory()->create([
            'period_disabled_reason' => 'outside_period',
        ]);

        $this->assertTrue($account->isDisabledByPeriodControl());

        $account2 = CodingAccount::factory()->create([
            'period_disabled_reason' => null,
        ]);

        $this->assertFalse($account2->isDisabledByPeriodControl());
    }

    /**
     * 测试时段显示格式化
     */
    public function test_period_display_format(): void
    {
        $account = CodingAccount::factory()->create();

        $period = CodingAvailablePeriod::create([
            'coding_account_id' => $account->id,
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'weekdays' => null,
        ]);

        $this->assertEquals('09:00-18:00', $period->getPeriodDisplay());

        $periodWithWeekdays = CodingAvailablePeriod::create([
            'coding_account_id' => $account->id,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'weekdays' => [1, 2, 3, 4, 5],
        ]);

        $this->assertEquals('09:00-12:00 (周一,周二,周三,周四,周五)', $periodWithWeekdays->getPeriodDisplay());
    }
}

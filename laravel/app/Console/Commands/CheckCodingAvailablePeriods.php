<?php

namespace App\Console\Commands;

use App\Services\CodingStatus\CodingPeriodControlService;
use Illuminate\Console\Command;

/**
 * 检查Coding账户可用时段，自动禁用/启用关联渠道
 *
 * 遍历所有启用了时段控制的 Coding 账户：
 * - 当前时间在时段外 → 禁用账户及关联渠道
 * - 当前时间在时段内 → 恢复因时段禁用的账户及渠道
 */
class CheckCodingAvailablePeriods extends Command
{
    protected $signature = 'cdapi:coding:check-available-periods';

    protected $description = '检查Coding账户可用时段，自动禁用/启用关联渠道';

    protected CodingPeriodControlService $periodControlService;

    public function __construct(CodingPeriodControlService $periodControlService)
    {
        parent::__construct();
        $this->periodControlService = $periodControlService;
    }

    public function handle(): int
    {
        $this->info('开始检查Coding账户可用时段...');

        $results = $this->periodControlService->checkAllPeriodControlledAccounts();

        $this->newLine();
        $this->info("检查完成: 共 {$results['total']} 个账户启用了时段控制");
        $this->info("  禁用账户: {$results['disabled']} 个，关联渠道: {$results['channels_disabled']} 个");
        $this->info("  启用账户: {$results['enabled']} 个，关联渠道: {$results['channels_enabled']} 个");

        return self::SUCCESS;
    }
}

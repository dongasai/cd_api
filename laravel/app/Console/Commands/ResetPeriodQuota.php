<?php

namespace App\Console\Commands;

use App\Models\CodingAccount;
use App\Models\CodingQuotaUsage;
use App\Services\CodingStatus\ChannelCodingStatusService;
use App\Services\CodingStatus\CodingStatusDriverManager;
use Illuminate\Console\Command;

/**
 * 重置周期配额定时任务
 *
 * 清理过期的配额使用记录，并恢复进入新周期的账户状态
 */
class ResetPeriodQuota extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cdapi:coding:reset-period
                            {--account= : 指定账户ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '检查并执行周期配额重置';

    protected CodingStatusDriverManager $driverManager;

    protected ChannelCodingStatusService $channelService;

    public function __construct(
        CodingStatusDriverManager $driverManager,
        ChannelCodingStatusService $channelService
    ) {
        parent::__construct();
        $this->driverManager = $driverManager;
        $this->channelService = $channelService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = CodingAccount::query();

        // 如果指定了账户ID
        if ($this->option('account')) {
            $query->where('id', $this->option('account'));
        }

        $accounts = $query->get();

        $this->info("检查 {$accounts->count()} 个Coding账户的周期配额...");

        $resetCount = 0;
        $enableCount = 0;

        foreach ($accounts as $account) {
            try {
                $driver = $this->driverManager->driverForAccount($account);
                $periodInfo = $driver->getPeriodInfo();

                // 获取当前周期键
                $currentPeriodKey = $periodInfo['key'] ?? date('Y-m-d');

                // 检查是否需要重置 (通过检查数据库中是否存在当前周期的记录)
                $hasCurrentPeriod = CodingQuotaUsage::where('account_id', $account->id)
                    ->where('period_key', $currentPeriodKey)
                    ->exists();

                // 如果不存在当前周期记录，说明进入新周期
                if (! $hasCurrentPeriod) {
                    $this->info("账户 {$account->name} (ID: {$account->id}) 进入新周期: {$currentPeriodKey}");

                    // 清理过期配额记录
                    $deletedCount = CodingQuotaUsage::where('account_id', $account->id)
                        ->where('period_ends_at', '<', now())
                        ->delete();

                    if ($deletedCount > 0) {
                        $this->info("  已清理 {$deletedCount} 条过期配额记录");
                    }

                    // 如果账户之前是耗尽状态，尝试恢复
                    if ($account->status === CodingAccount::STATUS_EXHAUSTED) {
                        $account->update(['status' => CodingAccount::STATUS_ACTIVE]);
                        $this->info('  账户状态已恢复为活跃');

                        // 触发关联渠道的自动启用
                        $enabledChannels = $this->enableRelatedChannels($account);
                        if ($enabledChannels > 0) {
                            $this->info("  {$enabledChannels} 个关联渠道已自动启用");
                            $enableCount += $enabledChannels;
                        }
                    }

                    $resetCount++;
                }
            } catch (\Exception $e) {
                $this->error("处理账户 {$account->name} 失败: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("处理完成: 重置 {$resetCount} 个账户, 启用 {$enableCount} 个渠道");

        return self::SUCCESS;
    }

    /**
     * 启用关联的渠道
     */
    protected function enableRelatedChannels(CodingAccount $account): int
    {
        $enabledCount = 0;

        // 检查账户是否允许自动启用
        if (! $account->allowsAutoEnable()) {
            return 0;
        }

        foreach ($account->channels as $channel) {
            // 只启用健康状态异常的渠道
            if (! $channel->isHealthNormal()) {
                $result = $this->channelService->manualEnableChannel(
                    $channel,
                    null,
                    '周期配额重置，自动启用'
                );

                if ($result['success']) {
                    $enabledCount++;
                }
            }
        }

        return $enabledCount;
    }
}

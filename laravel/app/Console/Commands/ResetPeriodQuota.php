<?php

namespace App\Console\Commands;

use App\Models\CodingAccount;
use App\Services\CodingStatus\ChannelCodingStatusService;
use App\Services\CodingStatus\CodingStatusDriverManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

/**
 * 重置周期配额定时任务
 *
 * 每分钟检查是否有账户进入新周期，重置配额使用缓存
 */
class ResetPeriodQuota extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coding:reset-period
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

                // 检查是否需要重置 (通过比较存储的周期键)
                $lastPeriodKey = Redis::get("coding:{$account->id}:last_period_key");

                if ($lastPeriodKey !== $currentPeriodKey) {
                    $this->info("账户 {$account->name} (ID: {$account->id}) 进入新周期: {$currentPeriodKey}");

                    // 重置配额使用缓存
                    $this->resetQuotaCache($account->id);

                    // 更新周期键
                    Redis::set("coding:{$account->id}:last_period_key", $currentPeriodKey);

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
     * 重置配额使用缓存
     */
    protected function resetQuotaCache(int $accountId): void
    {
        // 获取所有可能的配额维度
        $metrics = [
            'tokens_input',
            'tokens_output',
            'tokens_total',
            'requests',
            'requests_per_5h',
            'prompts',
            'prompts_per_5h',
            'prompts_per_day',
            'credits',
        ];

        // 删除当前周期的使用量缓存
        foreach ($metrics as $metric) {
            $pattern = "coding:{$accountId}:usage:{$metric}:*";
            $keys = Redis::keys($pattern);
            foreach ($keys as $key) {
                Redis::del($key);
            }
        }

        // 清除其他相关缓存
        Redis::del("coding:{$accountId}:quota_info");
    }

    /**
     * 启用关联的渠道
     */
    protected function enableRelatedChannels(CodingAccount $account): int
    {
        $enabledCount = 0;

        foreach ($account->channels as $channel) {
            if ($channel->allowsAutoEnable() && ! $channel->isActive()) {
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

<?php

namespace App\Console\Commands;

use App\Enums\OperationSource;
use App\Enums\OperationType;
use App\Models\CodingAccount;
use App\Services\OperationLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 自动重新开启被禁用的Coding账户
 *
 * 检查所有被禁用的账户，如果配置了自动重新开启时间且已到期，则自动重新开启
 */
class AutoReopenCodingAccounts extends Command
{
    protected $signature = 'cdapi:coding:auto-reopen';

    protected $description = '自动重新开启被禁用的Coding账户';

    protected OperationLogService $operationLogService;

    public function __construct(OperationLogService $operationLogService)
    {
        parent::__construct();
        $this->operationLogService = $operationLogService;
    }

    public function handle(): int
    {
        $this->info('开始检查需要自动重新开启的Coding账户...');

        $reopenedCount = 0;
        $channelCount = 0;

        $accounts = CodingAccount::whereIn('status', [
            CodingAccount::STATUS_EXHAUSTED,
            CodingAccount::STATUS_SUSPENDED,
        ])
            ->whereNotNull('disabled_at')
            ->get();

        foreach ($accounts as $account) {
            // 检查是否应该自动恢复（优先使用 pause_duration_minutes）
            $shouldReopen = false;
            $reopenReason = '';

            if ($account->pause_duration_minutes && $account->disabled_at) {
                // 错误暂停模式：使用 pause_duration_minutes 计算恢复时间
                $recoverAt = $account->disabled_at->addMinutes($account->pause_duration_minutes);
                if (now()->gte($recoverAt)) {
                    $shouldReopen = true;
                    $reopenReason = '错误暂停时长已到期';
                }
            } elseif ($account->shouldAutoReopen()) {
                // 配额耗尽模式：使用 auto_reopen_hours 计算恢复时间
                $shouldReopen = true;
                $reopenReason = '定时任务自动重新开启';
            }

            if ($shouldReopen) {
                $beforeData = [
                    'status' => $account->status,
                    'disabled_at' => $account->disabled_at?->toDateTimeString(),
                    'pause_duration_minutes' => $account->pause_duration_minutes,
                    'pause_reason' => $account->pause_reason,
                ];

                // 恢复账户
                $account->update([
                    'status' => CodingAccount::STATUS_ACTIVE,
                    'disabled_at' => null,
                    'pause_duration_minutes' => null,
                    'pause_reason' => null,
                    'pause_rule_id' => null,
                ]);

                $reopenedCount++;
                $this->info("[账户 {$account->id}] {$account->name} 已自动重新开启 (原因: {$reopenReason})");

                $afterData = [
                    'status' => CodingAccount::STATUS_ACTIVE,
                    'disabled_at' => null,
                ];

                // 记录Coding账户操作日志
                $this->operationLogService->logCodingAccountOperation(
                    type: OperationType::CODING_ACCOUNT_REOPEN,
                    accountId: $account->id,
                    accountName: $account->name,
                    reason: $reopenReason,
                    beforeData: $beforeData,
                    afterData: $afterData,
                    source: OperationSource::SCHEDULE
                );

                // 同时启用关联的渠道
                $channels = $account->channels()->where('status', 'disabled')->get();
                foreach ($channels as $channel) {
                    $channelBeforeData = [
                        'status' => 'disabled',
                    ];

                    $channel->update(['status' => 'active']);
                    $channelCount++;

                    $channelAfterData = [
                        'status' => 'active',
                    ];

                    // 记录渠道操作日志
                    $this->operationLogService->logChannelOperation(
                        type: OperationType::CHANNEL_ENABLE,
                        channelId: $channel->id,
                        channelName: $channel->name,
                        reason: '账户自动重新开启，渠道同步启用',
                        beforeData: $channelBeforeData,
                        afterData: $channelAfterData,
                        source: OperationSource::SCHEDULE
                    );

                    $this->line("  └─ [渠道 {$channel->id}] {$channel->name} 已自动启用");
                }

                Log::info('Coding账户自动重新开启', [
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'channels_reopened' => $channels->count(),
                    'reason' => $reopenReason,
                ]);
            }
        }

        $this->newLine();
        $this->info("检查完成: {$reopenedCount} 个账户已自动重新开启, {$channelCount} 个渠道已自动启用");

        return self::SUCCESS;
    }
}

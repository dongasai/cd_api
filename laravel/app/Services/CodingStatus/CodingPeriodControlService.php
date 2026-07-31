<?php

namespace App\Services\CodingStatus;

use App\Enums\OperationSource;
use App\Enums\OperationType;
use App\Models\Channel;
use App\Models\CodingAccount;
use App\Services\OperationLogService;
use Illuminate\Support\Facades\Log;

/**
 * Coding 可用时段控制服务
 *
 * 根据账户配置的可用时段，自动禁用/启用关联渠道。
 * 通过 period_disabled_at 字段区分时段禁用和其他禁用原因。
 */
class CodingPeriodControlService
{
    protected OperationLogService $operationLogService;

    public function __construct(OperationLogService $operationLogService)
    {
        $this->operationLogService = $operationLogService;
    }

    /**
     * 检查并更新所有启用了时段控制的账户及其关联渠道
     */
    public function checkAllPeriodControlledAccounts(): array
    {
        $accounts = CodingAccount::where('period_control_enabled', true)
            ->with(['enabledPeriods', 'channels'])
            ->get();

        $results = [
            'total' => $accounts->count(),
            'disabled' => 0,
            'enabled' => 0,
            'channels_disabled' => 0,
            'channels_enabled' => 0,
        ];

        foreach ($accounts as $account) {
            $withinPeriod = $account->isWithinAvailablePeriod();

            if (! $withinPeriod && ! $account->isDisabledByPeriodControl()) {
                // 时段外且尚未因时段禁用 → 禁用
                $this->disableAccountAndChannels($account);
                $results['disabled']++;
                $results['channels_disabled'] += $account->channels->count();
            } elseif ($withinPeriod && $account->isDisabledByPeriodControl()) {
                // 时段内且当前因时段禁用 → 启用
                $this->enableAccountAndChannels($account);
                $results['enabled']++;
                $results['channels_enabled'] += $account->channels->count();
            }
        }

        return $results;
    }

    /**
     * 因时段外禁用账户及关联渠道
     */
    protected function disableAccountAndChannels(CodingAccount $account): void
    {
        $beforeData = [
            'status' => $account->status,
            'period_disabled_reason' => $account->period_disabled_reason,
        ];

        $account->update([
            'status' => CodingAccount::STATUS_SUSPENDED,
            'disabled_at' => now(),
            'period_disabled_reason' => 'outside_period',
        ]);

        $this->operationLogService->logCodingAccountOperation(
            type: OperationType::CODING_ACCOUNT_DISABLE,
            accountId: $account->id,
            accountName: $account->name,
            reason: '可用时段外自动禁用',
            beforeData: $beforeData,
            afterData: [
                'status' => CodingAccount::STATUS_SUSPENDED,
                'period_disabled_reason' => 'outside_period',
            ],
            source: OperationSource::SCHEDULE
        );

        foreach ($account->channels as $channel) {
            $this->disableChannelForPeriod($channel);
        }

        Log::info('Coding账户因时段外自动禁用', [
            'account_id' => $account->id,
            'account_name' => $account->name,
            'channels_count' => $account->channels->count(),
        ]);
    }

    /**
     * 因时段内恢复账户及关联渠道
     */
    protected function enableAccountAndChannels(CodingAccount $account): void
    {
        $beforeData = [
            'status' => $account->status,
            'period_disabled_reason' => $account->period_disabled_reason,
        ];

        $account->update([
            'status' => CodingAccount::STATUS_ACTIVE,
            'disabled_at' => null,
            'period_disabled_reason' => null,
        ]);

        $this->operationLogService->logCodingAccountOperation(
            type: OperationType::CODING_ACCOUNT_REOPEN,
            accountId: $account->id,
            accountName: $account->name,
            reason: '可用时段内自动启用',
            beforeData: $beforeData,
            afterData: [
                'status' => CodingAccount::STATUS_ACTIVE,
                'period_disabled_reason' => null,
            ],
            source: OperationSource::SCHEDULE
        );

        foreach ($account->channels as $channel) {
            $this->enableChannelForPeriod($channel);
        }

        Log::info('Coding账户因时段内自动启用', [
            'account_id' => $account->id,
            'account_name' => $account->name,
            'channels_count' => $account->channels->count(),
        ]);
    }

    /**
     * 禁用渠道（时段外）
     *
     * 只禁用当前处于启用状态的渠道，并标记 period_disabled_at
     */
    protected function disableChannelForPeriod(Channel $channel): void
    {
        if ($channel->status->value !== 1) {
            return;
        }

        $beforeData = ['status' => $channel->status->value];

        $channel->update([
            'status' => 0,
            'period_disabled_at' => now(),
        ]);

        $this->operationLogService->logChannelOperation(
            type: OperationType::CHANNEL_DISABLE,
            channelId: $channel->id,
            channelName: $channel->name,
            reason: '账户可用时段外，渠道自动禁用',
            beforeData: $beforeData,
            afterData: ['status' => 0],
            source: OperationSource::SCHEDULE
        );
    }

    /**
     * 启用渠道（时段内）
     *
     * 只恢复因时段控制禁用的渠道（period_disabled_at 不为 null）
     */
    protected function enableChannelForPeriod(Channel $channel): void
    {
        if ($channel->status->value === 1) {
            return;
        }

        // 只恢复因时段控制禁用的渠道
        if ($channel->period_disabled_at === null) {
            return;
        }

        $beforeData = ['status' => $channel->status->value];

        $channel->update([
            'status' => 1,
            'period_disabled_at' => null,
        ]);

        $this->operationLogService->logChannelOperation(
            type: OperationType::CHANNEL_ENABLE,
            channelId: $channel->id,
            channelName: $channel->name,
            reason: '账户可用时段内，渠道自动启用',
            beforeData: $beforeData,
            afterData: ['status' => 1],
            source: OperationSource::SCHEDULE
        );
    }
}

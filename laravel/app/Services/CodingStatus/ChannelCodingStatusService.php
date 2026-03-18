<?php

namespace App\Services\CodingStatus;

use App\Enums\OperationSource;
use App\Enums\OperationType;
use App\Models\Channel;
use App\Models\CodingAccount;
use App\Models\CodingStatusLog;
use App\Services\OperationLogService;
use Illuminate\Support\Facades\Log;

/**
 * 渠道Coding状态服务
 *
 * 负责管理渠道与Coding账户的关联状态，处理渠道启用/禁用逻辑
 */
class ChannelCodingStatusService
{
    protected CodingStatusDriverManager $driverManager;

    protected OperationLogService $operationLogService;

    public function __construct(
        CodingStatusDriverManager $driverManager,
        OperationLogService $operationLogService
    ) {
        $this->driverManager = $driverManager;
        $this->operationLogService = $operationLogService;
    }

    /**
     * 检查并更新渠道状态
     *
     * 根据Coding账户的配额状态决定是否启用/禁用渠道的健康状态(status2)
     */
    public function checkAndUpdateChannel(Channel $channel): array
    {
        if (! $channel->hasCodingAccount()) {
            return [
                'updated' => false,
                'message' => '渠道未绑定Coding账户',
            ];
        }

        $account = $channel->codingAccount;
        if (! $account) {
            return [
                'updated' => false,
                'message' => 'Coding账户不存在',
            ];
        }

        // 获取驱动
        $driver = $this->driverManager->driverForAccount($account);

        $result = [
            'updated' => false,
            'action' => null,
            'from_status' => $channel->status2,
            'to_status' => $channel->status2,
        ];

        // 检查是否需要禁用健康状态
        if ($channel->isHealthNormal() && $driver->shouldDisable()) {
            if ($account->allowsAutoDisable()) {
                $this->disableChannel($channel, $account, '配额耗尽或账户异常');
                $result['updated'] = true;
                $result['action'] = 'disabled';
                $result['to_status'] = 'disabled';
            } else {
                $result['message'] = '配额耗尽，但账户配置为不自动禁用';
            }
        }

        // 检查是否需要启用健康状态
        if (! $channel->isHealthNormal() && $driver->shouldEnable()) {
            if ($account->allowsAutoEnable()) {
                $this->enableChannel($channel, $account, '配额恢复');
                $result['updated'] = true;
                $result['action'] = 'enabled';
                $result['to_status'] = 'normal';
            } else {
                $result['message'] = '配额恢复，但账户配置为不自动启用';
            }
        }

        return $result;
    }

    /**
     * 禁用渠道健康状态（系统自动）
     */
    public function disableChannel(Channel $channel, CodingAccount $account, string $reason): void
    {
        $fromStatus = $channel->status2;
        $beforeData = [
            'status2' => $fromStatus,
            'status2_remark' => $channel->status2_remark,
            'account_status' => $account->status,
        ];

        // 更新渠道健康状态
        $channel->disableHealth($reason);

        // 更新账户禁用时间
        $account->markAsDisabled(CodingAccount::STATUS_EXHAUSTED);

        $afterData = [
            'status2' => 'disabled',
            'status2_remark' => $reason,
            'account_status' => CodingAccount::STATUS_EXHAUSTED,
        ];

        // 记录状态变更日志
        $this->logStatusChange($account, $channel, $fromStatus, 'disabled', $reason, 'system');

        // 记录操作日志
        $this->operationLogService->logChannelOperation(
            type: OperationType::CHANNEL_DISABLE,
            channelId: $channel->id,
            channelName: $channel->name,
            reason: $reason,
            beforeData: $beforeData,
            afterData: $afterData,
            source: OperationSource::SYSTEM
        );

        Log::info('渠道健康状态已自动禁用', [
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'account_id' => $account->id,
            'reason' => $reason,
        ]);
    }

    /**
     * 启用渠道健康状态（系统自动）
     */
    public function enableChannel(Channel $channel, CodingAccount $account, string $reason): void
    {
        $fromStatus = $channel->status2;
        $beforeData = [
            'status2' => $fromStatus,
            'status2_remark' => $channel->status2_remark,
            'account_status' => $account->status,
        ];

        // 更新渠道健康状态
        $channel->enableHealth();

        // 清除账户禁用时间
        $account->reopen();

        $afterData = [
            'status2' => 'normal',
            'status2_remark' => null,
            'account_status' => CodingAccount::STATUS_ACTIVE,
        ];

        // 记录状态变更日志
        $this->logStatusChange($account, $channel, $fromStatus, 'active', $reason, 'system');

        // 记录操作日志
        $this->operationLogService->logChannelOperation(
            type: OperationType::CHANNEL_ENABLE,
            channelId: $channel->id,
            channelName: $channel->name,
            reason: $reason,
            beforeData: $beforeData,
            afterData: $afterData,
            source: OperationSource::SYSTEM
        );

        Log::info('渠道健康状态已自动启用', [
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'account_id' => $account->id,
            'reason' => $reason,
        ]);
    }

    /**
     * 手动禁用渠道健康状态
     */
    public function manualDisableChannel(Channel $channel, ?int $userId = null, ?string $reason = null): array
    {
        if (! $channel->hasCodingAccount()) {
            return [
                'success' => false,
                'message' => '渠道未绑定Coding账户',
            ];
        }

        $account = $channel->codingAccount;
        if (! $account) {
            return [
                'success' => false,
                'message' => 'Coding账户不存在',
            ];
        }

        $fromStatus = $channel->status2;
        $beforeData = [
            'status2' => $fromStatus,
            'status2_remark' => $channel->status2_remark,
            'account_status' => $account->status,
        ];

        // 更新渠道健康状态
        $channel->disableHealth($reason ?? '手动禁用');

        $afterData = [
            'status2' => 'disabled',
            'status2_remark' => $reason ?? '手动禁用',
        ];

        // 记录状态变更日志
        $this->logStatusChange(
            $account,
            $channel,
            $fromStatus,
            'disabled',
            $reason ?? '手动禁用',
            'manual',
            $userId
        );

        // 记录操作日志
        $this->operationLogService->logChannelOperation(
            type: OperationType::CHANNEL_DISABLE,
            channelId: $channel->id,
            channelName: $channel->name,
            reason: $reason ?? '手动禁用',
            beforeData: $beforeData,
            afterData: $afterData,
            source: OperationSource::ADMIN,
            userId: $userId
        );

        return [
            'success' => true,
            'message' => '渠道健康状态已手动禁用',
        ];
    }

    /**
     * 手动启用渠道健康状态
     */
    public function manualEnableChannel(Channel $channel, ?int $userId = null, ?string $reason = null): array
    {
        if (! $channel->hasCodingAccount()) {
            return [
                'success' => false,
                'message' => '渠道未绑定Coding账户',
            ];
        }

        $account = $channel->codingAccount;
        if (! $account) {
            return [
                'success' => false,
                'message' => 'Coding账户不存在',
            ];
        }

        // 检查配额状态
        $driver = $this->driverManager->driverForAccount($account);
        $status = $driver->getStatus();

        // 如果配额仍然耗尽，给出警告
        if ($status['status'] === CodingAccount::STATUS_EXHAUSTED) {
            return [
                'success' => false,
                'message' => 'Coding账户配额已耗尽，无法启用渠道健康状态',
                'quota_status' => $status,
            ];
        }

        $fromStatus = $channel->status2;
        $beforeData = [
            'status2' => $fromStatus,
            'status2_remark' => $channel->status2_remark,
            'account_status' => $account->status,
        ];

        // 更新渠道健康状态
        $channel->enableHealth();

        $afterData = [
            'status2' => 'normal',
            'status2_remark' => null,
        ];

        // 记录状态变更日志
        $this->logStatusChange(
            $account,
            $channel,
            $fromStatus,
            'active',
            $reason ?? '手动启用',
            'manual',
            $userId
        );

        // 记录操作日志
        $this->operationLogService->logChannelOperation(
            type: OperationType::CHANNEL_ENABLE,
            channelId: $channel->id,
            channelName: $channel->name,
            reason: $reason ?? '手动启用',
            beforeData: $beforeData,
            afterData: $afterData,
            source: OperationSource::ADMIN,
            userId: $userId
        );

        return [
            'success' => true,
            'message' => '渠道健康状态已手动启用',
        ];
    }

    /**
     * 检查请求是否允许
     *
     * 在请求处理前调用，检查配额是否充足
     */
    public function checkRequestAllowed(Channel $channel, array $context): array
    {
        if (! $channel->hasCodingAccount()) {
            return [
                'allowed' => true,
                'message' => '渠道未绑定Coding账户，直接放行',
            ];
        }

        $account = $channel->codingAccount;
        if (! $account) {
            return [
                'allowed' => false,
                'message' => 'Coding账户不存在',
                'code' => 'ACCOUNT_NOT_FOUND',
            ];
        }

        // 检查账户状态
        if ($account->status === CodingAccount::STATUS_EXHAUSTED) {
            return [
                'allowed' => false,
                'message' => 'Coding账户配额已耗尽',
                'code' => 'QUOTA_EXHAUSTED',
            ];
        }

        if ($account->status === CodingAccount::STATUS_EXPIRED) {
            return [
                'allowed' => false,
                'message' => 'Coding账户已过期',
                'code' => 'ACCOUNT_EXPIRED',
            ];
        }

        if ($account->status === CodingAccount::STATUS_SUSPENDED) {
            return [
                'allowed' => false,
                'message' => 'Coding账户已暂停',
                'code' => 'ACCOUNT_SUSPENDED',
            ];
        }

        if ($account->status === CodingAccount::STATUS_ERROR) {
            return [
                'allowed' => false,
                'message' => 'Coding账户状态异常',
                'code' => 'ACCOUNT_ERROR',
            ];
        }

        // 获取驱动并检查配额
        $driver = $this->driverManager->driverForAccount($account);
        $quotaCheck = $driver->checkQuota($context);

        if (! $quotaCheck['sufficient']) {
            return [
                'allowed' => false,
                'message' => '配额不足: '.implode(', ', $quotaCheck['insufficient_metrics']),
                'code' => 'QUOTA_INSUFFICIENT',
                'details' => $quotaCheck,
            ];
        }

        return [
            'allowed' => true,
            'message' => '配额充足',
            'details' => $quotaCheck,
        ];
    }

    /**
     * 记录配额使用
     *
     * 在请求处理成功后调用
     */
    public function recordUsage(Channel $channel, array $usage): void
    {
        if (! $channel->hasCodingAccount()) {
            return;
        }

        $account = $channel->codingAccount;
        if (! $account) {
            return;
        }

        // 添加渠道ID到使用量数据
        $usage['channel_id'] = $channel->id;

        // 获取驱动并记录使用
        $driver = $this->driverManager->driverForAccount($account);
        $driver->consume($usage);

        // 异步检查并更新渠道状态
        // 这里可以触发一个事件或队列任务
    }

    /**
     * 获取渠道Coding状态
     */
    public function getChannelCodingStatus(Channel $channel): array
    {
        if (! $channel->hasCodingAccount()) {
            return [
                'has_coding_account' => false,
                'message' => '渠道未绑定Coding账户',
            ];
        }

        $account = $channel->codingAccount;
        if (! $account) {
            return [
                'has_coding_account' => false,
                'message' => 'Coding账户不存在',
            ];
        }

        $driver = $this->driverManager->driverForAccount($account);

        return [
            'has_coding_account' => true,
            'channel_id' => $channel->id,
            'channel_status' => $channel->status,
            'channel_health_status' => $channel->status2,
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'platform' => $account->platform,
                'driver' => $account->driver_class,
                'status' => $account->status,
                'last_check_at' => $account->last_check_at?->toDateTimeString(),
            ],
            'quota' => $driver->getQuotaInfo(),
            'override' => $account->getStatusOverride(),
        ];
    }

    /**
     * 记录状态变更日志
     */
    protected function logStatusChange(
        CodingAccount $account,
        Channel $channel,
        string $fromStatus,
        string $toStatus,
        string $reason,
        string $triggeredBy,
        ?int $userId = null
    ): void {
        CodingStatusLog::create([
            'account_id' => $account->id,
            'channel_id' => $channel->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'quota_snapshot' => $account->quota_cached,
            'triggered_by' => $triggeredBy,
            'user_id' => $userId,
            'created_at' => now(),
        ]);
    }

    /**
     * 获取所有绑定Coding账户的渠道
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Channel>
     */
    public function getChannelsWithCodingAccount()
    {
        return Channel::whereNotNull('coding_account_id')
            ->with('codingAccount')
            ->get();
    }

    /**
     * 批量检查并更新所有渠道状态
     *
     * 按账户分组检查，避免重复检查同一账户
     *
     * @return array<string, mixed>
     */
    public function batchCheckAndUpdate(): array
    {
        // 获取所有绑定Coding账户的渠道，按账户分组
        $channels = $this->getChannelsWithCodingAccount();

        // 按账户ID分组
        $channelsByAccount = $channels->groupBy('coding_account_id');

        $results = [];
        $accountCheckResults = [];

        // 先检查每个账户（避免重复检查）
        foreach ($channelsByAccount as $accountId => $accountChannels) {
            $firstChannel = $accountChannels->first();
            if (! $firstChannel || ! $firstChannel->codingAccount) {
                continue;
            }

            $account = $firstChannel->codingAccount;

            try {
                $accountCheckResults[$accountId] = $this->checkAccountIfNeeded($account);
            } catch (\Exception $e) {
                Log::error('检查账户状态失败', [
                    'account_id' => $accountId,
                    'error' => $e->getMessage(),
                ]);
                $accountCheckResults[$accountId] = [
                    'updated' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // 根据账户检查结果更新所有关联渠道
        foreach ($channels as $channel) {
            $accountId = $channel->coding_account_id;
            if (! isset($accountCheckResults[$accountId])) {
                $results[$channel->id] = [
                    'updated' => false,
                    'message' => '账户检查结果不存在',
                ];

                continue;
            }

            $accountResult = $accountCheckResults[$accountId];

            // 如果账户检查触发了状态变更，需要更新所有关联渠道
            if ($accountResult['updated'] ?? false) {
                try {
                    $result = $this->checkAndUpdateChannel($channel);
                    $results[$channel->id] = $result;
                } catch (\Exception $e) {
                    Log::error('检查渠道状态失败', [
                        'channel_id' => $channel->id,
                        'error' => $e->getMessage(),
                    ]);
                    $results[$channel->id] = [
                        'updated' => false,
                        'error' => $e->getMessage(),
                    ];
                }
            } else {
                $results[$channel->id] = $accountResult;
            }
        }

        return [
            'total' => $channels->count(),
            'accounts' => count($accountCheckResults),
            'results' => $results,
        ];
    }

    /**
     * 检查账户是否需要检查（根据驱动的检查间隔）
     */
    protected function checkAccountIfNeeded(CodingAccount $account): array
    {
        $driver = $this->driverManager->driverForAccount($account);
        $checkInterval = $driver->getCheckInterval();

        $lastCheckAt = $account->last_check_at ?? null;

        if ($lastCheckAt) {
            $nextCheckAt = \Carbon\Carbon::parse($lastCheckAt)->addSeconds($checkInterval);
            if (now()->lt($nextCheckAt)) {
                return [
                    'updated' => false,
                    'message' => "距下次检查还有 {$nextCheckAt->diffInSeconds(now())} 秒",
                    'check_interval' => $checkInterval,
                    'next_check_at' => $nextCheckAt->toDateTimeString(),
                ];
            }
        }

        // 更新最后检查时间
        $account->updateLastCheckAt();

        return [
            'updated' => true,
            'message' => '账户需要检查',
        ];
    }
}

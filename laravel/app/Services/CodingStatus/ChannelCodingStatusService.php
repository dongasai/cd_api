<?php

namespace App\Services\CodingStatus;

use App\Models\Channel;
use App\Models\CodingAccount;
use App\Models\CodingStatusLog;
use App\Services\CodingStatus\Drivers\CodingStatusDriver;
use Illuminate\Support\Facades\Log;

/**
 * 渠道Coding状态服务
 *
 * 负责管理渠道与Coding账户的关联状态，处理渠道启用/禁用逻辑
 */
class ChannelCodingStatusService
{
    protected CodingStatusDriverManager $driverManager;

    public function __construct(CodingStatusDriverManager $driverManager)
    {
        $this->driverManager = $driverManager;
    }

    /**
     * 检查并更新渠道状态
     *
     * 根据Coding账户的配额状态决定是否启用/禁用渠道
     */
    public function checkAndUpdateChannel(Channel $channel): array
    {
        if (!$channel->hasCodingAccount()) {
            return [
                'updated' => false,
                'message' => '渠道未绑定Coding账户',
            ];
        }

        $account = $channel->codingAccount;
        if (!$account) {
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
            'from_status' => $channel->status,
            'to_status' => $channel->status,
        ];

        // 检查是否需要禁用
        if ($channel->isActive() && $driver->shouldDisable()) {
            if ($channel->allowsAutoDisable()) {
                $this->disableChannel($channel, $account, '配额耗尽或账户异常');
                $result['updated'] = true;
                $result['action'] = 'disabled';
                $result['to_status'] = 'disabled';
            } else {
                $result['message'] = '配额耗尽，但渠道配置为不自动禁用';
            }
        }

        // 检查是否需要启用
        if (!$channel->isActive() && $driver->shouldEnable()) {
            if ($channel->allowsAutoEnable()) {
                $this->enableChannel($channel, $account, '配额恢复');
                $result['updated'] = true;
                $result['action'] = 'enabled';
                $result['to_status'] = 'active';
            } else {
                $result['message'] = '配额恢复，但渠道配置为不自动启用';
            }
        }

        return $result;
    }

    /**
     * 禁用渠道
     */
    public function disableChannel(Channel $channel, CodingAccount $account, string $reason): void
    {
        $fromStatus = $channel->status;

        // 更新渠道状态
        $channel->update(['status' => 'disabled']);

        // 记录状态变更日志
        $this->logStatusChange($account, $channel, $fromStatus, 'disabled', $reason, 'system');

        Log::info('渠道已自动禁用', [
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'account_id' => $account->id,
            'reason' => $reason,
        ]);
    }

    /**
     * 启用渠道
     */
    public function enableChannel(Channel $channel, CodingAccount $account, string $reason): void
    {
        $fromStatus = $channel->status;

        // 更新渠道状态
        $channel->update(['status' => 'active']);

        // 记录状态变更日志
        $this->logStatusChange($account, $channel, $fromStatus, 'active', $reason, 'system');

        Log::info('渠道已自动启用', [
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'account_id' => $account->id,
            'reason' => $reason,
        ]);
    }

    /**
     * 手动禁用渠道
     */
    public function manualDisableChannel(Channel $channel, ?int $userId = null, ?string $reason = null): array
    {
        if (!$channel->hasCodingAccount()) {
            return [
                'success' => false,
                'message' => '渠道未绑定Coding账户',
            ];
        }

        $account = $channel->codingAccount;
        if (!$account) {
            return [
                'success' => false,
                'message' => 'Coding账户不存在',
            ];
        }

        $fromStatus = $channel->status;

        // 更新渠道状态
        $channel->update(['status' => 'disabled']);

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

        return [
            'success' => true,
            'message' => '渠道已手动禁用',
        ];
    }

    /**
     * 手动启用渠道
     */
    public function manualEnableChannel(Channel $channel, ?int $userId = null, ?string $reason = null): array
    {
        if (!$channel->hasCodingAccount()) {
            return [
                'success' => false,
                'message' => '渠道未绑定Coding账户',
            ];
        }

        $account = $channel->codingAccount;
        if (!$account) {
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
                'message' => 'Coding账户配额已耗尽，无法启用渠道',
                'quota_status' => $status,
            ];
        }

        $fromStatus = $channel->status;

        // 更新渠道状态
        $channel->update(['status' => 'active']);

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

        return [
            'success' => true,
            'message' => '渠道已手动启用',
        ];
    }

    /**
     * 检查请求是否允许
     *
     * 在请求处理前调用，检查配额是否充足
     */
    public function checkRequestAllowed(Channel $channel, array $context): array
    {
        if (!$channel->hasCodingAccount()) {
            return [
                'allowed' => true,
                'message' => '渠道未绑定Coding账户，直接放行',
            ];
        }

        $account = $channel->codingAccount;
        if (!$account) {
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

        if (!$quotaCheck['sufficient']) {
            return [
                'allowed' => false,
                'message' => '配额不足: ' . implode(', ', $quotaCheck['insufficient_metrics']),
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
        if (!$channel->hasCodingAccount()) {
            return;
        }

        $account = $channel->codingAccount;
        if (!$account) {
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
        if (!$channel->hasCodingAccount()) {
            return [
                'has_coding_account' => false,
                'message' => '渠道未绑定Coding账户',
            ];
        }

        $account = $channel->codingAccount;
        if (!$account) {
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
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'platform' => $account->platform,
                'driver' => $account->driver_class,
                'status' => $account->status,
            ],
            'quota' => $driver->getQuotaInfo(),
            'override' => $channel->getCodingStatusOverride(),
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
     * @return array<string, mixed>
     */
    public function batchCheckAndUpdate(): array
    {
        $channels = $this->getChannelsWithCodingAccount();
        $results = [];

        foreach ($channels as $channel) {
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
        }

        return [
            'total' => $channels->count(),
            'results' => $results,
        ];
    }
}

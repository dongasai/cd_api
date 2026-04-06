<?php

namespace App\Services\CodingStatus;

use App\Enums\OperationSource;
use App\Enums\OperationType;
use App\Models\Channel;
use App\Models\ChannelErrorHandlingLog;
use App\Models\ChannelRequestLog;
use App\Models\CodingAccount;
use App\Services\OperationLogService;
use Illuminate\Support\Facades\Log;

/**
 * 渠道错误处理服务
 *
 * 协调错误处理流程，处理渠道请求错误并执行相应动作
 */
class ChannelErrorHandlingService
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
     * 处理请求错误
     *
     * 根据错误日志内容匹配规则并执行处理动作
     *
     * @param  Channel  $channel  发生错误的渠道
     * @param  ChannelRequestLog  $log  请求日志
     * @return array<string, mixed> 处理结果
     */
    public function handleRequestError(Channel $channel, ChannelRequestLog $log): array
    {
        // 检查渠道是否绑定了 CodingAccount
        if (! $channel->hasCodingAccount()) {
            return [
                'handled' => false,
                'message' => '渠道未绑定 CodingAccount',
            ];
        }

        $account = $channel->codingAccount;
        if (! $account) {
            return [
                'handled' => false,
                'message' => 'CodingAccount 不存在',
            ];
        }

        // 检查是否为成功请求
        if ($log->is_success) {
            return [
                'handled' => false,
                'message' => '请求成功，无需处理',
            ];
        }

        // 构建错误上下文
        $errorContext = [
            'channel_id' => $channel->id,
            'status_code' => $log->response_status,
            'error_type' => $log->error_type,
            'error_message' => $log->error_message,
            'response_body' => $log->response_body,
        ];

        // 获取驱动并处理错误
        try {
            $driver = $this->driverManager->driverForAccount($account);
            $result = $driver->handleError($errorContext);

            // 记录操作日志
            if ($result['handled'] && isset($result['rule_matched'])) {
                $this->operationLogService->logCodingAccountOperation(
                    type: OperationType::CODING_ACCOUNT_PAUSE,
                    accountId: $account->id,
                    accountName: $account->name,
                    reason: "错误规则匹配: {$result['rule_matched']['name']}",
                    beforeData: ['status' => $account->status],
                    afterData: [
                        'status' => CodingAccount::STATUS_SUSPENDED,
                        'pause_duration_minutes' => $result['pause_duration'] ?? null,
                    ],
                    source: OperationSource::SYSTEM
                );
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('处理渠道错误失败', [
                'channel_id' => $channel->id,
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'handled' => false,
                'message' => '处理错误失败: '.$e->getMessage(),
            ];
        }
    }

    /**
     * 手动恢复账户
     *
     * @param  CodingAccount  $account  账户实例
     * @param  int|null  $userId  操作用户ID
     * @return array<string, mixed> 处理结果
     */
    public function manualRecoverAccount(CodingAccount $account, ?int $userId = null): array
    {
        if ($account->status !== CodingAccount::STATUS_SUSPENDED) {
            return [
                'success' => false,
                'message' => '账户不在暂停状态',
            ];
        }

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

        $afterData = [
            'status' => CodingAccount::STATUS_ACTIVE,
            'disabled_at' => null,
        ];

        // 记录操作日志
        $this->operationLogService->logCodingAccountOperation(
            type: OperationType::CODING_ACCOUNT_REOPEN,
            accountId: $account->id,
            accountName: $account->name,
            reason: '手动恢复暂停账户',
            beforeData: $beforeData,
            afterData: $afterData,
            source: OperationSource::ADMIN,
            userId: $userId
        );

        // 记录错误处理日志
        ChannelErrorHandlingLog::logManualHandling([
            'account_id' => $account->id,
            'action_taken' => 'manual_recover',
        ], $userId ?? 0);

        Log::info('账户已手动恢复', [
            'account_id' => $account->id,
            'account_name' => $account->name,
            'user_id' => $userId,
        ]);

        return [
            'success' => true,
            'message' => '账户已恢复',
        ];
    }

    /**
     * 手动暂停账户
     *
     * @param  CodingAccount  $account  账户实例
     * @param  int  $minutes  暂停时长（分钟）
     * @param  int|null  $userId  操作用户ID
     * @param  string|null  $reason  暂停原因
     * @return array<string, mixed> 处理结果
     */
    public function manualPauseAccount(CodingAccount $account, int $minutes, ?int $userId = null, ?string $reason = null): array
    {
        $beforeData = [
            'status' => $account->status,
            'disabled_at' => $account->disabled_at?->toDateTimeString(),
        ];

        // 暂停账户
        $account->update([
            'status' => CodingAccount::STATUS_SUSPENDED,
            'disabled_at' => now(),
            'pause_duration_minutes' => $minutes,
            'pause_reason' => $reason ?? '手动暂停',
            'pause_rule_id' => null,
        ]);

        $afterData = [
            'status' => CodingAccount::STATUS_SUSPENDED,
            'disabled_at' => now()->toDateTimeString(),
            'pause_duration_minutes' => $minutes,
            'pause_reason' => $reason ?? '手动暂停',
        ];

        // 记录操作日志
        $this->operationLogService->logCodingAccountOperation(
            type: OperationType::CODING_ACCOUNT_PAUSE,
            accountId: $account->id,
            accountName: $account->name,
            reason: $reason ?? '手动暂停',
            beforeData: $beforeData,
            afterData: $afterData,
            source: OperationSource::ADMIN,
            userId: $userId
        );

        // 记录错误处理日志
        ChannelErrorHandlingLog::logManualHandling([
            'account_id' => $account->id,
            'action_taken' => 'manual_pause',
            'pause_duration_minutes' => $minutes,
        ], $userId ?? 0);

        Log::info('账户已手动暂停', [
            'account_id' => $account->id,
            'account_name' => $account->name,
            'pause_duration_minutes' => $minutes,
            'reason' => $reason,
            'user_id' => $userId,
        ]);

        return [
            'success' => true,
            'message' => "账户已暂停 {$minutes} 分钟",
        ];
    }

    /**
     * 获取暂停中的账户列表
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, CodingAccount>
     */
    public function getPausedAccounts()
    {
        return CodingAccount::where('status', CodingAccount::STATUS_SUSPENDED)
            ->whereNotNull('pause_duration_minutes')
            ->with('channels')
            ->get();
    }

    /**
     * 检查账户是否应该自动恢复
     *
     * @param  CodingAccount  $account  账户实例
     * @return bool 是否应该恢复
     */
    public function shouldAutoRecover(CodingAccount $account): bool
    {
        if ($account->status !== CodingAccount::STATUS_SUSPENDED) {
            return false;
        }

        if (! $account->pause_duration_minutes || ! $account->disabled_at) {
            return false;
        }

        $recoverAt = $account->disabled_at->addMinutes($account->pause_duration_minutes);

        return now()->gte($recoverAt);
    }
}

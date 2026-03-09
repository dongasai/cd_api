<?php

namespace App\Services;

use App\Enums\OperationSource;
use App\Enums\OperationType;
use App\Models\OperationLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * 操作日志服务
 */
class OperationLogService
{
    /**
     * 记录操作日志
     */
    public function log(
        OperationType $type,
        ?int $targetId = null,
        ?string $targetName = null,
        ?string $description = null,
        ?string $reason = null,
        ?array $beforeData = null,
        ?array $afterData = null,
        ?OperationSource $source = null,
        ?int $userId = null,
        ?string $username = null
    ): OperationLog {
        $source = $source ?? $this->detectSource();
        $userId = $userId ?? $this->getCurrentUserId();
        $username = $username ?? $this->getCurrentUsername();

        return OperationLog::create([
            'type' => $type->value,
            'target' => $type->getTarget()->value,
            'target_id' => $targetId,
            'target_name' => $targetName,
            'source' => $source->value,
            'user_id' => $userId,
            'username' => $username,
            'description' => $description,
            'reason' => $reason,
            'before_data' => $beforeData,
            'after_data' => $afterData,
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * 记录渠道操作日志
     */
    public function logChannelOperation(
        OperationType $type,
        int $channelId,
        string $channelName,
        ?string $reason = null,
        ?array $beforeData = null,
        ?array $afterData = null,
        ?OperationSource $source = null,
        ?int $userId = null
    ): OperationLog {
        return $this->log(
            type: $type,
            targetId: $channelId,
            targetName: $channelName,
            description: $this->generateChannelDescription($type, $channelName),
            reason: $reason,
            beforeData: $beforeData,
            afterData: $afterData,
            source: $source,
            userId: $userId
        );
    }

    /**
     * 记录Coding账户操作日志
     */
    public function logCodingAccountOperation(
        OperationType $type,
        int $accountId,
        string $accountName,
        ?string $reason = null,
        ?array $beforeData = null,
        ?array $afterData = null,
        ?OperationSource $source = null,
        ?int $userId = null
    ): OperationLog {
        return $this->log(
            type: $type,
            targetId: $accountId,
            targetName: $accountName,
            description: $this->generateCodingAccountDescription($type, $accountName),
            reason: $reason,
            beforeData: $beforeData,
            afterData: $afterData,
            source: $source,
            userId: $userId
        );
    }

    /**
     * 记录管理员操作
     */
    public function logAdminOperation(
        OperationType $type,
        ?int $targetId = null,
        ?string $targetName = null,
        ?string $reason = null,
        ?array $beforeData = null,
        ?array $afterData = null
    ): OperationLog {
        return $this->log(
            type: $type,
            targetId: $targetId,
            targetName: $targetName,
            reason: $reason,
            beforeData: $beforeData,
            afterData: $afterData,
            source: OperationSource::ADMIN
        );
    }

    /**
     * 记录定时任务操作
     */
    public function logScheduleOperation(
        OperationType $type,
        ?int $targetId = null,
        ?string $targetName = null,
        ?string $reason = null,
        ?array $beforeData = null,
        ?array $afterData = null
    ): OperationLog {
        return $this->log(
            type: $type,
            targetId: $targetId,
            targetName: $targetName,
            reason: $reason,
            beforeData: $beforeData,
            afterData: $afterData,
            source: OperationSource::SCHEDULE
        );
    }

    /**
     * 记录系统自动操作
     */
    public function logSystemOperation(
        OperationType $type,
        ?int $targetId = null,
        ?string $targetName = null,
        ?string $reason = null,
        ?array $beforeData = null,
        ?array $afterData = null
    ): OperationLog {
        return $this->log(
            type: $type,
            targetId: $targetId,
            targetName: $targetName,
            reason: $reason,
            beforeData: $beforeData,
            afterData: $afterData,
            source: OperationSource::SYSTEM
        );
    }

    /**
     * 检测操作来源
     */
    protected function detectSource(): OperationSource
    {
        if (app()->runningInConsole()) {
            return OperationSource::SCHEDULE;
        }

        if (Auth::check()) {
            return OperationSource::ADMIN;
        }

        return OperationSource::SYSTEM;
    }

    /**
     * 获取当前用户ID
     */
    protected function getCurrentUserId(): ?int
    {
        return Auth::id();
    }

    /**
     * 获取当前用户名
     */
    protected function getCurrentUsername(): ?string
    {
        return Auth::user()?->name;
    }

    /**
     * 生成渠道操作描述
     */
    protected function generateChannelDescription(OperationType $type, string $channelName): string
    {
        return match ($type) {
            OperationType::CHANNEL_CREATE => "创建渠道: {$channelName}",
            OperationType::CHANNEL_UPDATE => "更新渠道: {$channelName}",
            OperationType::CHANNEL_DELETE => "删除渠道: {$channelName}",
            OperationType::CHANNEL_ENABLE => "启用渠道: {$channelName}",
            OperationType::CHANNEL_DISABLE => "禁用渠道: {$channelName}",
            default => "渠道操作: {$channelName}",
        };
    }

    /**
     * 生成Coding账户操作描述
     */
    protected function generateCodingAccountDescription(OperationType $type, string $accountName): string
    {
        return match ($type) {
            OperationType::CODING_ACCOUNT_CREATE => "创建Coding账户: {$accountName}",
            OperationType::CODING_ACCOUNT_UPDATE => "更新Coding账户: {$accountName}",
            OperationType::CODING_ACCOUNT_DELETE => "删除Coding账户: {$accountName}",
            OperationType::CODING_ACCOUNT_ENABLE => "启用Coding账户: {$accountName}",
            OperationType::CODING_ACCOUNT_DISABLE => "禁用Coding账户: {$accountName}",
            OperationType::CODING_ACCOUNT_SYNC => "同步Coding账户: {$accountName}",
            OperationType::CODING_ACCOUNT_REOPEN => "重新开启Coding账户: {$accountName}",
            default => "Coding账户操作: {$accountName}",
        };
    }
}

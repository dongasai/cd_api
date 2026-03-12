<?php

namespace App\Services\CodingStatus\Drivers;

use App\Models\CodingAccount;
use App\Models\CodingQuotaUsage;
use App\Models\CodingUsageLog;

/**
 * CodingStatus 驱动抽象基类
 *
 * 提供公共的配额管理逻辑
 */
abstract class AbstractCodingStatusDriver implements CodingStatusDriver
{
    protected CodingAccount $account;

    /**
     * 设置Coding账户
     */
    public function setAccount(CodingAccount $account): self
    {
        $this->account = $account;

        return $this;
    }

    /**
     * 获取Redis键前缀
     */
    protected function getRedisKeyPrefix(): string
    {
        return "coding:{$this->account->id}:";
    }

    /**
     * 获取使用量Redis键
     */
    protected function getUsageKey(string $metric): string
    {
        $period = $this->getCurrentPeriodKey();

        return $this->getRedisKeyPrefix()."usage:{$metric}:{$period}";
    }

    /**
     * 获取当前周期键
     */
    protected function getCurrentPeriodKey(): string
    {
        $periodInfo = $this->getPeriodInfo();

        return $periodInfo['key'] ?? date('Y-m-d');
    }

    /**
     * 获取配额配置
     */
    protected function getQuotaConfig(): array
    {
        return $this->account->getQuotaConfig();
    }

    /**
     * 获取阈值配置
     */
    protected function getThresholds(): array
    {
        $config = $this->getQuotaConfig();

        return $config['thresholds'] ?? [
            'warning' => 0.80,
            'critical' => 0.90,
            'disable' => 0.95,
        ];
    }

    /**
     * 获取限制配置
     */
    protected function getLimits(): array
    {
        $config = $this->getQuotaConfig();

        return $config['limits'] ?? [];
    }

    /**
     * 获取周期类型
     */
    protected function getCycle(): string
    {
        $config = $this->getQuotaConfig();

        return $config['cycle'] ?? 'monthly';
    }

    /**
     * 获取当前使用量
     */
    protected function getCurrentUsage(string $metric): int
    {
        $key = $this->getUsageKey($metric);

        return (int) Redis::get($key) ?: 0;
    }

    /**
     * 增加使用量
     */
    protected function incrementUsage(string $metric, int $amount): void
    {
        $key = $this->getUsageKey($metric);
        Redis::incrby($key, $amount);

        // 设置过期时间，防止无限增长
        $periodInfo = $this->getPeriodInfo();
        if (isset($periodInfo['ends_at'])) {
            $ttl = $periodInfo['ends_at']->diffInSeconds(now()) + 86400; // 额外一天
            Redis::expire($key, (int) $ttl);
        }
    }

    /**
     * 获取模型消耗倍数
     */
    protected function getModelMultiplier(string $model): float
    {
        return 1.0;
    }

    /**
     * 计算使用率
     */
    protected function calculateUsageRate(int $used, int $limit): float
    {
        if ($limit <= 0) {
            return 0.0;
        }

        return min(1.0, $used / $limit);
    }

    /**
     * 根据使用率获取状态
     */
    protected function getStatusByUsageRate(float $rate): string
    {
        $thresholds = $this->getThresholds();

        if ($rate >= ($thresholds['disable'] ?? 0.95)) {
            return CodingAccount::STATUS_EXHAUSTED;
        }

        if ($rate >= ($thresholds['critical'] ?? 0.90)) {
            return CodingAccount::STATUS_CRITICAL;
        }

        if ($rate >= ($thresholds['warning'] ?? 0.80)) {
            return CodingAccount::STATUS_WARNING;
        }

        return CodingAccount::STATUS_ACTIVE;
    }

    /**
     * 判断是否应该禁用渠道
     */
    public function shouldDisable(): bool
    {
        $status = $this->getStatus();

        return in_array($status['status'], [
            CodingAccount::STATUS_EXHAUSTED,
            CodingAccount::STATUS_EXPIRED,
            CodingAccount::STATUS_SUSPENDED,
            CodingAccount::STATUS_ERROR,
        ], true);
    }

    /**
     * 判断是否应该启用渠道
     */
    public function shouldEnable(): bool
    {
        // 检查账户状态是否已恢复
        $status = $this->getStatus();

        // 如果当前是禁用状态，但配额已恢复
        if ($this->account->status === CodingAccount::STATUS_EXHAUSTED) {
            return in_array($status['status'], [
                CodingAccount::STATUS_ACTIVE,
                CodingAccount::STATUS_WARNING,
                CodingAccount::STATUS_CRITICAL,
            ], true);
        }

        // 如果是过期但已续期
        if ($this->account->status === CodingAccount::STATUS_EXPIRED) {
            return ! $this->account->isExpired();
        }

        // 如果是暂停状态，需要手动恢复
        if ($this->account->status === CodingAccount::STATUS_SUSPENDED) {
            return false;
        }

        // 如果是错误状态，需要手动恢复
        if ($this->account->status === CodingAccount::STATUS_ERROR) {
            return false;
        }

        return false;
    }

    /**
     * 记录使用量到数据库
     */
    protected function logUsage(array $data): void
    {
        CodingUsageLog::create([
            'account_id' => $this->account->id,
            'channel_id' => $data['channel_id'] ?? null,
            'request_id' => $data['request_id'] ?? null,
            'requests' => $data['requests'] ?? 0,
            'tokens_input' => $data['tokens_input'] ?? 0,
            'tokens_output' => $data['tokens_output'] ?? 0,
            'prompts' => $data['prompts'] ?? 0,
            'credits' => $data['credits'] ?? 0,
            'cost' => $data['cost'] ?? 0,
            'model' => $data['model'] ?? null,
            'model_multiplier' => $data['model_multiplier'] ?? 1.00,
            'status' => $data['status'] ?? CodingUsageLog::STATUS_SUCCESS,
            'metadata' => $data['metadata'] ?? null,
            'created_at' => now(),
        ]);
    }

    /**
     * 获取周期信息
     */
    public function getPeriodInfo(): array
    {
        $cycle = $this->getCycle();
        $now = now();

        return match ($cycle) {
            '5h' => $this->get5hPeriodInfo($now),
            'daily' => $this->getDailyPeriodInfo($now),
            'weekly' => $this->getWeeklyPeriodInfo($now),
            'monthly' => $this->getMonthlyPeriodInfo($now),
            default => $this->getMonthlyPeriodInfo($now),
        };
    }

    /**
     * 获取5小时周期信息
     */
    protected function get5hPeriodInfo(\Carbon\Carbon $now): array
    {
        $config = $this->getQuotaConfig();
        $offset = $config['period_offset'] ?? 0;

        // 计算当天经过的秒数
        $secondsOfDay = $now->copy()->startOfDay()->diffInSeconds($now);
        $periodIndex = (int) floor(($secondsOfDay + $offset) / 18000); // 5小时 = 18000秒

        $periodStart = $now->copy()->startOfDay()->addSeconds($periodIndex * 18000 - $offset);
        $periodEnd = $periodStart->copy()->addSeconds(18000);

        return [
            'type' => '5h',
            'key' => $now->format('Y-m-d').'-'.$periodIndex,
            'starts_at' => $periodStart,
            'ends_at' => $periodEnd,
            'next_reset' => $periodEnd,
        ];
    }

    /**
     * 获取日周期信息
     */
    protected function getDailyPeriodInfo(\Carbon\Carbon $now): array
    {
        return [
            'type' => 'daily',
            'key' => $now->format('Y-m-d'),
            'starts_at' => $now->copy()->startOfDay(),
            'ends_at' => $now->copy()->endOfDay(),
            'next_reset' => $now->copy()->addDay()->startOfDay(),
        ];
    }

    /**
     * 获取周周期信息
     */
    protected function getWeeklyPeriodInfo(\Carbon\Carbon $now): array
    {
        return [
            'type' => 'weekly',
            'key' => $now->format('Y-W'),
            'starts_at' => $now->copy()->startOfWeek(),
            'ends_at' => $now->copy()->endOfWeek(),
            'next_reset' => $now->copy()->addWeek()->startOfWeek(),
        ];
    }

    /**
     * 获取月周期信息
     */
    protected function getMonthlyPeriodInfo(\Carbon\Carbon $now): array
    {
        $config = $this->getQuotaConfig();
        $resetDay = $config['reset_day'] ?? 1;

        $currentMonthStart = $now->copy()->setDay($resetDay)->startOfDay();
        if ($now->day < $resetDay) {
            $currentMonthStart->subMonth();
        }

        $nextMonthStart = $currentMonthStart->copy()->addMonth();

        return [
            'type' => 'monthly',
            'key' => $currentMonthStart->format('Y-m'),
            'starts_at' => $currentMonthStart,
            'ends_at' => $nextMonthStart->copy()->subSecond(),
            'next_reset' => $nextMonthStart,
            'reset_day' => $resetDay,
        ];
    }

    /**
     * 获取配置表单字段
     */
    public function getConfigFields(): array
    {
        return [
            [
                'name' => 'limits',
                'label' => '配额限制',
                'type' => 'key_value',
                'help' => '设置各维度的配额限制',
            ],
            [
                'name' => 'thresholds',
                'label' => '阈值配置',
                'type' => 'key_value',
                'help' => 'warning: 警告阈值, critical: 临界阈值, disable: 禁用阈值',
            ],
            [
                'name' => 'cycle',
                'label' => '重置周期',
                'type' => 'select',
                'options' => [
                    '5h' => '5小时',
                    'daily' => '每日',
                    'weekly' => '每周',
                    'monthly' => '每月',
                ],
                'default' => 'monthly',
            ],
            [
                'name' => 'reset_day',
                'label' => '重置日期',
                'type' => 'number',
                'min' => 1,
                'max' => 31,
                'default' => 1,
                'help' => '每月重置的日期 (仅月度周期有效)',
            ],
        ];
    }

    /**
     * 获取默认配额配置
     */
    public function getDefaultQuotaConfig(): array
    {
        return [
            'limits' => [],
            'thresholds' => [
                'warning' => 0.80,
                'critical' => 0.90,
                'disable' => 0.95,
            ],
            'cycle' => 'monthly',
            'reset_day' => 1,
        ];
    }

    /**
     * 获取检查间隔（秒）
     *
     * 固定周期驱动默认60秒检查一次
     */
    public function getCheckInterval(): int
    {
        $config = $this->getQuotaConfig();

        return $config['check_interval'] ?? 60;
    }

    /**
     * 验证账户凭证
     */
    public function validateCredentials(): array
    {
        $credentials = $this->account->getCredentials();

        if (empty($credentials)) {
            return [
                'valid' => false,
                'message' => '凭证信息为空',
            ];
        }

        return [
            'valid' => true,
            'message' => '凭证格式正确',
        ];
    }
}

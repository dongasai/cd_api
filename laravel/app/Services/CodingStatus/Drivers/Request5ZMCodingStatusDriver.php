<?php

namespace App\Services\CodingStatus\Drivers;

use App\Models\Coding5ZMQuota;
use App\Models\CodingAccount;

/**
 * Request5ZM Coding Status 驱动
 *
 * 支持三个维度的请求次数限制：5小时/周/月
 * 同时监控三个周期的配额使用情况
 * 配额数据存储在 coding_5zm_quotas 表中
 */
class Request5ZMCodingStatusDriver extends AbstractCodingStatusDriver
{
    /**
     * 配额模型实例
     */
    protected ?Coding5ZMQuota $quotaModel = null;

    /**
     * 获取配额模型
     */
    protected function getQuotaModel(): Coding5ZMQuota
    {
        if ($this->quotaModel === null) {
            $this->quotaModel = Coding5ZMQuota::firstOrCreate(
                ['account_id' => $this->account->id],
                $this->getDefaultQuotaConfig()
            );
        }

        return $this->quotaModel;
    }

    /**
     * 获取配额配置
     */
    protected function getQuotaConfig(): array
    {
        $quota = $this->getQuotaModel();

        return [
            'limits' => [
                'requests_5h' => $quota->limit_5h,
                'requests_weekly' => $quota->limit_weekly,
                'requests_monthly' => $quota->limit_monthly,
            ],
            'thresholds' => [
                'warning' => $quota->threshold_warning,
                'critical' => $quota->threshold_critical,
                'disable' => $quota->threshold_disable,
            ],
            'period_offset' => $quota->period_offset,
            'reset_day' => $quota->reset_day,
        ];
    }

    /**
     * 获取限制配置
     */
    protected function getLimits(): array
    {
        $quota = $this->getQuotaModel();

        return [
            'requests_5h' => $quota->limit_5h,
            'requests_weekly' => $quota->limit_weekly,
            'requests_monthly' => $quota->limit_monthly,
        ];
    }

    /**
     * 获取阈值配置
     */
    protected function getThresholds(): array
    {
        $quota = $this->getQuotaModel();

        return [
            'warning' => $quota->threshold_warning,
            'critical' => $quota->threshold_critical,
            'disable' => $quota->threshold_disable,
        ];
    }

    /**
     * 获取使用量
     */
    protected function getUsage(): array
    {
        $quota = $this->getQuotaModel();
        $periods = $this->getAllPeriodInfo();

        // 检查周期是否变更，如果变更则重置使用量
        $this->checkAndResetPeriods($quota, $periods);

        return [
            'requests_5h' => $quota->used_5h,
            'requests_weekly' => $quota->used_weekly,
            'requests_monthly' => $quota->used_monthly,
        ];
    }

    /**
     * 检查并重置周期
     */
    protected function checkAndResetPeriods(Coding5ZMQuota $quota, array $periods): void
    {
        $needSave = false;

        // 检查5小时周期
        if ($quota->period_5h !== ($periods['requests_5h']['key'] ?? null)) {
            $quota->period_5h = $periods['requests_5h']['key'] ?? null;
            $quota->used_5h = 0;
            $needSave = true;
        }

        // 检查周周期
        if ($quota->period_weekly !== ($periods['requests_weekly']['key'] ?? null)) {
            $quota->period_weekly = $periods['requests_weekly']['key'] ?? null;
            $quota->used_weekly = 0;
            $needSave = true;
        }

        // 检查月周期
        if ($quota->period_monthly !== ($periods['requests_monthly']['key'] ?? null)) {
            $quota->period_monthly = $periods['requests_monthly']['key'] ?? null;
            $quota->used_monthly = 0;
            $needSave = true;
        }

        if ($needSave) {
            $quota->save();
        }
    }

    /**
     * 增加使用量
     */
    protected function incrementUsage(string $metric, int $amount): void
    {
        $quota = $this->getQuotaModel();

        switch ($metric) {
            case 'requests_5h':
                $quota->used_5h += $amount;
                break;
            case 'requests_weekly':
                $quota->used_weekly += $amount;
                break;
            case 'requests_monthly':
                $quota->used_monthly += $amount;
                break;
        }

        $quota->last_usage_at = now();
        $quota->save();
    }

    /**
     * 获取驱动名称
     */
    public function getName(): string
    {
        return '请求次数计费(三维度)';
    }

    /**
     * 获取驱动描述
     */
    public function getDescription(): string
    {
        return '支持5小时/周/月三个维度的请求次数限制';
    }

    /**
     * 获取支持的计费维度
     */
    public function getSupportedMetrics(): array
    {
        return [
            'requests_5h' => '5小时周期请求次数',
            'requests_weekly' => '周请求次数',
            'requests_monthly' => '月请求次数',
        ];
    }

    /**
     * 获取当前配额状态
     */
    public function getStatus(): array
    {
        $limits = $this->getLimits();
        $usage = $this->getUsage();
        $rates = [];
        $maxRate = 0.0;

        // 计算各维度使用率
        $metrics = ['requests_5h', 'requests_weekly', 'requests_monthly'];
        foreach ($metrics as $metric) {
            if (isset($limits[$metric]) && $limits[$metric] > 0) {
                $used = $usage[$metric] ?? 0;
                $rate = $this->calculateUsageRate($used, $limits[$metric]);
                $rates[$metric] = [
                    'used' => $used,
                    'limit' => $limits[$metric],
                    'rate' => $rate,
                ];
                $maxRate = max($maxRate, $rate);
            }
        }

        // 根据最大使用率确定状态
        $status = $this->getStatusByUsageRate($maxRate);

        // 检查账户是否过期
        if ($this->account->isExpired()) {
            $status = CodingAccount::STATUS_EXPIRED;
        }

        return [
            'status' => $status,
            'usage_rate' => $maxRate,
            'rates' => $rates,
            'periods' => $this->getAllPeriodInfo(),
        ];
    }

    /**
     * 检查配额是否充足
     */
    public function checkQuota(array $context): array
    {
        $limits = $this->getLimits();
        $usage = $this->getUsage();

        $requests = $context['requests'] ?? 1;
        $model = $context['model'] ?? '';

        // 获取模型消耗倍数
        $multiplier = $this->getModelMultiplier($model);
        $adjustedRequests = (int) ceil($requests * $multiplier);

        $sufficient = true;
        $insufficientMetrics = [];

        // 检查三个维度的配额
        $metrics = [
            'requests_5h' => '5小时',
            'requests_weekly' => '周',
            'requests_monthly' => '月',
        ];

        foreach ($metrics as $metric => $label) {
            if (isset($limits[$metric]) && $limits[$metric] > 0) {
                $used = $usage[$metric] ?? 0;
                if ($used + $adjustedRequests > $limits[$metric]) {
                    $sufficient = false;
                    $insufficientMetrics[] = $label.'('.$metric.')';
                }
            }
        }

        return [
            'sufficient' => $sufficient,
            'insufficient_metrics' => $insufficientMetrics,
            'requested' => [
                'requests' => $requests,
            ],
            'adjusted' => [
                'requests' => $adjustedRequests,
            ],
            'multiplier' => $multiplier,
        ];
    }

    /**
     * 消耗配额
     */
    public function consume(array $usage): void
    {
        $requests = $usage['requests'] ?? 1;
        $model = $usage['model'] ?? '';
        $channelId = $usage['channel_id'] ?? null;
        $requestId = $usage['request_id'] ?? null;

        // 获取模型消耗倍数
        $multiplier = $this->getModelMultiplier($model);
        $adjustedRequests = (int) ceil($requests * $multiplier);

        // 获取消耗前的配额快照
        $usageBefore = $this->getUsage();

        // 同时更新三个维度的使用量
        $this->incrementUsage('requests_5h', $adjustedRequests);
        $this->incrementUsage('requests_weekly', $adjustedRequests);
        $this->incrementUsage('requests_monthly', $adjustedRequests);

        // 获取消耗后的配额快照
        $usageAfter = $this->getUsage();

        // 获取周期标识
        $periods = $this->getAllPeriodInfo();

        // 记录到专用日志表
        \App\Models\Coding5ZMUsageLog::create([
            'account_id' => $this->account->id,
            'channel_id' => $channelId,
            'request_id' => $requestId,
            'requests' => $adjustedRequests,
            'model' => $model,
            'model_multiplier' => $multiplier,
            'period_5h' => $periods['requests_5h']['key'] ?? '',
            'period_weekly' => $periods['requests_weekly']['key'] ?? '',
            'period_monthly' => $periods['requests_monthly']['key'] ?? '',
            'quota_before_5h' => $usageBefore['requests_5h'] ?? 0,
            'quota_before_weekly' => $usageBefore['requests_weekly'] ?? 0,
            'quota_before_monthly' => $usageBefore['requests_monthly'] ?? 0,
            'quota_after_5h' => $usageAfter['requests_5h'] ?? 0,
            'quota_after_weekly' => $usageAfter['requests_weekly'] ?? 0,
            'quota_after_monthly' => $usageAfter['requests_monthly'] ?? 0,
            'status' => $usage['status'] ?? 'success',
            'metadata' => $usage['metadata'] ?? null,
            'created_at' => now(),
        ]);
    }

    /**
     * 同步配额信息
     */
    public function sync(): void
    {
        $quota = $this->getQuotaModel();
        $quota->last_sync_at = now();
        $quota->save();

        $this->account->update([
            'last_sync_at' => now(),
        ]);
    }

    /**
     * 获取配额详细信息
     */
    public function getQuotaInfo(): array
    {
        $limits = $this->getLimits();
        $usage = $this->getUsage();
        $periods = $this->getAllPeriodInfo();

        $metrics = [];
        $labels = [
            'requests_5h' => '5小时周期请求次数',
            'requests_weekly' => '周请求次数',
            'requests_monthly' => '月请求次数',
        ];

        foreach ($labels as $metric => $label) {
            if (isset($limits[$metric])) {
                $used = $usage[$metric] ?? 0;
                $limit = $limits[$metric];
                $metrics[$metric] = [
                    'label' => $label,
                    'used' => $used,
                    'limit' => $limit,
                    'remaining' => max(0, $limit - $used),
                    'rate' => $this->calculateUsageRate($used, $limit),
                    'period' => $periods[$metric] ?? null,
                ];
            }
        }

        return [
            'metrics' => $metrics,
            'periods' => $periods,
            'status' => $this->getStatus(),
        ];
    }

    /**
     * 获取所有周期信息
     */
    protected function getAllPeriodInfo(): array
    {
        $now = now();

        return [
            'requests_5h' => $this->get5hPeriodInfo($now),
            'requests_weekly' => $this->getWeeklyPeriodInfo($now),
            'requests_monthly' => $this->getMonthlyPeriodInfo($now),
        ];
    }

    /**
     * 获取默认配额配置
     */
    public function getDefaultQuotaConfig(): array
    {
        return [
            'limit_5h' => 300,
            'limit_weekly' => 1000,
            'limit_monthly' => 5000,
            'threshold_warning' => 0.800,
            'threshold_critical' => 0.900,
            'threshold_disable' => 0.950,
            'period_offset' => 0,
            'reset_day' => 1,
        ];
    }

    /**
     * 获取配置表单字段
     */
    public function getConfigFields(): array
    {
        return [
            [
                'name' => 'limits.requests_5h',
                'label' => '5小时周期请求限制',
                'type' => 'number',
                'min' => 0,
                'default' => 300,
                'help' => '5小时周期内的最大请求次数',
            ],
            [
                'name' => 'limits.requests_weekly',
                'label' => '周请求限制',
                'type' => 'number',
                'min' => 0,
                'default' => 1000,
                'help' => '每周最大请求次数',
            ],
            [
                'name' => 'limits.requests_monthly',
                'label' => '月请求限制',
                'type' => 'number',
                'min' => 0,
                'default' => 5000,
                'help' => '每月最大请求次数',
            ],
            [
                'name' => 'thresholds',
                'label' => '阈值配置',
                'type' => 'key_value',
                'help' => 'warning: 警告阈值, critical: 临界阈值, disable: 禁用阈值',
                'default' => [
                    'warning' => 0.80,
                    'critical' => 0.90,
                    'disable' => 0.95,
                ],
            ],
            [
                'name' => 'period_offset',
                'label' => '5小时周期偏移量 (秒)',
                'type' => 'number',
                'min' => 0,
                'max' => 17999,
                'default' => 0,
                'help' => '5小时周期的起始偏移量',
            ],
            [
                'name' => 'reset_day',
                'label' => '月重置日期',
                'type' => 'number',
                'min' => 1,
                'max' => 31,
                'default' => 1,
                'help' => '每月重置的日期',
            ],
        ];
    }

    /**
     * 格式化配额数值显示
     *
     * Request5ZM驱动显示：5h/周/月 三维度请求次数
     */
    public function formatQuotaDisplay(): string
    {
        $quotaInfo = $this->getQuotaInfo();
        $metrics = $quotaInfo['metrics'] ?? [];

        if (empty($metrics)) {
            return '<span class="text-muted">暂无数据</span>';
        }

        $displayParts = [];

        // 显示5小时周期
        if (isset($metrics['requests_5h'])) {
            $data = $metrics['requests_5h'];
            $used = (int) $data['used'];
            $limit = (int) $data['limit'];
            $percent = $limit > 0 ? round($used / $limit * 100, 1) : 0;

            $color = $this->getColorByPercent($percent);
            $displayParts[] = "<span class='text-{$color}'>5h: {$used}/{$limit}</span>";
        }

        // 显示周周期
        if (isset($metrics['requests_weekly'])) {
            $data = $metrics['requests_weekly'];
            $used = (int) $data['used'];
            $limit = (int) $data['limit'];
            $percent = $limit > 0 ? round($used / $limit * 100, 1) : 0;

            $color = $this->getColorByPercent($percent);
            $displayParts[] = "<small class='text-{$color}'>周: {$used}/{$limit}</small>";
        }

        // 显示月周期
        if (isset($metrics['requests_monthly'])) {
            $data = $metrics['requests_monthly'];
            $used = (int) $data['used'];
            $limit = (int) $data['limit'];
            $percent = $limit > 0 ? round($used / $limit * 100, 1) : 0;

            $color = $this->getColorByPercent($percent);
            $displayParts[] = "<small class='text-{$color}'>月: {$used}/{$limit}</small>";
        }

        return implode('<br>', $displayParts);
    }

    /**
     * 根据百分比获取颜色
     */
    protected function getColorByPercent(float $percent): string
    {
        if ($percent >= 95) {
            return 'danger';
        }

        if ($percent >= 90) {
            return 'warning';
        }

        if ($percent >= 80) {
            return 'info';
        }

        return 'success';
    }
}

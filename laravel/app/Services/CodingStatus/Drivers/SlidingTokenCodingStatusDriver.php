<?php

namespace App\Services\CodingStatus\Drivers;

use App\Models\CodingAccount;
use App\Models\CodingSlidingUsageLog;
use App\Services\CodingStatus\SlidingWindowRepository;

class SlidingTokenCodingStatusDriver implements CodingStatusDriver
{
    protected CodingAccount $account;

    protected SlidingWindowRepository $repository;

    public function __construct(SlidingWindowRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getName(): string
    {
        return '滑动窗口Token计费';
    }

    public function getDescription(): string
    {
        return '统计过去N小时/天内的Token消耗';
    }

    public function getSupportedMetrics(): array
    {
        return [
            'tokens_input' => '输入Token',
            'tokens_output' => '输出Token',
            'tokens_total' => '总Token',
        ];
    }

    public function setAccount(CodingAccount $account): self
    {
        $this->account = $account;

        return $this;
    }

    protected function getWindowType(): string
    {
        $config = $this->account->getQuotaConfig();

        return $config['window_type'] ?? '7d';
    }

    protected function getLimits(): array
    {
        $config = $this->account->getQuotaConfig();

        return $config['limits'] ?? [];
    }

    protected function getThresholds(): array
    {
        $config = $this->account->getQuotaConfig();

        return $config['thresholds'] ?? [
            'warning' => 0.80,
            'critical' => 0.90,
            'disable' => 0.95,
        ];
    }

    protected function getModelMultiplier(string $model): float
    {
        return 1.0;
    }

    protected function calculateUsageRate(int $used, int $limit): float
    {
        if ($limit <= 0) {
            return 0.0;
        }

        return min(1.0, $used / $limit);
    }

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

    public function getStatus(): array
    {
        $limits = $this->getLimits();
        $windowType = $this->getWindowType();
        $usage = $this->repository->getTokenUsageInWindow($this->account, $windowType);

        $rates = [];
        $maxRate = 0.0;

        foreach ($limits as $metric => $limit) {
            if ($limit > 0 && isset($usage[$metric])) {
                $used = $usage[$metric];
                $rate = $this->calculateUsageRate($used, $limit);
                $rates[$metric] = [
                    'used' => $used,
                    'limit' => $limit,
                    'rate' => $rate,
                ];
                $maxRate = max($maxRate, $rate);
            }
        }

        $status = $this->getStatusByUsageRate($maxRate);

        if ($this->account->isExpired()) {
            $status = CodingAccount::STATUS_EXPIRED;
        }

        return [
            'status' => $status,
            'usage_rate' => $maxRate,
            'rates' => $rates,
            'period' => $this->getPeriodInfo(),
        ];
    }

    public function checkQuota(array $context): array
    {
        $limits = $this->getLimits();
        $windowType = $this->getWindowType();
        $usage = $this->repository->getTokenUsageInWindow($this->account, $windowType);

        $tokensInput = $context['tokens_input'] ?? 0;
        $tokensOutput = $context['tokens_output'] ?? 0;
        $tokensTotal = $tokensInput + $tokensOutput;
        $model = $context['model'] ?? '';

        $multiplier = $this->getModelMultiplier($model);
        $adjustedTokensInput = (int) ($tokensInput * $multiplier);
        $adjustedTokensOutput = (int) ($tokensOutput * $multiplier);
        $adjustedTokensTotal = $adjustedTokensInput + $adjustedTokensOutput;

        $sufficient = true;
        $insufficientMetrics = [];

        if (isset($limits['tokens_input']) && $limits['tokens_input'] > 0) {
            $used = $usage['tokens_input'] ?? 0;
            if ($used + $adjustedTokensInput > $limits['tokens_input']) {
                $sufficient = false;
                $insufficientMetrics[] = 'tokens_input';
            }
        }

        if (isset($limits['tokens_output']) && $limits['tokens_output'] > 0) {
            $used = $usage['tokens_output'] ?? 0;
            if ($used + $adjustedTokensOutput > $limits['tokens_output']) {
                $sufficient = false;
                $insufficientMetrics[] = 'tokens_output';
            }
        }

        if (isset($limits['tokens_total']) && $limits['tokens_total'] > 0) {
            $used = $usage['tokens_total'] ?? 0;
            if ($used + $adjustedTokensTotal > $limits['tokens_total']) {
                $sufficient = false;
                $insufficientMetrics[] = 'tokens_total';
            }
        }

        return [
            'sufficient' => $sufficient,
            'insufficient_metrics' => $insufficientMetrics,
            'requested' => [
                'tokens_input' => $tokensInput,
                'tokens_output' => $tokensOutput,
                'tokens_total' => $tokensTotal,
            ],
            'adjusted' => [
                'tokens_input' => $adjustedTokensInput,
                'tokens_output' => $adjustedTokensOutput,
                'tokens_total' => $adjustedTokensTotal,
            ],
            'multiplier' => $multiplier,
        ];
    }

    public function consume(array $usage): void
    {
        $tokensInput = $usage['tokens_input'] ?? 0;
        $tokensOutput = $usage['tokens_output'] ?? 0;
        $model = $usage['model'] ?? '';

        $multiplier = $this->getModelMultiplier($model);
        $adjustedTokensInput = (int) ($tokensInput * $multiplier);
        $adjustedTokensOutput = (int) ($tokensOutput * $multiplier);
        $adjustedTokensTotal = $adjustedTokensInput + $adjustedTokensOutput;

        $this->repository->recordUsage($this->account, [
            'channel_id' => $usage['channel_id'] ?? null,
            'request_id' => $usage['request_id'] ?? null,
            'tokens_input' => $adjustedTokensInput,
            'tokens_output' => $adjustedTokensOutput,
            'tokens_total' => $adjustedTokensTotal,
            'model' => $model,
            'model_multiplier' => $multiplier,
            'status' => $usage['status'] ?? CodingSlidingUsageLog::STATUS_SUCCESS,
            'metadata' => $usage['metadata'] ?? null,
        ]);
    }

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

    public function shouldEnable(): bool
    {
        $status = $this->getStatus();

        if ($this->account->status === CodingAccount::STATUS_EXHAUSTED) {
            return in_array($status['status'], [
                CodingAccount::STATUS_ACTIVE,
                CodingAccount::STATUS_WARNING,
                CodingAccount::STATUS_CRITICAL,
            ], true);
        }

        if ($this->account->status === CodingAccount::STATUS_EXPIRED) {
            return ! $this->account->isExpired();
        }

        return false;
    }

    public function sync(): void
    {
        $windowType = $this->getWindowType();
        $usage = $this->repository->getTokenUsageInWindow($this->account, $windowType);

        $this->account->update([
            'quota_cached' => [
                'synced_at' => now()->toDateTimeString(),
                'usage' => $usage,
                'limits' => $this->getLimits(),
                'window_type' => $windowType,
            ],
            'last_sync_at' => now(),
        ]);
    }

    public function getQuotaInfo(): array
    {
        $limits = $this->getLimits();
        $windowType = $this->getWindowType();
        $usage = $this->repository->getTokenUsageInWindow($this->account, $windowType);

        $metrics = [];
        foreach ($this->getSupportedMetrics() as $metric => $label) {
            if (isset($limits[$metric])) {
                $used = $usage[$metric] ?? 0;
                $limit = $limits[$metric];
                $metrics[$metric] = [
                    'label' => $label,
                    'used' => $used,
                    'limit' => $limit,
                    'remaining' => max(0, $limit - $used),
                    'rate' => $this->calculateUsageRate($used, $limit),
                ];
            }
        }

        return [
            'metrics' => $metrics,
            'period' => $this->getPeriodInfo(),
            'status' => $this->getStatus(),
        ];
    }

    public function getPeriodInfo(): array
    {
        $windowType = $this->getWindowType();

        return $this->repository->getPeriodInfo($windowType);
    }

    public function validateCredentials(): array
    {
        return [
            'valid' => true,
            'message' => '滑动窗口驱动不需要外部凭证',
        ];
    }

    public function getConfigFields(): array
    {
        return [
            [
                'name' => 'limits.tokens_input',
                'label' => '输入Token限制',
                'type' => 'number',
                'min' => 0,
                'default' => 5000000,
            ],
            [
                'name' => 'limits.tokens_output',
                'label' => '输出Token限制',
                'type' => 'number',
                'min' => 0,
                'default' => 2500000,
            ],
            [
                'name' => 'limits.tokens_total',
                'label' => '总Token限制',
                'type' => 'number',
                'min' => 0,
                'default' => 7500000,
            ],
            [
                'name' => 'window_type',
                'label' => '窗口类型',
                'type' => 'select',
                'options' => [
                    '5h' => '5小时',
                    '1d' => '1天',
                    '7d' => '7天',
                    '30d' => '30天',
                ],
                'default' => '7d',
            ],
            [
                'name' => 'thresholds',
                'label' => '阈值配置',
                'type' => 'key_value',
                'default' => [
                    'warning' => 0.80,
                    'critical' => 0.90,
                    'disable' => 0.95,
                ],
            ],
        ];
    }

    public function getDefaultQuotaConfig(): array
    {
        return [
            'limits' => [
                'tokens_input' => 5000000,
                'tokens_output' => 2500000,
                'tokens_total' => 7500000,
            ],
            'thresholds' => [
                'warning' => 0.80,
                'critical' => 0.90,
                'disable' => 0.95,
            ],
            'window_type' => '7d',
            'check_interval' => 300,
        ];
    }

    public function getCheckInterval(): int
    {
        $config = $this->account->getQuotaConfig();

        return $config['check_interval'] ?? 300;
    }
}

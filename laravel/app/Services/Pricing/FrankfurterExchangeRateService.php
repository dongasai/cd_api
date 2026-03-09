<?php

namespace App\Services\Pricing;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Frankfurter 汇率服务
 *
 * 基于 Frankfurter API (欧洲央行数据源) 提供汇率查询服务
 * API 文档: https://www.frankfurter.app/docs/
 */
class FrankfurterExchangeRateService
{
    protected const API_URL = 'https://api.frankfurter.app';

    protected const CACHE_KEY_PREFIX = 'frankfurter_exchange_rate_';

    protected const CACHE_TTL = 3600;

    protected ?array $ratesCache = [];

    /**
     * 获取汇率
     *
     * @param  string  $from  源货币代码 (如: USD)
     * @param  string  $to  目标货币代码 (如: CNY)
     * @return float|null 汇率，失败返回 null
     *
     * @example
     * $rate = $service->getRate('USD', 'CNY'); // 返回 6.9173
     */
    public function getRate(string $from, string $to): ?float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return 1.0;
        }

        $rates = $this->getRates($from);

        return $rates[$to] ?? null;
    }

    /**
     * 获取指定货币的所有汇率
     *
     * @param  string  $base  基准货币代码 (如: USD)
     * @return array 汇率数组 ['CNY' => 6.9173, 'EUR' => 0.86, ...]
     *
     * @example
     * $rates = $service->getRates('USD');
     */
    public function getRates(string $base = 'USD'): array
    {
        $base = strtoupper($base);

        if (isset($this->ratesCache[$base])) {
            return $this->ratesCache[$base];
        }

        return $this->ratesCache[$base] = Cache::remember(
            self::CACHE_KEY_PREFIX.$base,
            self::CACHE_TTL,
            fn () => $this->fetchRatesFromApi($base)
        );
    }

    /**
     * 货币转换
     *
     * @param  float  $amount  金额
     * @param  string  $from  源货币代码
     * @param  string  $to  目标货币代码
     * @return array|null 转换结果数组，失败返回 null
     *
     * @example
     * $result = $service->convert(100, 'USD', 'CNY');
     * // 返回 ['amount' => 100, 'from' => 'USD', 'to' => 'CNY', 'rate' => 6.9173, 'result' => 691.73]
     */
    public function convert(float $amount, string $from, string $to): ?array
    {
        $rate = $this->getRate($from, $to);

        if ($rate === null) {
            return null;
        }

        return [
            'amount' => $amount,
            'from' => strtoupper($from),
            'to' => strtoupper($to),
            'rate' => $rate,
            'result' => round($amount * $rate, 6),
        ];
    }

    /**
     * 获取历史汇率
     *
     * @param  string  $date  日期 (格式: Y-m-d)
     * @param  string  $from  源货币代码
     * @param  string  $to  目标货币代码
     * @return float|null 汇率，失败返回 null
     */
    public function getHistoricalRate(string $date, string $from, string $to): ?float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return 1.0;
        }

        $cacheKey = self::CACHE_KEY_PREFIX."{$date}_{$from}";

        $rates = Cache::remember(
            $cacheKey,
            self::CACHE_TTL * 24,
            fn () => $this->fetchHistoricalRatesFromApi($date, $from)
        );

        return $rates[$to] ?? null;
    }

    /**
     * 获取汇率更新日期
     *
     * @param  string  $base  基准货币代码
     * @return string|null 日期 (Y-m-d 格式)
     */
    public function getLastUpdateDate(string $base = 'USD'): ?string
    {
        $base = strtoupper($base);

        $cacheKey = self::CACHE_KEY_PREFIX.$base.'_date';

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            function () use ($base) {
                try {
                    $response = Http::timeout(10)
                        ->withoutVerifying()
                        ->get(self::API_URL."/latest?from={$base}");

                    if (! $response->successful()) {
                        return null;
                    }

                    $data = $response->json();

                    return $data['date'] ?? null;
                } catch (\Throwable) {
                    return null;
                }
            }
        );
    }

    /**
     * 刷新缓存
     *
     * @param  string|null  $base  指定基准货币，null 则刷新所有
     * @return bool 是否刷新成功
     */
    public function refreshCache(?string $base = null): bool
    {
        if ($base !== null) {
            Cache::forget(self::CACHE_KEY_PREFIX.strtoupper($base));
            Cache::forget(self::CACHE_KEY_PREFIX.strtoupper($base).'_date');
            unset($this->ratesCache[strtoupper($base)]);

            return ! empty($this->getRates($base));
        }

        $this->ratesCache = [];

        return ! empty($this->getRates('USD'));
    }

    /**
     * 从 API 获取汇率数据
     *
     * @param  string  $base  基准货币代码
     * @return array 汇率数组
     */
    protected function fetchRatesFromApi(string $base): array
    {
        try {
            $response = Http::timeout(10)
                ->withoutVerifying()
                ->get(self::API_URL."/latest?from={$base}");

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json();

            return $data['rates'] ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * 从 API 获取历史汇率数据
     *
     * @param  string  $date  日期
     * @param  string  $base  基准货币代码
     * @return array 汇率数组
     */
    protected function fetchHistoricalRatesFromApi(string $date, string $base): array
    {
        try {
            $response = Http::timeout(10)
                ->withoutVerifying()
                ->get(self::API_URL."/{$date}?from={$base}");

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json();

            return $data['rates'] ?? [];
        } catch (\Throwable) {
            return [];
        }
    }
}

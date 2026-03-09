<?php

namespace App\Services\CodingStatus;

use App\Models\CodingAccount;
use App\Models\CodingSlidingUsageLog;
use App\Models\CodingSlidingWindow;
use Illuminate\Support\Facades\DB;

class SlidingWindowRepository
{
    /**
     * Get or create active sliding window for account
     *
     * For sliding window, we don't create a fixed window.
     * Instead, we calculate usage based on time range from now backwards.
     * This method creates a window record for tracking purposes only.
     */
    public function getOrCreateWindow(CodingAccount $account, string $windowType): CodingSlidingWindow
    {
        $windowSeconds = CodingSlidingWindow::getTypeSeconds($windowType);
        $now = now();
        $windowStart = $now->copy()->subSeconds($windowSeconds);

        $window = new CodingSlidingWindow([
            'account_id' => $account->id,
            'window_type' => $windowType,
            'window_seconds' => $windowSeconds,
            'started_at' => $windowStart,
            'ends_at' => $now,
            'status' => CodingSlidingWindow::STATUS_ACTIVE,
        ]);

        return $window;
    }

    /**
     * Get usage within sliding window (past N seconds from now)
     */
    public function getUsageInWindow(CodingAccount $account, string $windowType): array
    {
        $windowSeconds = CodingSlidingWindow::getTypeSeconds($windowType);
        $startTime = now()->subSeconds($windowSeconds);

        $usage = CodingSlidingUsageLog::query()
            ->where('account_id', $account->id)
            ->where('created_at', '>=', $startTime)
            ->select([
                DB::raw('SUM(requests) as total_requests'),
                DB::raw('SUM(tokens_input) as total_tokens_input'),
                DB::raw('SUM(tokens_output) as total_tokens_output'),
                DB::raw('SUM(tokens_total) as total_tokens_total'),
            ])
            ->first();

        return [
            'requests' => (int) ($usage->total_requests ?? 0),
            'tokens_input' => (int) ($usage->total_tokens_input ?? 0),
            'tokens_output' => (int) ($usage->total_tokens_output ?? 0),
            'tokens_total' => (int) ($usage->total_tokens_total ?? 0),
        ];
    }

    /**
     * Record usage to sliding window
     */
    public function recordUsage(CodingAccount $account, array $data): CodingSlidingUsageLog
    {
        return CodingSlidingUsageLog::create([
            'account_id' => $account->id,
            'window_id' => $data['window_id'] ?? null,
            'channel_id' => $data['channel_id'] ?? null,
            'request_id' => $data['request_id'] ?? null,
            'requests' => $data['requests'] ?? 0,
            'tokens_input' => $data['tokens_input'] ?? 0,
            'tokens_output' => $data['tokens_output'] ?? 0,
            'tokens_total' => $data['tokens_total'] ?? 0,
            'model' => $data['model'] ?? null,
            'model_multiplier' => $data['model_multiplier'] ?? 1.00,
            'status' => $data['status'] ?? CodingSlidingUsageLog::STATUS_SUCCESS,
            'metadata' => $data['metadata'] ?? null,
            'created_at' => now(),
        ]);
    }

    /**
     * Get usage count in sliding window (for request-based quota)
     */
    public function getRequestCountInWindow(CodingAccount $account, string $windowType): int
    {
        $windowSeconds = CodingSlidingWindow::getTypeSeconds($windowType);
        $startTime = now()->subSeconds($windowSeconds);

        return (int) CodingSlidingUsageLog::query()
            ->where('account_id', $account->id)
            ->where('created_at', '>=', $startTime)
            ->sum('requests');
    }

    /**
     * Get token usage in sliding window (for token-based quota)
     */
    public function getTokenUsageInWindow(CodingAccount $account, string $windowType): array
    {
        $windowSeconds = CodingSlidingWindow::getTypeSeconds($windowType);
        $startTime = now()->subSeconds($windowSeconds);

        $usage = CodingSlidingUsageLog::query()
            ->where('account_id', $account->id)
            ->where('created_at', '>=', $startTime)
            ->select([
                DB::raw('SUM(tokens_input) as total_tokens_input'),
                DB::raw('SUM(tokens_output) as total_tokens_output'),
                DB::raw('SUM(tokens_total) as total_tokens_total'),
            ])
            ->first();

        return [
            'tokens_input' => (int) ($usage->total_tokens_input ?? 0),
            'tokens_output' => (int) ($usage->total_tokens_output ?? 0),
            'tokens_total' => (int) ($usage->total_tokens_total ?? 0),
        ];
    }

    /**
     * Cleanup expired records (older than max window period)
     */
    public function cleanupExpiredRecords(int $retentionDays = 35): int
    {
        $cutoffTime = now()->subDays($retentionDays);

        return CodingSlidingUsageLog::query()
            ->where('created_at', '<', $cutoffTime)
            ->delete();
    }

    /**
     * Get window period info
     */
    public function getPeriodInfo(string $windowType): array
    {
        $windowSeconds = CodingSlidingWindow::getTypeSeconds($windowType);
        $now = now();
        $windowStart = $now->copy()->subSeconds($windowSeconds);

        return [
            'type' => $windowType,
            'seconds' => $windowSeconds,
            'starts_at' => $windowStart,
            'ends_at' => $now,
            'is_sliding' => true,
        ];
    }
}

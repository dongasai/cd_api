<?php

namespace App\Console\Commands;

use App\Models\CodingSlidingUsageLog;
use App\Models\CodingSlidingWindow;
use Illuminate\Console\Command;

class CleanupSlidingWindowData extends Command
{
    protected $signature = 'cdapi:coding:cleanup-sliding-window
                            {--retention=35 : Number of days to retain data}
                            {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Cleanup expired sliding window data';

    public function handle(): int
    {
        $retentionDays = (int) $this->option('retention');
        $dryRun = (bool) $this->option('dry-run');
        $cutoffDate = now()->subDays($retentionDays);

        $this->info("Cleaning up data older than {$retentionDays} days (before {$cutoffDate->toDateTimeString()})");

        // Count records to delete
        $usageLogCount = CodingSlidingUsageLog::query()
            ->where('created_at', '<', $cutoffDate)
            ->count();

        $windowCount = CodingSlidingWindow::query()
            ->where('ends_at', '<', $cutoffDate)
            ->count();

        $this->info("Found {$usageLogCount} usage logs and {$windowCount} windows to delete");

        if ($dryRun) {
            $this->info('Dry run mode - no data will be deleted');

            return self::SUCCESS;
        }

        if ($this->confirm('Do you want to proceed with deletion?', true)) {
            // Delete usage logs first (due to foreign key constraint)
            $deletedLogs = CodingSlidingUsageLog::query()
                ->where('created_at', '<', $cutoffDate)
                ->delete();

            $this->info("Deleted {$deletedLogs} usage logs");

            // Delete expired windows
            $deletedWindows = CodingSlidingWindow::query()
                ->where('ends_at', '<', $cutoffDate)
                ->delete();

            $this->info("Deleted {$deletedWindows} windows");

            $this->info('Cleanup completed successfully');
        }

        return self::SUCCESS;
    }
}

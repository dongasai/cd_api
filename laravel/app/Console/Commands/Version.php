<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * 输出版本信息命令
 *
 * 输出构建信息、框架版本和系统基本信息
 * php artisan version
 */
class Version extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'version';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '输出版本信息';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('╔════════════════════════════════════════╗');
        $this->info('║         CdApi 版本信息                 ║');
        $this->info('╚════════════════════════════════════════╝');
        $this->newLine();

        // 构建信息（从环境变量读取）
        $this->info('【构建信息】');
        $buildTimeRaw = env('DOCKER_BUILD_TIME', 'N/A');
        $buildTimeLocal = $this->formatBuildTime($buildTimeRaw);
        $buildBranch = env('DOCKER_BUILD_BRANCH', 'N/A');
        $buildCommit = env('DOCKER_BUILD_COMMIT', 'N/A');
        $buildRunner = env('DOCKER_BUILD_RUNNER', 'N/A');

        $this->line(sprintf('  构建时间(UTC): <comment>%s</comment>', $buildTimeRaw));
        $this->line(sprintf('  构建时间(本地): <comment>%s</comment>', $buildTimeLocal));
        $this->line(sprintf('  构建分支: <comment>%s</comment>', $buildBranch));
        $this->line(sprintf('  构建 Commit: <comment>%s</comment>', $buildCommit));
        $this->line(sprintf('  构建执行者: <comment>%s</comment>', $buildRunner));
        $this->newLine();

        // 框架信息
        $this->info('【框架信息】');
        $this->line(sprintf('  Laravel 版本: <comment>%s</comment>', app()->version()));
        $this->line(sprintf('  PHP 版本: <comment>%s</comment>', PHP_VERSION));
        $this->line(sprintf('  应用名称: <comment>%s</comment>', config('app.name')));
        $this->newLine();

        // 运行环境
        $this->info('【运行环境】');
        $this->line(sprintf('  运行环境: <comment>%s</comment>', config('app.env')));
        $this->line(sprintf('  服务器时区: <comment>%s</comment>', date_default_timezone_get()));
        $this->line(sprintf('  操作系统: <comment>%s</comment>', PHP_OS_FAMILY));
        $this->line(sprintf('  运行方式: <comment>%s</comment>', php_sapi_name()));
        $this->newLine();

        // 数据库信息
        $this->info('【数据库信息】');
        $dbConnection = config('database.default');
        $this->line(sprintf('  数据库连接: <comment>%s</comment>', $dbConnection));

        // 获取数据库名称
        $dbName = config("database.connections.{$dbConnection}.database", 'N/A');
        $this->line(sprintf('  数据库名称: <comment>%s</comment>', $dbName));

        // 获取数据库版本
        try {
            $pdo = app('db')->connection()->getPdo();
            $this->line(sprintf('  数据库版本: <comment>%s</comment>', $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION)));
        } catch (\Exception $e) {
            $this->line('  数据库版本: <error>无法获取</error>');
        }

        // Composer 版本
        $composerVersion = $this->getComposerVersion();
        $this->line(sprintf('  Composer 版本: <comment>%s</comment>', $composerVersion));
        $this->newLine();

        $this->info('╔════════════════════════════════════════╗');
        $this->info('║        构建信息已输出完成              ║');
        $this->info('╚════════════════════════════════════════╝');

        return self::SUCCESS;
    }

    /**
     * 格式化构建时间为 Y-m-d H:i:s
     */
    private function formatBuildTime(?string $time): string
    {
        if (! $time || $time === 'N/A') {
            return 'N/A';
        }

        try {
            // 自动解析时间字符串中的时区（如 "2026-07-30 16:32:50 UTC"）
            $dt = new \DateTime($time);
            $dt->setTimezone(new \DateTimeZone(date_default_timezone_get()));

            return $dt->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return $time;
        }
    }

    /**
     * 获取 Composer 版本
     */
    private function getComposerVersion(): string
    {
        try {
            $output = [];
            exec('composer --version 2>&1', $output, $returnCode);
            if ($returnCode === 0 && ! empty($output)) {
                // Composer version 2.7.1 2024-02-09 15:26:28
                return trim(str_replace('Composer version', '', $output[0]));
            }
        } catch (\Exception $e) {
            // 忽略异常
        }

        return 'N/A';
    }
}

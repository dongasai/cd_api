<?php

namespace App\Services;

/**
 * 系统信息服务
 *
 * 收集并返回系统版本信息，供 CLI 命令和后台页面共用
 */
class SystemInfoService
{
    /**
     * 获取所有系统信息
     */
    public function getAll(): array
    {
        return [
            'build' => $this->getBuildInfo(),
            'framework' => $this->getFrameworkInfo(),
            'runtime' => $this->getRuntimeInfo(),
            'database' => $this->getDatabaseInfo(),
        ];
    }

    /**
     * 获取构建信息（从 Docker 环境变量读取）
     */
    public function getBuildInfo(): array
    {
        $buildTimeRaw = env('DOCKER_BUILD_TIME', 'N/A');

        return [
            'build_time_utc' => $buildTimeRaw,
            'build_time_local' => $this->formatBuildTime($buildTimeRaw),
            'build_branch' => env('DOCKER_BUILD_BRANCH', 'N/A'),
            'build_commit' => env('DOCKER_BUILD_COMMIT', 'N/A'),
            'build_runner' => env('DOCKER_BUILD_RUNNER', 'N/A'),
        ];
    }

    /**
     * 获取框架信息
     */
    public function getFrameworkInfo(): array
    {
        return [
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'app_name' => config('app.name'),
        ];
    }

    /**
     * 获取运行环境信息
     */
    public function getRuntimeInfo(): array
    {
        return [
            'environment' => config('app.env'),
            'timezone' => date_default_timezone_get(),
            'os' => PHP_OS_FAMILY,
            'sapi' => php_sapi_name(),
        ];
    }

    /**
     * 获取数据库信息
     */
    public function getDatabaseInfo(): array
    {
        $connection = config('database.default');
        $dbName = config("database.connections.{$connection}.database", 'N/A');

        // 获取数据库版本
        try {
            $pdo = app('db')->connection()->getPdo();
            $dbVersion = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
        } catch (\Exception) {
            $dbVersion = '无法获取';
        }

        return [
            'connection' => $connection,
            'database_name' => $dbName,
            'database_version' => $dbVersion,
            'composer_version' => $this->getComposerVersion(),
        ];
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
        } catch (\Exception) {
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
        } catch (\Exception) {
            // 忽略异常
        }

        return 'N/A';
    }
}

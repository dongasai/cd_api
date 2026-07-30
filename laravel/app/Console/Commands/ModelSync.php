<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * 同步 models.dev 模型数据到本地
 *
 * 从 models.dev 下载模型元数据（context_length、pricing、modality 等）
 * 缓存1天，供排行榜命令读取补充数据
 * php artisan cdapi:stats:model-sync
 */
class ModelSync extends Command
{
    protected $signature = 'cdapi:stats:model-sync
                            {--force : 强制刷新，忽略缓存}';

    protected $description = '同步 models.dev 模型数据到本地缓存（1天）';

    private const CACHE_PATH = 'app/models_dev.json';

    private const DATA_PATH = 'database/data/models.json';

    private const CACHE_TTL = 86400;

    public function handle(): int
    {
        $cacheFile = storage_path(self::CACHE_PATH);

        if (! $this->option('force') && $this->isCacheValid($cacheFile)) {
            $hours = round((time() - filemtime($cacheFile)) / 3600, 1);
            $this->info("缓存有效（{$hours}小时前），使用 --force 强制刷新");

            return self::SUCCESS;
        }

        $this->info('正在从 models.dev 下载模型数据...');

        $data = $this->fetchModelsDevData();
        if ($data === null) {
            if (file_exists($cacheFile)) {
                $this->warn('下载失败，使用旧缓存');

                return self::SUCCESS;
            }

            return self::FAILURE;
        }

        $this->saveCache($cacheFile, $data);

        // 同时保存到 database/data/models.json（供迁移引用）
        $dataPath = base_path(self::DATA_PATH);
        $this->saveCache($dataPath, $data);

        $count = count($data['data'] ?? []);
        $this->info("同步完成，共 {$count} 个模型");

        return self::SUCCESS;
    }

    private function isCacheValid(string $path): bool
    {
        if (! file_exists($path)) {
            return false;
        }

        return (time() - filemtime($path)) < self::CACHE_TTL;
    }

    private function fetchModelsDevData(): ?array
    {
        $urls = [
            'https://raw.githubusercontent.com/anomalyco/models.dev/dev/models.json',
            'https://models.dev/api.json',
        ];

        foreach ($urls as $url) {
            try {
                $response = Http::timeout(60)->get($url);

                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['data']) && count($data['data']) > 0) {
                        $this->info("数据来源: {$url}");

                        return $data;
                    }
                }
            } catch (\Exception $e) {
                $this->warn("来源失败 {$url}: {$e->getMessage()}");
                continue;
            }
        }

        $this->error('所有数据源均不可用');

        return null;
    }

    private function saveCache(string $path, array $data): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // database/data/models.json 格式化保存
        if (str_ends_with($path, 'models.json')) {
            file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE));
        }
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * 更新 Anthropic OpenAPI 规范文件
 */
class UpdateAnthropicApiSpec extends Command
{
    /**
     * Anthropic OpenAPI 规范文件 URL
     */
    private const OPENAPI_URL = 'https://app.stainless.com/api/spec/documented/anthropic/openapi.documented.yml';

    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cdapi:update-anthropic-spec
                            {--force : 强制更新，即使文件已存在}
                            {--extract-messages : 提取 Messages API Schema}';

    /**
     * The console command description.
     */
    protected $description = '下载并更新 Anthropic 官方 OpenAPI 规范文件';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('开始更新 Anthropic OpenAPI 规范文件...');

        $filename = 'anthropic-openapi.yml';
        $path = storage_path($filename);

        // 检查文件是否存在
        if (file_exists($path) && ! $this->option('force')) {
            $fileTime = filemtime($path);
            $fileAge = time() - $fileTime;
            $days = round($fileAge / 86400, 1);

            if ($this->confirm("文件已存在（{$days} 天前更新），是否重新下载？", false)) {
                $this->downloadFile($path);
            } else {
                $this->info('跳过下载，使用现有文件。');
            }
        } else {
            $this->downloadFile($path);
        }

        // 验证文件
        if (! $this->validateFile($path)) {
            $this->error('文件验证失败！');

            return self::FAILURE;
        }

        // 提取 Messages API Schema
        if ($this->option('extract-messages')) {
            $this->extractMessagesSchema($path);
        }

        $this->info('✓ Anthropic OpenAPI 规范文件更新完成！');
        $this->line("  文件位置: {$path}");
        $this->line('  文件大小: '.round(filesize($path) / 1024 / 1024, 2).' MB');

        return self::SUCCESS;
    }

    /**
     * 下载文件
     */
    private function downloadFile(string $path): void
    {
        $this->info('正在从 Stainless 下载 Anthropic OpenAPI 规范文件...');

        try {
            $response = Http::timeout(60)->get(self::OPENAPI_URL);

            if (! $response->successful()) {
                throw new \Exception("HTTP {$response->status()}: {$response->reason()}");
            }

            file_put_contents($path, $response->body());

            $this->info('✓ 文件下载成功');
        } catch (\Exception $e) {
            $this->error("下载失败: {$e->getMessage()}");
            exit(1);
        }
    }

    /**
     * 验证文件
     */
    private function validateFile(string $path): bool
    {
        $this->info('验证文件格式...');

        if (! file_exists($path)) {
            $this->error('文件不存在');

            return false;
        }

        $content = file_get_contents($path);

        // 检查是否是有效的 YAML
        if (! str_starts_with($content, 'openapi:')) {
            $this->error('文件格式无效，不是 OpenAPI 规范');

            return false;
        }

        // 检查版本
        if (preg_match('/^openapi:\s*([\d.]+)/m', $content, $matches)) {
            $version = $matches[1];
            $this->line("  OpenAPI 版本: {$version}");
        }

        // 检查信息
        if (preg_match('/^version:\s*([\d.]+)/m', $content, $matches)) {
            $apiVersion = $matches[1];
            $this->line("  API 版本: {$apiVersion}");
        }

        return true;
    }

    /**
     * 提取 Messages API Schema
     */
    private function extractMessagesSchema(string $openApiPath): void
    {
        $this->info('提取 Messages API Schema...');

        $content = file_get_contents($openApiPath);

        // 查找 CreateMessageParams 定义（Anthropic 的 Messages API 请求参数）
        if (! preg_match('/^    CreateMessageParams:$/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $this->warn('未找到 CreateMessageParams 定义');

            return;
        }

        $startPos = $matches[0][1];

        // 查找下一个 schema 定义的位置
        if (! preg_match('/^    \w+:$/m', $content, $matches, PREG_OFFSET_CAPTURE, $startPos + 100)) {
            $endPos = strlen($content);
        } else {
            $endPos = $matches[0][1];
        }

        $schemaContent = substr($content, $startPos, $endPos - $startPos);

        // 保存提取的 Schema
        $schemaPath = storage_path('anthropic-messages-request-schema.yml');
        file_put_contents($schemaPath, $schemaContent);

        $this->info('✓ Messages API Schema 已提取');
        $this->line("  文件位置: {$schemaPath}");
    }
}

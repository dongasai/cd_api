<?php

namespace App\Console\Commands;

use App\Models\McpClient;
use App\Services\McpClientService;
use Illuminate\Console\Command;

/**
 * 测试 MCP 客户端连接命令
 */
class TestMcpConnection extends Command
{
    /**
     * 命令签名
     *
     * @var string
     */
    protected $signature = 'cdapi:mcp:test {--client= : 客户端ID} {--url= : 测试URL} {--header= : 自定义请求头(格式: name=value)} {--create : 创建测试客户端}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '测试 MCP 客户端连接';

    /**
     * 执行命令
     */
    public function handle(McpClientService $service): int
    {
        $clientId = $this->option('client');
        $url = $this->option('url');
        $create = $this->option('create');
        $headers = $this->parseHeaders($this->option('header'));

        // 如果指定了 URL，创建临时测试客户端
        if ($url) {
            $this->testUrl($service, $url, $headers);

            return self::SUCCESS;
        }

        // 如果需要创建测试客户端
        if ($create) {
            $this->createTestClient();

            return self::SUCCESS;
        }

        // 测试指定的客户端
        if ($clientId) {
            $client = McpClient::find($clientId);
            if (! $client) {
                $this->error("客户端 ID {$clientId} 不存在");

                return self::FAILURE;
            }
        } else {
            // 测试第一个客户端
            $client = McpClient::first();
            if (! $client) {
                $this->error('没有 MCP 客户端配置');
                $this->info('使用 --create 创建测试客户端，或 --url=URL 测试指定 URL');

                return self::FAILURE;
            }
        }

        $url = $client->url ?: 'N/A';
        $this->info("测试客户端: {$client->name} (ID: {$client->id})");
        $this->info("传输类型: {$client->transport}");
        $this->info("URL: {$url}");

        try {
            $result = $service->testConnection($client);

            if ($result['success']) {
                $this->info('✓ 连接成功');
                $this->newLine();

                if ($result['capabilities']) {
                    $this->displayCapabilities($result['capabilities']);
                }
            } else {
                $this->error("✗ 连接失败: {$result['message']}");

                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("✗ 异常: {$e->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * 测试指定 URL
     */
    protected function testUrl(McpClientService $service, string $url, array $headers = []): void
    {
        $this->info("测试 URL: {$url}");
        if (! empty($headers)) {
            $this->info('自定义头: '.json_encode($headers));
        }

        // 创建临时客户端模型
        $client = new McpClient([
            'name' => '临时测试',
            'slug' => 'temp-test',
            'transport' => McpClient::TRANSPORT_HTTP,
            'url' => $url,
            'headers' => $headers,
            'timeout' => 30,
        ]);

        try {
            $result = $service->testConnection($client, false);

            if ($result['success']) {
                $this->info('✓ 连接成功');
                if ($result['capabilities']) {
                    $this->displayCapabilities($result['capabilities']);
                }
            } else {
                $this->error("✗ 连接失败: {$result['message']}");
            }
        } catch (\Exception $e) {
            $this->error("✗ 异常: {$e->getMessage()}");
        }
    }

    /**
     * 解析 headers 参数
     */
    protected function parseHeaders(mixed $headerOptions): array
    {
        $headers = [];
        if ($headerOptions) {
            // 单个 header 可能是字符串，多个 header 是数组
            $headerList = is_array($headerOptions) ? $headerOptions : [$headerOptions];
            foreach ($headerList as $header) {
                if (str_contains($header, '=')) {
                    [$name, $value] = explode('=', $header, 2);
                    $headers[$name] = $value;
                }
            }
        }

        return $headers;
    }

    /**
     * 创建测试客户端
     */
    protected function createTestClient(): void
    {
        $url = $this->ask('请输入 MCP 服务器 URL', 'http://192.168.4.107:32126/mcp/cdapi');
        $name = $this->ask('请输入客户端名称', 'CdApi Local');

        $client = McpClient::create([
            'name' => $name,
            'slug' => 'test-'.time(),
            'transport' => McpClient::TRANSPORT_HTTP,
            'url' => $url,
            'timeout' => 30,
            'status' => McpClient::STATUS_INACTIVE,
        ]);

        $this->info("创建客户端成功: ID {$client->id}");
    }

    /**
     * 显示能力信息
     */
    protected function displayCapabilities(array $capabilities): void
    {
        if ($capabilities['server_info']) {
            $this->info("服务器: {$capabilities['server_info']['name']} v{$capabilities['server_info']['version']}");
        }

        if (! empty($capabilities['tools'])) {
            $this->newLine();
            $this->info('工具列表 ('.count($capabilities['tools']).'):');
            foreach ($capabilities['tools'] as $tool) {
                $this->line("  - {$tool['name']}: {$tool['description']}");
            }
        }

        if (! empty($capabilities['resources'])) {
            $this->newLine();
            $this->info('资源列表 ('.count($capabilities['resources']).'):');
            foreach ($capabilities['resources'] as $resource) {
                $this->line("  - {$resource['uri']}: {$resource['name']}");
            }
        }

        if (! empty($capabilities['prompts'])) {
            $this->newLine();
            $this->info('提示列表 ('.count($capabilities['prompts']).'):');
            foreach ($capabilities['prompts'] as $prompt) {
                $this->line("  - {$prompt['name']}: {$prompt['description']}");
            }
        }
    }
}

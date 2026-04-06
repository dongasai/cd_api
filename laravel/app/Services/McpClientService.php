<?php

namespace App\Services;

use App\Models\McpClient;
use Illuminate\Support\Facades\Log;
use Mcp\Client;
use Mcp\Client\Transport\HttpTransport;
use Mcp\Client\Transport\StdioTransport;
use Mcp\Schema\ClientCapabilities;
use Mcp\Schema\Content\EmbeddedResource;
use Mcp\Schema\Content\ImageContent;
use Mcp\Schema\Content\TextContent;
use Psr\Log\LoggerInterface;

/**
 * MCP 客户端服务类
 *
 * 封装 mcp/sdk 的连接和调用逻辑，支持 Streamable HTTP 和 Stdio 传输
 */
class McpClientService
{
    /**
     * 客户端连接缓存
     *
     * @var array<int, Client>
     */
    protected array $connections = [];

    /**
     * 传输实例缓存
     *
     * @var array<int, HttpTransport|StdioTransport>
     */
    protected array $transports = [];

    /**
     * 创建并连接 MCP 客户端
     *
     * @param  McpClient  $client  MCP 客户端配置模型
     * @return Client 连接后的客户端实例
     *
     * @throws \Exception 连接失败时抛出异常
     */
    public function connect(McpClient $client): Client
    {
        // 如果已有连接，直接返回
        if (isset($this->connections[$client->id])) {
            return $this->connections[$client->id];
        }

        try {
            $mcpClient = $this->createClient($client);
            $transport = $this->createTransport($client);

            // 连接到服务器
            $mcpClient->connect($transport);

            $this->connections[$client->id] = $mcpClient;
            $this->transports[$client->id] = $transport;

            // 更新状态
            $client->update([
                'status' => McpClient::STATUS_ACTIVE,
                'last_connected_at' => now(),
                'connection_error' => null,
            ]);

            // 获取并保存能力信息
            $this->refreshCapabilities($client, $mcpClient);

            return $mcpClient;
        } catch (\Exception $e) {
            $client->update([
                'status' => McpClient::STATUS_ERROR,
                'connection_error' => $e->getMessage(),
            ]);

            Log::error('MCP 客户端连接失败', [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * 断开客户端连接
     *
     * @param  McpClient  $client  MCP 客户端配置模型
     */
    public function disconnect(McpClient $client): void
    {
        if (isset($this->connections[$client->id])) {
            try {
                $this->connections[$client->id]->disconnect();
            } catch (\Exception $e) {
                Log::warning('MCP 客户端断开连接异常', [
                    'client_id' => $client->id,
                    'error' => $e->getMessage(),
                ]);
            }
            unset($this->connections[$client->id]);
        }

        if (isset($this->transports[$client->id])) {
            unset($this->transports[$client->id]);
        }

        $client->update([
            'status' => McpClient::STATUS_INACTIVE,
        ]);
    }

    /**
     * 测试客户端连接
     *
     * @param  McpClient  $client  MCP 客户端配置模型
     * @param  bool  $persist  是否持久化状态到数据库（默认 true）
     * @return array{success: bool, message: string, capabilities: array|null}
     */
    public function testConnection(McpClient $client, bool $persist = true): array
    {
        try {
            $mcpClient = $this->createClient($client);
            $transport = $this->createTransport($client);

            // 连接到服务器
            $mcpClient->connect($transport);

            // 获取能力信息
            $capabilities = $this->fetchCapabilities($mcpClient);

            // 断开连接
            $mcpClient->disconnect();

            // 更新状态（仅在模型已存在时）
            if ($persist && $client->exists) {
                $client->update([
                    'status' => McpClient::STATUS_ACTIVE,
                    'last_connected_at' => now(),
                    'connection_error' => null,
                    'capabilities' => $capabilities,
                ]);
            }

            return [
                'success' => true,
                'message' => '连接成功',
                'capabilities' => $capabilities,
            ];
        } catch (\Exception $e) {
            if ($persist && $client->exists) {
                $client->update([
                    'status' => McpClient::STATUS_ERROR,
                    'connection_error' => $e->getMessage(),
                ]);
            }

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'capabilities' => null,
            ];
        }
    }

    /**
     * 获取服务器工具列表
     *
     * @param  McpClient  $client  MCP 客户端配置模型
     * @return array 工具列表
     *
     * @throws \Exception 未连接时抛出异常
     */
    public function listTools(McpClient $client): array
    {
        $mcpClient = $this->getOrConnect($client);

        try {
            $result = $mcpClient->listTools();
            $tools = [];

            foreach ($result->tools as $tool) {
                $tools[] = [
                    'name' => $tool->name,
                    'description' => $tool->description,
                    'input_schema' => $tool->inputSchema,
                ];
            }

            return $tools;
        } catch (\Exception $e) {
            Log::error('MCP 获取工具列表失败', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 调用 MCP 工具
     *
     * @param  McpClient  $client  MCP 客户端配置模型
     * @param  string  $toolName  工具名称
     * @param  array  $arguments  工具参数
     * @return mixed 工具返回结果
     *
     * @throws \Exception 调用失败时抛出异常
     */
    public function callTool(McpClient $client, string $toolName, array $arguments = []): mixed
    {
        $mcpClient = $this->getOrConnect($client);

        try {
            Log::info('MCP 调用工具', [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'tool' => $toolName,
                'arguments' => $arguments,
            ]);

            $result = $mcpClient->callTool($toolName, $arguments);

            // 解析结果内容
            $content = [];
            foreach ($result->content as $item) {
                if ($item instanceof TextContent) {
                    $content[] = [
                        'type' => 'text',
                        'text' => $item->text,
                    ];
                } elseif ($item instanceof ImageContent) {
                    $content[] = [
                        'type' => 'image',
                        'data' => $item->data,
                        'mime_type' => $item->mimeType,
                    ];
                } elseif ($item instanceof EmbeddedResource) {
                    $content[] = [
                        'type' => 'resource',
                        'uri' => $item->resource->uri,
                        'name' => $item->resource->name ?? null,
                    ];
                } else {
                    // 其他类型转为文本
                    $content[] = [
                        'type' => 'text',
                        'text' => json_encode($item->jsonSerialize(), JSON_PRETTY_PRINT),
                    ];
                }
            }

            return [
                'content' => $content,
                'is_error' => $result->isError,
            ];
        } catch (\Exception $e) {
            Log::error('MCP 调用工具失败', [
                'client_id' => $client->id,
                'tool' => $toolName,
                'arguments' => $arguments,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 获取服务器资源列表
     *
     * @param  McpClient  $client  MCP 客户端配置模型
     * @return array 资源列表
     */
    public function listResources(McpClient $client): array
    {
        $mcpClient = $this->getOrConnect($client);

        try {
            $result = $mcpClient->listResources();
            $resources = [];

            foreach ($result->resources as $resource) {
                $resources[] = [
                    'uri' => $resource->uri,
                    'name' => $resource->name,
                    'description' => $resource->description ?? null,
                    'mime_type' => $resource->mimeType ?? null,
                ];
            }

            return $resources;
        } catch (\Exception $e) {
            Log::error('MCP 获取资源列表失败', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 获取服务器提示列表
     *
     * @param  McpClient  $client  MCP 客户端配置模型
     * @return array 提示列表
     */
    public function listPrompts(McpClient $client): array
    {
        $mcpClient = $this->getOrConnect($client);

        try {
            $result = $mcpClient->listPrompts();
            $prompts = [];

            foreach ($result->prompts as $prompt) {
                $prompts[] = [
                    'name' => $prompt->name,
                    'description' => $prompt->description ?? null,
                    'arguments' => $prompt->arguments ?? [],
                ];
            }

            return $prompts;
        } catch (\Exception $e) {
            Log::error('MCP 获取提示列表失败', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 刷新服务器能力信息
     *
     * @param  McpClient  $client  模型实例
     * @param  Client|null  $mcpClient  连接实例（可选）
     */
    public function refreshCapabilities(McpClient $client, ?Client $mcpClient = null): void
    {
        $mcpClient = $mcpClient ?? $this->getOrConnect($client);

        $capabilities = $this->fetchCapabilities($mcpClient);

        $client->update([
            'capabilities' => $capabilities,
        ]);
    }

    /**
     * 获取或连接客户端
     *
     * @param  McpClient  $client  MCP 客户端配置模型
     * @return Client 连接后的客户端实例
     */
    protected function getOrConnect(McpClient $client): Client
    {
        if (isset($this->connections[$client->id])) {
            return $this->connections[$client->id];
        }

        return $this->connect($client);
    }

    /**
     * 创建 MCP 客户端实例
     *
     * @param  McpClient  $client  MCP 客端配置模型
     * @return Client 客户端实例
     */
    protected function createClient(McpClient $client): Client
    {
        // 使用 Builder 构建客户端
        // 设置 roots 能力，确保 capabilities 序列化为对象而非空数组
        // 阿里云等 MCP 服务器要求 capabilities 必须是对象格式 {}
        $capabilities = new ClientCapabilities(roots: true);

        $builder = Client::builder()
            ->setClientInfo('CdApi MCP Client', '1.0.0')
            ->setCapabilities($capabilities)
            ->setRequestTimeout((int) $client->timeout)
            ->setInitTimeout((int) $client->timeout);

        return $builder->build();
    }

    /**
     * 创建传输实例
     *
     * @param  McpClient  $client  MCP 客端配置模型
     * @return HttpTransport|StdioTransport 传输实例
     */
    protected function createTransport(McpClient $client): HttpTransport|StdioTransport
    {
        if ($client->isHttp()) {
            // HTTP 传输 (Streamable HTTP)
            return new HttpTransport(
                endpoint: $client->url,
                headers: $client->headers ?? [],
                logger: app(LoggerInterface::class),
            );
        } else {
            // Stdio 传输
            return new StdioTransport(
                command: $client->command,
                args: $client->args ?? [],
                logger: app(LoggerInterface::class),
            );
        }
    }

    /**
     * 获取服务器能力信息
     *
     * @param  Client  $mcpClient  连接实例
     * @return array 能力信息
     */
    protected function fetchCapabilities(Client $mcpClient): array
    {
        $capabilities = [
            'server_info' => null,
            'tools' => [],
            'resources' => [],
            'prompts' => [],
        ];

        try {
            // 获取服务器信息
            $serverInfo = $mcpClient->getServerInfo();
            if ($serverInfo !== null) {
                $capabilities['server_info'] = [
                    'name' => $serverInfo->name,
                    'version' => $serverInfo->version,
                ];
            }
        } catch (\Exception $e) {
            Log::warning('获取服务器信息失败', ['error' => $e->getMessage()]);
        }

        try {
            // 获取工具列表
            $toolsResult = $mcpClient->listTools();
            foreach ($toolsResult->tools as $tool) {
                $capabilities['tools'][] = [
                    'name' => $tool->name,
                    'description' => $tool->description,
                ];
            }
        } catch (\Exception $e) {
            Log::warning('获取工具列表失败', ['error' => $e->getMessage()]);
        }

        try {
            // 获取资源列表
            $resourcesResult = $mcpClient->listResources();
            foreach ($resourcesResult->resources as $resource) {
                $capabilities['resources'][] = [
                    'uri' => $resource->uri,
                    'name' => $resource->name,
                ];
            }
        } catch (\Exception $e) {
            Log::warning('获取资源列表失败', ['error' => $e->getMessage()]);
        }

        try {
            // 获取提示列表
            $promptsResult = $mcpClient->listPrompts();
            foreach ($promptsResult->prompts as $prompt) {
                $capabilities['prompts'][] = [
                    'name' => $prompt->name,
                    'description' => $prompt->description,
                ];
            }
        } catch (\Exception $e) {
            Log::warning('获取提示列表失败', ['error' => $e->getMessage()]);
        }

        return $capabilities;
    }
}

<?php

namespace App\Services;

use App\Models\McpClient;
use Illuminate\Support\Facades\Log;
use PhpMcp\Client\Client;
use PhpMcp\Client\Enum\TransportType;
use PhpMcp\Client\ServerConfig;

/**
 * MCP 客户端服务类
 *
 * 封装 php-mcp/client 的连接和调用逻辑
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

        $mcpClient = $this->createClient($client);

        // 使用同步 API 初始化连接
        try {
            $mcpClient->initialize();
            $this->connections[$client->id] = $mcpClient;

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

        $client->update([
            'status' => McpClient::STATUS_INACTIVE,
        ]);
    }

    /**
     * 测试客户端连接
     *
     * @param  McpClient  $client  MCP 客户端配置模型
     * @return array{success: bool, message: string, capabilities: array|null}
     */
    public function testConnection(McpClient $client): array
    {
        try {
            $mcpClient = $this->createClient($client);
            $mcpClient->initialize();

            // 获取能力信息
            $capabilities = $this->fetchCapabilities($mcpClient);

            $mcpClient->disconnect();

            // 更新状态
            $client->update([
                'status' => McpClient::STATUS_ACTIVE,
                'last_connected_at' => now(),
                'connection_error' => null,
                'capabilities' => $capabilities,
            ]);

            return [
                'success' => true,
                'message' => '连接成功',
                'capabilities' => $capabilities,
            ];
        } catch (\Exception $e) {
            $client->update([
                'status' => McpClient::STATUS_ERROR,
                'connection_error' => $e->getMessage(),
            ]);

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

            foreach ($result->getTools() as $tool) {
                $tools[] = [
                    'name' => $tool->getName(),
                    'description' => $tool->getDescription(),
                    'input_schema' => $tool->getInputSchema(),
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

            // 解析结果
            $content = [];
            foreach ($result->getContent() as $item) {
                if ($item->isText()) {
                    $content[] = [
                        'type' => 'text',
                        'text' => $item->getText(),
                    ];
                } elseif ($item->isImage()) {
                    $content[] = [
                        'type' => 'image',
                        'data' => $item->getData(),
                        'mime_type' => $item->getMimeType(),
                    ];
                } elseif ($item->isResource()) {
                    $content[] = [
                        'type' => 'resource',
                        'uri' => $item->getUri(),
                        'name' => $item->getName(),
                    ];
                }
            }

            return [
                'content' => $content,
                'is_error' => $result->isError(),
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

            foreach ($result->getResourceTemplates() as $resource) {
                $resources[] = [
                    'uri' => $resource->getUri(),
                    'name' => $resource->getName(),
                    'description' => $resource->getDescription(),
                    'mime_type' => $resource->getMimeType(),
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

            foreach ($result->getPrompts() as $prompt) {
                $prompts[] = [
                    'name' => $prompt->getName(),
                    'description' => $prompt->getDescription(),
                    'arguments' => $prompt->getArguments(),
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
     * @param  Client  $mcpClient  连接实例（可选）
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
        // 构建服务器配置
        if ($client->isHttp()) {
            // HTTP 传输
            $serverConfig = new ServerConfig(
                name: $client->slug,
                transport: TransportType::Http,
                timeout: (float) $client->timeout,
                url: $client->url,
                headers: $client->headers ?? [],
            );
        } else {
            // Stdio 传输
            $serverConfig = new ServerConfig(
                name: $client->slug,
                transport: TransportType::Stdio,
                timeout: (float) $client->timeout,
                command: $client->command,
                args: $client->args ?? [],
            );
        }

        // 使用 ClientBuilder 构建客户端
        return Client::make()
            ->withClientInfo('CdApi MCP Client', '1.0.0')
            ->withServerConfig($serverConfig)
            ->build();
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
            $capabilities['server_info'] = [
                'name' => $mcpClient->getServerName(),
                'version' => $mcpClient->getServerVersion(),
                'protocol_version' => $mcpClient->getNegotiatedProtocolVersion(),
            ];
        } catch (\Exception $e) {
            Log::warning('获取服务器信息失败', ['error' => $e->getMessage()]);
        }

        try {
            // 获取工具列表
            $toolsResult = $mcpClient->listTools();
            foreach ($toolsResult->getTools() as $tool) {
                $capabilities['tools'][] = [
                    'name' => $tool->getName(),
                    'description' => $tool->getDescription(),
                ];
            }
        } catch (\Exception $e) {
            Log::warning('获取工具列表失败', ['error' => $e->getMessage()]);
        }

        try {
            // 获取资源列表
            $resourcesResult = $mcpClient->listResources();
            foreach ($resourcesResult->getResourceTemplates() as $resource) {
                $capabilities['resources'][] = [
                    'uri' => $resource->getUri(),
                    'name' => $resource->getName(),
                ];
            }
        } catch (\Exception $e) {
            Log::warning('获取资源列表失败', ['error' => $e->getMessage()]);
        }

        try {
            // 获取提示列表
            $promptsResult = $mcpClient->listPrompts();
            foreach ($promptsResult->getPrompts() as $prompt) {
                $capabilities['prompts'][] = [
                    'name' => $prompt->getName(),
                    'description' => $prompt->getDescription(),
                ];
            }
        } catch (\Exception $e) {
            Log::warning('获取提示列表失败', ['error' => $e->getMessage()]);
        }

        return $capabilities;
    }
}

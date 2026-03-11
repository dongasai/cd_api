<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\ChannelRequestLog;
use App\Models\RequestLog;
use App\Services\Provider\DTO\ProviderRequest;
use App\Services\Provider\ProviderManager;
use App\Services\Router\ChannelRouterService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

/**
 * 直接使用渠道驱动重放请求，绕过 ProxyServer
 *
 * 流程:
 * 1. 从 channel_request_logs 表获取实际发送到上游的请求体
 * 2. 通过 ProviderManager 获取渠道驱动
 * 3. 直接使用原始请求体字符串发送请求
 *
 * php artisan request:replay-channel --request-id=1004
 * php artisan request:replay-channel --audit-id=500
 * php artisan request:replay-channel --request-id=req_abc123
 */
class ReplayRequestChannel extends Command
{
    protected $signature = 'request:replay-channel
                            {--request-id= : 请求 ID 或 request_id}
                            {--audit-id= : 审计 ID}
                            {--channel-id= : 指定渠道 ID (可选，不指定则自动选择)}
                            {--show-body : 显示实际发送到上游的请求体}';

    protected $description = '直接使用渠道驱动重放请求 - 绕过 ProxyServer';

    public function handle(): int
    {
        // 临时禁用日志，避免只读文件系统错误
        config(['logging.default' => 'null']);

        $requestId = $this->option('request-id');
        $auditId = $this->option('audit-id');
        $channelId = $this->option('channel-id');

        // 二选一验证
        if (! $requestId && ! $auditId) {
            $this->error('请提供 --request-id 或 --audit-id 参数之一');

            return self::FAILURE;
        }

        if ($requestId && $auditId) {
            $this->error('--request-id 和 --audit-id 参数不能同时使用');

            return self::FAILURE;
        }

        // 查找请求日志
        if ($auditId) {
            // 从审计表查找
            $auditLog = AuditLog::find($auditId);
            if (! $auditLog) {
                $this->error("审计记录不存在：{$auditId}");

                return self::FAILURE;
            }

            // 通过审计表的关联查找请求日志
            $requestLog = $auditLog->requestLog;
            if (! $requestLog) {
                // 尝试通过 channel_request_logs 关联查找
                $channelRequestLog = ChannelRequestLog::where('audit_log_id', $auditId)->first();
                if ($channelRequestLog) {
                    $requestLog = $channelRequestLog->requestLog;
                }
            }

            if (! $requestLog) {
                $this->error("审计记录 {$auditId} 未找到关联的请求记录");

                return self::FAILURE;
            }
        } else {
            // 支持数字 ID 或 request_id
            $requestLog = is_numeric($requestId)
                ? RequestLog::find((int) $requestId)
                : RequestLog::where('request_id', $requestId)->first();
            if (! $requestLog) {
                $this->error("请求记录不存在：{$requestId}");

                return self::FAILURE;
            }
        }

        // 显示请求信息
        $this->displayRequestInfo($requestLog);

        // 发送请求
        return $this->sendRequestViaChannel($requestLog, $channelId);
    }

    protected function displayRequestInfo(RequestLog $log): void
    {
        $this->info('========== 请求信息 ==========');
        $this->info("请求 ID: {$log->request_id}");
        $this->info("渠道 ID: {$log->channel_id}");
        $this->info("请求路径：{$log->path}");
        $this->info("请求方法：{$log->method}");
        $this->info("模型：{$log->model}");
        if ($log->upstream_model && $log->upstream_model !== $log->model) {
            $this->info("上游模型：{$log->upstream_model}");
        }
        $this->info("创建时间：{$log->created_at?->format('Y-m-d H:i:s')}");

        // 显示请求参数
        $body = $this->getRequestBody($log);
        $this->newLine();
        $this->info('---------- 请求参数 ----------');

        // 简化显示
        $displayBody = Arr::except($body, ['messages', 'tools', 'system']);
        $this->line(json_encode($displayBody, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // 显示消息数量
        if (isset($body['messages'])) {
            $messageCount = count($body['messages']);
            $this->info("消息数量：{$messageCount}");
        }

        // 显示是否有工具
        if (isset($body['tools']) && ! empty($body['tools'])) {
            $this->info('工具数量：'.count($body['tools']));
        }
    }

    protected function getRequestBody(RequestLog $log): array
    {
        // 使用原始请求体
        if (! empty($log->body_text)) {
            return json_decode($log->body_text, true) ?? [];
        }

        return [];
    }

    protected function sendRequestViaChannel(RequestLog $log, ?int $channelId): int
    {
        $body = $this->getRequestBody($log);

        if (empty($body)) {
            $this->error('请求体为空');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('========== 直接调用渠道驱动 ==========');

        try {
            $startTime = microtime(true);

            // 获取 API Key（可选）
            $apiKey = $this->getApiKey($log);

            // 获取渠道
            $channel = $this->getChannel($log, $channelId, $body['model'] ?? '', $apiKey);
            if (! $channel) {
                $this->error('无法获取可用渠道');

                return self::FAILURE;
            }

            $this->info("选中渠道: ID={$channel->id}, Name={$channel->name}, Provider={$channel->provider}");

            // 解析实际模型名称
            $actualModel = $this->resolveModel($body['model'] ?? '', $channel);

            // 获取渠道驱动
            $providerManager = app(ProviderManager::class);
            $provider = $providerManager->getForChannel($channel, []);

            $this->info('供应商驱动: '.get_class($provider));
            $this->info("实际模型: {$actualModel}");

            // 构建供应商请求
            $providerRequest = $this->buildProviderRequest($body, $actualModel);

            // 显示实际请求体（如果指定了 --show-body 选项）
            if ($this->option('show-body')) {
                $this->newLine();
                $this->info('---------- 实际发送的请求体 ----------');
                $requestBody = $channel->provider === 'anthropic'
                    ? $providerRequest->toAnthropicFormat()
                    : $providerRequest->toOpenAIFormat();
                $this->line(json_encode($requestBody, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            }

            // 判断是否流式请求
            $isStream = $body['stream'] ?? false;

            if ($isStream) {
                $result = $provider->sendStream($providerRequest);
            } else {
                $result = $provider->send($providerRequest);
            }

            $latency = round((microtime(true) - $startTime) * 1000, 2);

            $this->newLine();
            $this->info('========== 请求完成 ==========');
            $this->info("耗时：{$latency}ms");

            // 显示响应结果
            $this->displayResponse($result, $isStream, $provider);

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("请求异常：{$e->getMessage()}");
            $this->error('文件：'.$e->getFile().':'.$e->getLine());

            return self::FAILURE;
        }
    }

    /**
     * 获取 API Key
     */
    protected function getApiKey(RequestLog $log): ?ApiKey
    {
        $auditLog = AuditLog::where('request_id', $log->request_id)->first();
        if ($auditLog && $auditLog->api_key_id) {
            return ApiKey::find($auditLog->api_key_id);
        }

        return null;
    }

    /**
     * 获取渠道
     */
    protected function getChannel(RequestLog $log, ?int $channelId, string $model, ?ApiKey $apiKey): ?\App\Models\Channel
    {
        // 如果指定了渠道 ID，直接使用
        if ($channelId) {
            $channel = \App\Models\Channel::find($channelId);
            if (! $channel) {
                $this->error("指定的渠道不存在：{$channelId}");

                return null;
            }

            return $channel;
        }

        // 如果请求日志中有渠道 ID，尝试使用原渠道
        if ($log->channel_id) {
            $channel = \App\Models\Channel::find($log->channel_id);
            if ($channel && $channel->isActive()) {
                $this->info("使用原始渠道: ID={$channel->id}");

                return $channel;
            }
        }

        // 自动选择渠道
        if (empty($model)) {
            $this->error('无法自动选择渠道：模型名称为空');

            return null;
        }

        try {
            $channelRouter = app(ChannelRouterService::class);
            $context = [];
            if ($apiKey) {
                $context['api_key'] = $apiKey;
            }

            return $channelRouter->selectChannel($model, $context);
        } catch (\Exception $e) {
            $this->error("自动选择渠道失败：{$e->getMessage()}");

            return null;
        }
    }

    /**
     * 解析模型名称
     */
    protected function resolveModel(string $model, \App\Models\Channel $channel): string
    {
        $channelModel = \App\Models\ChannelModel::where('channel_id', $channel->id)
            ->where('model_name', $model)
            ->where('is_enabled', true)
            ->first();

        if ($channelModel && $channelModel->mapped_model) {
            return $channelModel->mapped_model;
        }

        return $model;
    }

    /**
     * 构建供应商请求
     */
    protected function buildProviderRequest(array $body, string $actualModel): ProviderRequest
    {
        // 使用 ProviderRequest::fromArray 来构建请求
        // 先更新模型名称
        $body['model'] = $actualModel;

        return ProviderRequest::fromArray($body);
    }

    protected function displayResponse(mixed $result, bool $isStream, $provider = null): void
    {
        $this->newLine();
        $this->info('---------- 响应内容 ----------');

        if ($isStream && $result instanceof \Generator) {
            $this->info('流式响应');
            $this->warn('注意：流式响应在命令行下无法完整查看');

            // 尝试收集流式内容
            $this->newLine();
            $this->info('---------- 流式内容预览 ----------');
            $content = '';
            foreach ($result as $chunk) {
                echo $chunk;
                $content .= $chunk;
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
            $this->newLine();
            $this->info("\n流式内容总长度：".mb_strlen($content).' 字符');

            // 显示实际请求信息（流式响应）
            if ($provider && method_exists($provider, 'getLastRequestInfo')) {
                $actualRequestInfo = $provider->getLastRequestInfo();
                if ($actualRequestInfo) {
                    $this->newLine();
                    $this->info('---------- 实际请求信息 ----------');
                    $this->info("URL: {$actualRequestInfo->url}");
                    $this->info('Method: POST');
                    if ($actualRequestInfo->headers) {
                        $this->info('Headers: '.json_encode($actualRequestInfo->headers, JSON_UNESCAPED_UNICODE));
                    }
                }
            }
        } elseif ($result instanceof \App\Services\Provider\DTO\ProviderResponse) {
            // 非流式响应
            $data = $result->toArray();

            // 成功响应
            if (isset($data['choices'])) {
                $content = $data['choices'][0]['message']['content'] ?? '';
                if ($content) {
                    $this->displayContent($content);
                }
            } elseif (isset($data['content'])) {
                $content = is_array($data['content'])
                    ? ($data['content'][0]['text'] ?? '')
                    : $data['content'];
                if ($content) {
                    $this->displayContent($content);
                }
            }

            // 显示 token 使用情况
            if (isset($data['usage'])) {
                $this->newLine();
                $this->info('Token 使用:');
                $this->info("  输入：{$data['usage']['prompt_tokens']}");
                $this->info("  输出：{$data['usage']['completion_tokens']}");
                $this->info("  总计：{$data['usage']['total_tokens']}");
            }

            // 显示结束原因
            if (isset($data['choices'][0]['finish_reason'])) {
                $this->info("结束原因：{$data['choices'][0]['finish_reason']}");
            }

            // 显示错误信息
            if (isset($data['error'])) {
                $this->error('请求失败');
                $this->error('详情：'.json_encode($data['error'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            }

            // 显示实际请求信息（非流式响应）
            if ($provider && method_exists($provider, 'getLastRequestInfo')) {
                $actualRequestInfo = $provider->getLastRequestInfo();
                if ($actualRequestInfo) {
                    $this->newLine();
                    $this->info('---------- 实际请求信息 ----------');
                    $this->info("URL: {$actualRequestInfo->url}");
                    $this->info('Method: POST');
                    if ($actualRequestInfo->headers) {
                        $this->info('Headers: '.json_encode($actualRequestInfo->headers, JSON_UNESCAPED_UNICODE));
                    }
                }
            }
        } else {
            $this->info('响应类型：'.gettype($result));
        }
    }

    protected function displayContent(string $content): void
    {
        if (mb_strlen($content) > 500) {
            $this->line(mb_substr($content, 0, 500).'...');
            $this->info('(内容已截断，总长度：'.mb_strlen($content).' 字符)');
        } else {
            $this->line($content);
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\ChannelRequestLog;
use App\Models\RequestLog;
use App\Services\Router\ProxyServer;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log as FacadesLog;

/**
 * 直接重放请求，不经过 HTTP，直接组装 Request 对象调用 ProxyServer
 *
 * php artisan request:replay-direct --request-id=1004
 * php artisan request:replay-direct --audit-id=500
 * php artisan request:replay-direct --request-id=req_abc123
 * php artisan request:replay-direct --request-id=1004 --stream    # 强制流式请求
 * php artisan request:replay-direct --request-id=1004 --no-stream # 强制非流式请求
 */
class ReplayRequestDirectly extends Command
{
    protected $signature = 'request:replay-direct
                            {--request-id= : 请求 ID 或 request_id}
                            {--audit-id= : 审计 ID}
                            {--stream : 强制使用流式请求}
                            {--no-stream : 强制使用非流式请求}';

    protected $description = '直接重放请求 - 不经过 HTTP，直接调用 ProxyServer';

    public function handle(): int
    {

        FacadesLog::debug('直接重放请求');
        $requestId = $this->option('request-id');
        $auditId = $this->option('audit-id');
        $forceStream = $this->option('stream');
        $forceNoStream = $this->option('no-stream');

        // 二选一验证
        if (! $requestId && ! $auditId) {
            $this->error('请提供 --request-id 或 --audit-id 参数之一');

            return self::FAILURE;
        }

        if ($requestId && $auditId) {
            $this->error('--request-id 和 --audit-id 参数不能同时使用');

            return self::FAILURE;
        }

        // stream 参数验证
        if ($forceStream && $forceNoStream) {
            $this->error('--stream 和 --no-stream 参数不能同时使用');

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
        return $this->sendRequestDirectly($requestLog, $forceStream, $forceNoStream);
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

    protected function sendRequestDirectly(RequestLog $log, bool $forceStream = false, bool $forceNoStream = false): int
    {
        $body = $this->getRequestBody($log);

        if (empty($body)) {
            $this->error('请求体为空');

            return self::FAILURE;
        }

        // 根据 stream 参数覆盖原始请求体中的 stream 设置
        if ($forceStream) {
            $body['stream'] = true;
            $this->info('已强制启用流式请求');
        } elseif ($forceNoStream) {
            $body['stream'] = false;
            $this->info('已强制禁用流式请求');
        }

        $this->newLine();
        $this->info('========== 直接调用 ProxyServer ==========');

        try {
            $startTime = microtime(true);

            // 组装 Laravel Request 对象
            $laravelRequest = $this->buildRequest($log, $body);

            // 创建 ProxyServer 实例
            $proxyServer = app(ProxyServer::class);

            // 根据路径判断协议类型
            $protocol = $this->detectProtocol($log->path);
            $this->info("协议类型：{$protocol}");

            // 直接调用 ProxyServer 的 proxy 方法
            $result = $proxyServer->proxy($laravelRequest, $protocol);

            $latency = round((microtime(true) - $startTime) * 1000, 2);

            // 获取新创建的审计日志 ID
            $newAuditLogId = $this->getNewAuditLogId($log);

            $this->newLine();
            $this->info('========== 请求完成 ==========');
            $this->info("耗时：{$latency}ms");

            // 显示新的审计日志 ID
            if ($newAuditLogId) {
                $this->newLine();
                $this->info("✅ 新审计日志 ID: {$newAuditLogId}");
            }

            // 显示响应结果
            $this->displayResponse($result);

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("请求异常：{$e->getMessage()}");
            $this->error('文件：'.$e->getFile().':'.$e->getLine());

            return self::FAILURE;
        }
    }

    protected function buildRequest(RequestLog $log, array $body): Request
    {
        // 从 request_log 中获取原始 headers
        $headers = $this->getOriginalHeaders($log);

        // 直接使用 initialize 创建请求对象，不使用 create()
        // 因为 create() 对于 POST 请求不会正确解析 JSON body
        $request = new Request(
            [],  // query parameters
            $body,  // request body (parsed)
            [],  // attributes
            [],  // cookies
            [],  // files
            [],  // server (will be populated by Symfony)
            json_encode($body)
        );

        // 设置请求的方法和路径
        $request->setMethod($log->method);
        $request->server->set('REQUEST_URI', $log->path);
        $request->server->set('PATH_INFO', $log->path);

        // 设置 headers 到 server 参数中
        foreach ($headers as $name => $value) {
            $serverKey = 'HTTP_'.strtoupper(str_replace('-', '_', $name));
            $request->server->set($serverKey, $value);
        }

        // 设置请求属性（模拟中间件处理后的结果）
        // 从 audit_log 中获取 api_key 信息
        $auditLog = AuditLog::where('request_id', $log->request_id)->first();
        if ($auditLog && $auditLog->api_key_id) {
            $apiKey = ApiKey::find($auditLog->api_key_id);
            if ($apiKey) {
                // 设置 api_key 属性，ProxyServer 需要这个信息
                $request->attributes->set('api_key', $apiKey);
            }
        }

        // 设置其他属性
        $request->attributes->set('request_id', $log->request_id);

        return $request;
    }

    protected function getOriginalHeaders(RequestLog $log): array
    {
        if (empty($log->headers)) {
            return [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer sk-placeholder',
            ];
        }

        $headers = is_string($log->headers) ? json_decode($log->headers, true) : $log->headers;

        if (! is_array($headers)) {
            return [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer sk-placeholder',
            ];
        }

        // 提取 header 值（原格式可能是 ['Authorization' => ['Bearer sk-xxx']]）
        $result = [];
        foreach ($headers as $name => $values) {
            $result[$name] = is_array($values) ? reset($values) : $values;
        }

        // 确保有 Content-Type
        if (! isset($result['Content-Type'])) {
            $result['Content-Type'] = 'application/json';
        }

        return $result;
    }

    protected function detectProtocol(string $path): string
    {
        // 根据路径判断协议类型
        if (str_contains($path, '/anthropic')) {
            return 'anthropic';
        }

        // 默认使用 openai 协议
        return 'openai';
    }

    protected function displayResponse(mixed $result): void
    {
        $this->newLine();
        $this->info('---------- 响应内容 ----------');

        // ProxyServer::proxy() 返回的是数组或者 Generator
        if (is_array($result)) {
            $data = $result;

            // 显示完整响应数据（调试用）
            $this->info('响应数据：');
            $this->line(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

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
                // \dump($data['usage']);
                $this->newLine();
                $this->info('Token 使用:');
                $this->info("  输入：{$data['usage']['input_tokens']}");
                $this->info("  输出：{$data['usage']['output_tokens']}");
                $this->info("  缓存：{$data['usage']['cache_read_input_tokens']}");
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
        } elseif ($result instanceof \Generator) {
            $this->info('流式响应');
            $this->warn('注意：流式响应在命令行下无法完整查看');

            // 尝试收集流式内容
            $this->newLine();
            $this->info('---------- 流式内容预览 ----------');

            $rawContent = '';
            $assembledContent = '';
            $reasoningContent = '';
            $usage = null;
            $finishReason = null;

            foreach ($result as $chunk) {
                echo $chunk;
                $rawContent .= $chunk;

                // 解析 SSE 事件
                $parsedEvent = $this->parseSSEChunk($chunk);
                if ($parsedEvent) {
                    // 提取文本增量
                    if (isset($parsedEvent['delta']['text'])) {
                        $assembledContent .= $parsedEvent['delta']['text'];
                    }
                    // 提取思考增量
                    if (isset($parsedEvent['delta']['thinking'])) {
                        $reasoningContent .= $parsedEvent['delta']['thinking'];
                    }
                    // 合并 usage（需要累加，因为可能来自多个事件）
                    if (isset($parsedEvent['usage'])) {
                        if ($usage === null) {
                            $usage = $parsedEvent['usage'];
                        } else {
                            // 合并数据，保留非零值
                            if (isset($parsedEvent['usage']['input_tokens'])) {
                                $usage['input_tokens'] = $parsedEvent['usage']['input_tokens'];
                            }
                            if (isset($parsedEvent['usage']['output_tokens']) && $parsedEvent['usage']['output_tokens'] > 0) {
                                $usage['output_tokens'] = $parsedEvent['usage']['output_tokens'];
                            }
                            if (isset($parsedEvent['usage']['cache_read_input_tokens'])) {
                                $usage['cache_read_input_tokens'] = $parsedEvent['usage']['cache_read_input_tokens'];
                            }
                        }
                    }
                    // 提取 finish_reason
                    if (isset($parsedEvent['finish_reason'])) {
                        $finishReason = $parsedEvent['finish_reason'];
                    }
                }

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }

            $this->newLine(2);
            $this->info('---------- 组装后的内容 ----------');
            if ($reasoningContent) {
                $this->newLine();
                $this->comment('【思考过程】');
                $this->displayContent($reasoningContent);
            }
            if ($assembledContent) {
                $this->newLine();
                $this->comment('【回复内容】');
                $this->displayContent($assembledContent);
            }
            if (! $reasoningContent && ! $assembledContent) {
                $this->warn('(无文本内容)');
            }

            // 显示 token 使用情况
            if ($usage) {
                $this->newLine();
                $this->info('Token 使用:');
                $this->info("  输入：{$usage['input_tokens']}");
                $this->info("  输出：{$usage['output_tokens']}");
                if (isset($usage['cache_read_input_tokens'])) {
                    $this->info("  缓存：{$usage['cache_read_input_tokens']}");
                }
            }

            // 显示结束原因
            if ($finishReason) {
                $this->info("结束原因：{$finishReason}");
            }

            $this->newLine();
            $this->info("\n流式内容总长度：".mb_strlen($rawContent).' 字符');
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

    protected function getNewAuditLogId(RequestLog $originalLog): ?int
    {
        // 查找最新创建的审计日志（通过 request_id 关联）
        $newAuditLog = AuditLog::where('request_id', $originalLog->request_id)
            ->latest('id')
            ->first();

        return $newAuditLog?->id;
    }

    /**
     * 解析 SSE 格式的数据块
     *
     * @param  string  $chunk  SSE 数据块
     * @return array|null 解析后的数据
     */
    protected function parseSSEChunk(string $chunk): ?array
    {
        $lines = explode("\n", trim($chunk));
        $event = '';
        $data = '';

        // 解析 SSE 格式
        foreach ($lines as $line) {
            if (str_starts_with($line, 'event:')) {
                $event = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:')) {
                $data = trim(substr($line, 5));
            }
        }

        if (empty($data)) {
            return null;
        }

        $parsed = json_decode($data, true);
        if ($parsed === null) {
            return null;
        }

        $result = [
            'event' => $event,
            'delta' => [],
            'usage' => null,
            'finish_reason' => null,
        ];

        // 解析 Anthropic 格式的事件
        switch ($event) {
            case 'content_block_delta':
                // 先获取 delta 类型
                $deltaType = $parsed['delta']['type'] ?? '';

                // 处理文本增量 (text_delta)
                if ($deltaType === 'text_delta' && isset($parsed['delta']['text'])) {
                    $result['delta']['text'] = $parsed['delta']['text'];
                }
                // 处理思考增量 (thinking_delta)
                if ($deltaType === 'thinking_delta' && isset($parsed['delta']['thinking'])) {
                    $result['delta']['thinking'] = $parsed['delta']['thinking'];
                }
                break;

            case 'message_delta':
                // message_delta 可能出现多次，需要合并 usage 数据
                // 但只保留非零的 output_tokens（避免最后的 delta 事件用 0 覆盖）
                if (isset($parsed['usage'])) {
                    // 如果 usage 还未初始化，先初始化
                    if ($result['usage'] === null) {
                        $result['usage'] = [
                            'input_tokens' => 0,
                            'output_tokens' => 0,
                            'cache_read_input_tokens' => 0,
                        ];
                    }

                    // 合并非零的字段
                    if (isset($parsed['usage']['input_tokens'])) {
                        $result['usage']['input_tokens'] = $parsed['usage']['input_tokens'];
                    }
                    if (isset($parsed['usage']['output_tokens']) && $parsed['usage']['output_tokens'] > 0) {
                        $result['usage']['output_tokens'] = $parsed['usage']['output_tokens'];
                    }
                    if (isset($parsed['usage']['cache_read_input_tokens'])) {
                        $result['usage']['cache_read_input_tokens'] = $parsed['usage']['cache_read_input_tokens'];
                    }
                }
                if (isset($parsed['delta']['stop_reason'])) {
                    $result['finish_reason'] = $parsed['delta']['stop_reason'];
                }
                break;

            case 'message_start':
                if (isset($parsed['message']['usage'])) {
                    $result['usage'] = [
                        'input_tokens' => $parsed['message']['usage']['input_tokens'] ?? 0,
                        'output_tokens' => $parsed['message']['usage']['output_tokens'] ?? 0,
                        'cache_read_input_tokens' => $parsed['message']['usage']['cache_read_input_tokens'] ?? 0,
                    ];
                }
                break;
        }

        return $result;
    }
}

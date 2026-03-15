<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ChannelRequestLog;
use App\Models\RequestLog;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * 重放客户端真实请求,使用真实Http重新请求到本系统
 *
 * 通过请求ID重放: php artisan request:replay --request-id=1004
 * 通过审计ID重放: php artisan request:replay --audit-id=500
 * 通过request_id重放: php artisan request:replay --request-id=req_abc123
 */
class ReplayRequest extends Command
{
    protected $signature = 'request:replay
                            {--request-id= : 请求 ID 或 request_id}
                            {--audit-id= : 审计 ID}
                            {--timeout=120 : 请求超时时间 (秒)}
                            {--dry-run : 仅显示请求信息，不实际发送}';

    protected $description = '复现请求 - 根据请求 ID 或审计 ID 重新发送真实请求';

    public function handle(): int
    {
        $requestId = $this->option('request-id');
        $auditId = $this->option('audit-id');

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

        if ($this->option('dry-run')) {
            $this->warn("\n[DRY-RUN] 仅显示请求信息，未发送实际请求");

            return self::SUCCESS;
        }

        // 发送请求
        return $this->sendRequest($requestLog);
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

    protected function sendRequest(RequestLog $log): int
    {
        $body = $this->getRequestBody($log);

        if (empty($body)) {
            $this->error('请求体为空');

            return self::FAILURE;
        }

        // 确定模型
        $model = $log->upstream_model ?? $log->model ?? $body['model'] ?? null;

        if (! $model) {
            $this->error('无法确定请求模型');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('========== 发送真实请求 ==========');
        $this->info("模型：{$model}");

        try {
            $startTime = microtime(true);

            // 判断是否为流式请求
            $isStream = $body['stream'] ?? false;

            if ($isStream) {
                $this->sendStreamRequest($log, $body);
            } else {
                $this->sendSyncRequest($log, $body);
            }

            $latency = round((microtime(true) - $startTime) * 1000, 2);

            $this->newLine();
            $this->info('========== 请求完成 ==========');
            $this->info("耗时：{$latency}ms");

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("请求异常：{$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function sendStreamRequest(RequestLog $log, array $body): string
    {
        // 构建本系统的请求 URL
        $baseUrl = config('app.url');
        $path = $log->path;
        $path = ltrim($path, '/');
        $url = rtrim($baseUrl, '/').'/'.$path;

        // 从请求日志中获取原始 headers
        $headers = $this->getOriginalHeaders($log);

        $this->newLine();
        $this->info('---------- 发送流式请求 ----------');
        $this->info("URL: {$url}");

        $fullContent = '';
        $reasoningContent = '';
        $usage = null;
        $finishReason = null;

        try {
            $response = Http::withHeaders($headers)
                ->timeout((int) $this->option('timeout'))
                ->connectTimeout(30)
                ->post($url, $body);

            $statusCode = $response->status();

            $this->newLine();
            $this->info('---------- 响应内容 ----------');
            $this->info("状态码：{$statusCode}");

            if ($statusCode >= 200 && $statusCode < 300) {
                $rawContent = $response->body();
                $this->info('流式响应');
                $this->warn('注意：流式响应在命令行下无法完整查看');

                $this->newLine();
                $this->info('---------- 流式内容预览 ----------');

                // 解析 SSE 流式内容
                $lines = explode("\n", $rawContent);
                foreach ($lines as $line) {
                    if (str_starts_with($line, 'data:')) {
                        $data = trim(substr($line, 5));

                        if (empty($data) || $data === '[DONE]') {
                            continue;
                        }

                        $parsed = json_decode($data, true);
                        if ($parsed === null) {
                            continue;
                        }

                        // 处理 Anthropic 格式
                        if (isset($parsed['delta'])) {
                            // 文本增量
                            if (isset($parsed['delta']['text'])) {
                                $fullContent .= $parsed['delta']['text'];
                            }
                            // 思考增量
                            if (isset($parsed['delta']['thinking'])) {
                                $reasoningContent .= $parsed['delta']['thinking'];
                            }
                        }

                        // 提取 usage (支持多种格式，需要合并)
                        if (isset($parsed['usage'])) {
                            // message_delta 事件的 usage (通常只有 output_tokens)
                            // 注意：后面的 delta 可能覆盖前面的值，所以只保留非零的 output_tokens
                            if (isset($parsed['usage']['output_tokens']) && $parsed['usage']['output_tokens'] > 0) {
                                $usage['output_tokens'] = $parsed['usage']['output_tokens'];
                            }
                            // 合并其他字段
                            foreach (['input_tokens', 'cache_read_input_tokens', 'cache_creation_input_tokens'] as $key) {
                                if (isset($parsed['usage'][$key])) {
                                    $usage[$key] = $parsed['usage'][$key];
                                }
                            }
                        } elseif (isset($parsed['message']['usage'])) {
                            // message_start 事件的 usage (有 input_tokens 和 output_tokens)
                            $usage = array_merge($usage ?? [], $parsed['message']['usage']);
                        }

                        // 提取 finish_reason
                        if (isset($parsed['delta']['stop_reason'])) {
                            $finishReason = $parsed['delta']['stop_reason'];
                        }

                        // 输出原始行（预览）
                        echo $line."\n";
                    } elseif (str_starts_with($line, 'event:')) {
                        echo $line."\n";
                    }
                }

                $this->newLine(2);
                $this->info('---------- 组装后的内容 ----------');

                if ($reasoningContent) {
                    $this->newLine();
                    $this->comment('【思考过程】');
                    $this->displayContent($reasoningContent);
                }

                if ($fullContent) {
                    $this->newLine();
                    $this->comment('【回复内容】');
                    $this->displayContent($fullContent);
                }

                if (! $reasoningContent && ! $fullContent) {
                    $this->warn('(无文本内容)');
                }

                // 显示 token 使用情况
                if ($usage) {
                    $this->newLine();
                    $this->info('Token 使用:');
                    $hasInput = false;
                    $hasOutput = false;

                    // 输入 token
                    if (array_key_exists('input_tokens', $usage)) {
                        $this->info("  输入：{$usage['input_tokens']}");
                        $hasInput = true;
                    } elseif (array_key_exists('prompt_tokens', $usage)) {
                        $this->info("  输入：{$usage['prompt_tokens']}");
                        $hasInput = true;
                    }

                    // 输出 token
                    if (array_key_exists('output_tokens', $usage)) {
                        $this->info("  输出：{$usage['output_tokens']}");
                        $hasOutput = true;
                    } elseif (array_key_exists('completion_tokens', $usage)) {
                        $this->info("  输出：{$usage['completion_tokens']}");
                        $hasOutput = true;
                    }

                    // 缓存
                    if (isset($usage['cache_read_input_tokens'])) {
                        $this->info("  缓存：{$usage['cache_read_input_tokens']}");
                    }

                    // 如果没有 token 数据，提示
                    if (! $hasInput && ! $hasOutput) {
                        $this->warn('  (无 token 数据)');
                    }
                }

                // 显示结束原因
                if ($finishReason) {
                    $this->info("结束原因：{$finishReason}");
                }
            } else {
                // 错误响应
                $this->error('请求失败');
                $result = $response->json();
                $this->error('详情：'.json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            }

        } catch (\Throwable $e) {
            $this->error("流式请求异常：{$e->getMessage()}");
        }

        return $fullContent;
    }

    protected function sendSyncRequest(RequestLog $log, array $body): string
    {
        // 构建本系统的请求 URL
        $baseUrl = config('app.url');
        $path = $log->path;
        // 确保路径以 / 开头
        $path = ltrim($path, '/');
        $url = rtrim($baseUrl, '/').'/'.$path;

        // 从请求日志中获取原始 headers
        $headers = $this->getOriginalHeaders($log);

        $this->newLine();
        $this->info('---------- 发送请求 ----------');
        $this->info("URL: {$url}");

        $response = Http::withHeaders($headers)
            ->timeout((int) $this->option('timeout'))
            ->connectTimeout(30)
            ->post($url, $body);

        $statusCode = $response->status();
        $result = $response->json();

        $this->newLine();
        $this->info('---------- 响应内容 ----------');
        $this->info("状态码：{$statusCode}");

        if ($statusCode >= 200 && $statusCode < 300) {
            $content = '';
            // 成功响应
            if (isset($result['choices'])) {
                $content = $result['choices'][0]['message']['content'] ?? '';
                if ($content) {
                    $this->displayContent($content);
                }
            } elseif (isset($result['content'])) {
                $content = is_array($result['content'])
                    ? ($result['content'][0]['text'] ?? '')
                    : $result['content'];
                if ($content) {
                    $this->displayContent($content);
                }
            }

            // 显示 token 使用情况
            if (isset($result['usage'])) {
                $this->newLine();
                $this->info('Token 使用:');
                // 兼容 OpenAI 和 Anthropic 两种格式
                $inputTokens = $result['usage']['prompt_tokens'] ?? $result['usage']['input_tokens'] ?? 0;
                $outputTokens = $result['usage']['completion_tokens'] ?? $result['usage']['output_tokens'] ?? 0;
                $totalTokens = $result['usage']['total_tokens'] ?? ($inputTokens + $outputTokens);
                $this->info("  输入：{$inputTokens}");
                $this->info("  输出：{$outputTokens}");
                $this->info("  总计：{$totalTokens}");
            }

            // 显示结束原因
            if (isset($result['choices'][0]['finish_reason'])) {
                $this->info("结束原因：{$result['choices'][0]['finish_reason']}");
            }

            return $content;
        } else {
            // 错误响应
            $this->error('请求失败');
            $this->error('详情：'.json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        return '';
    }

    protected function getOriginalHeaders(RequestLog $log): array
    {
        if (empty($log->headers)) {
            return [
                'Content-Type' => 'application/json',
            ];
        }

        $headers = is_string($log->headers) ? json_decode($log->headers, true) : $log->headers;

        if (! is_array($headers)) {
            return [
                'Content-Type' => 'application/json',
            ];
        }

        // 提取 header 值（原格式可能是 ['Authorization' => ['Bearer sk-xxx']]）
        $result = [];
        foreach ($headers as $name => $values) {
            $result[$name] = is_array($values) ? reset($values) : $values;
        }

        return $result;
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

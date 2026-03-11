<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Channel;
use App\Models\ChannelModel;
use App\Models\ChannelRequestLog;
use App\Models\RequestLog;
use Illuminate\Console\Command;

/**
 * 使用 PHP curl 重放请求
 *
 * 从 request_logs 表读取数据，使用 PHP curl 扩展直接发送请求到上游
 *
 * php artisan request:replay-curl --request-id=1971
 * php artisan request:replay-curl --audit-id=500
 * php artisan request:replay-curl --request-id=req_abc123
 */
class ReplayRequestCurl extends Command
{
    protected $signature = 'request:replay-curl
                            {--request-id= : 请求 ID 或 request_id}
                            {--audit-id= : 审计 ID}
                            {--channel-id= : 指定渠道 ID (可选，不指定则使用原始渠道)}
                            {--timeout=120 : 请求超时时间(秒)}';

    protected $description = '使用 PHP curl 重放请求 - 直接发送到上游';

    public function handle(): int
    {
        $requestId = $this->option('request-id');
        $auditId = $this->option('audit-id');
        $channelId = $this->option('channel-id');

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
            $auditLog = AuditLog::find($auditId);
            if (! $auditLog) {
                $this->error("审计记录不存在：{$auditId}");

                return self::FAILURE;
            }

            // 直接从 request_logs 表获取
            $requestLog = RequestLog::where('request_id', $auditLog->request_id)->first();

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
        return $this->sendRequestWithCurl($requestLog, $channelId);
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
    }

    protected function sendRequestWithCurl(RequestLog $log, ?int $channelId): int
    {
        // 获取原始请求体字符串
        $bodyText = $log->body_text;
        if (empty($bodyText)) {
            $this->error('请求体为空');

            return self::FAILURE;
        }

        // 获取渠道
        $channel = $this->getChannel($log, $channelId);
        if (! $channel) {
            $this->error('无法获取渠道信息');

            return self::FAILURE;
        }

        $this->info("选中渠道: ID={$channel->id}, Name={$channel->name}, Provider={$channel->provider}");

        // 解析实际模型名称并替换
        $originalModel = $log->model;
        $actualModel = $this->resolveModel($originalModel, $channel);

        // 使用字符串替换模型名称
        if ($originalModel && $actualModel && $originalModel !== $actualModel) {
            $bodyText = str_replace('"model":"'.$originalModel.'"', '"model":"'.$actualModel.'"', $bodyText);
            $this->info("模型映射: {$originalModel} -> {$actualModel}");
        }

        // 构建 URL
        $url = $this->buildUrl($channel, $log);

        // 构建请求头（合并原始请求头和渠道认证头）
        $headers = $this->buildHeaders($channel, $log);

        // 使用 PHP curl 执行请求
        $this->newLine();
        $this->info('========== 执行请求 ==========');
        $this->info("URL: {$url}");

        $timeout = (int) $this->option('timeout');

        try {
            $startTime = microtime(true);

            // 初始化 curl
            $ch = curl_init();

            // 设置 curl 选项
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $bodyText,
                CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_HEADER => false,
            ]);

            // 执行请求
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $errno = curl_errno($ch);

            curl_close($ch);

            $latency = round((microtime(true) - $startTime) * 1000, 2);

            $this->newLine();
            $this->info('========== 响应结果 ==========');
            $this->info("耗时：{$latency}ms");
            $this->info("HTTP 状态码：{$httpCode}");

            if ($error) {
                $this->error("cURL 错误 ({$errno}): {$error}");

                return self::FAILURE;
            }

            if ($response) {
                // 尝试解析 JSON 响应
                $jsonData = json_decode($response, true);
                if ($jsonData) {
                    $this->displayJsonResponse($jsonData);
                } else {
                    // 非 JSON 响应，直接显示
                    if (mb_strlen($response) > 1000) {
                        $this->line(mb_substr($response, 0, 1000).'...');
                        $this->info('(内容已截断，总长度：'.mb_strlen($response).' 字符)');
                    } else {
                        $this->line($response);
                    }
                }
            }

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("请求异常：{$e->getMessage()}");
            $this->error('文件：'.$e->getFile().':'.$e->getLine());

            return self::FAILURE;
        }
    }

    protected function getChannel(RequestLog $log, ?int $channelId): ?Channel
    {
        // 如果指定了渠道 ID，直接使用
        if ($channelId) {
            $channel = Channel::find($channelId);
            if (! $channel) {
                $this->error("指定的渠道不存在：{$channelId}");

                return null;
            }

            return $channel;
        }

        // 使用请求日志中的渠道
        if ($log->channel_id) {
            $channel = Channel::find($log->channel_id);
            if ($channel) {
                return $channel;
            }
        }

        $this->error('无法确定渠道');

        return null;
    }

    protected function resolveModel(string $model, Channel $channel): string
    {
        $channelModel = ChannelModel::where('channel_id', $channel->id)
            ->where('model_name', $model)
            ->where('is_enabled', true)
            ->first();

        if ($channelModel && $channelModel->mapped_model) {
            return $channelModel->mapped_model;
        }

        return $model;
    }

    protected function buildUrl(Channel $channel, RequestLog $log): string
    {
        // 优先从 channel_request_logs 获取实际发送的完整 URL
        $channelRequestLog = ChannelRequestLog::where('request_log_id', $log->id)->first();
        if ($channelRequestLog && ! empty($channelRequestLog->full_url)) {
            return $channelRequestLog->full_url;
        }

        // 回退：根据渠道配置构建 URL
        $baseUrl = $channel->base_url;
        $provider = $channel->provider;

        // 根据供应商确定端点
        $endpoint = $this->getEndpoint($provider, $baseUrl);

        // 构建完整 URL
        $url = rtrim($baseUrl, '/').$endpoint;

        // 添加 query string
        if (! empty($log->query_string)) {
            $url .= '?'.$log->query_string;
        }

        return $url;
    }

    protected function getEndpoint(string $provider, string $baseUrl): string
    {
        // 检查 baseUrl 是否已包含完整路径
        // 如 https://xxx/v1 或 https://xxx/apps/anthropic/v1
        if (str_ends_with($baseUrl, '/v1')) {
            return '/chat/completions';
        }

        // 如果 baseUrl 以 /messages 结尾，不需要额外端点
        if (str_ends_with($baseUrl, '/messages')) {
            return '';
        }

        // 根据供应商确定端点
        return match ($provider) {
            'anthropic' => '/v1/messages',
            default => '/v1/chat/completions',
        };
    }

    protected function buildHeaders(Channel $channel, RequestLog $log): array
    {
        $provider = $channel->provider;
        $apiKey = $channel->api_key;

        // 从原始请求中获取请求头
        $originalHeaders = $log->headers ?? [];
        $headers = [];

        // 需要排除的请求头（由渠道配置决定）
        $excludeHeaders = [
            'host',
            'content-length',
            'content-type',
            'authorization',
            'x-api-key',
            'api-key',
            'connection',
            'accept-encoding',
            'accept',
        ];

        // 转换原始请求头格式（从数组格式转为键值对）
        foreach ($originalHeaders as $key => $value) {
            $keyLower = strtolower($key);
            if (in_array($keyLower, $excludeHeaders, true)) {
                continue;
            }
            // 值可能是数组，取第一个元素
            $headers[$key] = is_array($value) ? ($value[0] ?? '') : $value;
        }

        // 确保 Content-Type 存在
        $headers['Content-Type'] = 'application/json';

        // 根据供应商设置认证头
        switch ($provider) {
            case 'anthropic':
                $headers['x-api-key'] = $apiKey;
                // 保留原始 anthropic-version 或使用默认值
                if (! isset($headers['anthropic-version'])) {
                    $headers['anthropic-version'] = '2023-06-01';
                }
                break;
            case 'azure':
                $headers['api-key'] = $apiKey;
                break;
            default:
                // OpenAI 兼容格式
                $headers['Authorization'] = "Bearer {$apiKey}";
                break;
        }

        return $headers;
    }

    /**
     * 格式化请求头为 curl 格式
     */
    protected function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $key => $value) {
            $formatted[] = "{$key}: {$value}";
        }

        return $formatted;
    }

    protected function displayJsonResponse(array $data): void
    {
        // 显示内容
        if (isset($data['choices'])) {
            $content = $data['choices'][0]['message']['content'] ?? '';
            if ($content) {
                $this->info('---------- 响应内容 ----------');
                $this->displayContent($content);
            }
        } elseif (isset($data['content'])) {
            $content = is_array($data['content'])
                ? ($data['content'][0]['text'] ?? '')
                : $data['content'];
            if ($content) {
                $this->info('---------- 响应内容 ----------');
                $this->displayContent($content);
            }
        }

        // 显示 token 使用情况
        if (isset($data['usage'])) {
            $this->newLine();
            $this->info('Token 使用:');
            $this->info('  输入：'.($data['usage']['prompt_tokens'] ?? 'N/A'));
            $this->info('  输出：'.($data['usage']['completion_tokens'] ?? 'N/A'));
            $this->info('  总计：'.($data['usage']['total_tokens'] ?? 'N/A'));
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

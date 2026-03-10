<?php

namespace App\Console\Commands;

use App\Models\RequestLog;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * php artisan request:replay 981
 */
class ReplayRequest extends Command
{
    protected $signature = 'request:replay
                            {request_id : 请求 ID}
                            {--timeout=120 : 请求超时时间 (秒)}
                            {--dry-run : 仅显示请求信息，不实际发送}';

    protected $description = '复现请求 - 根据请求 ID 重新发送真实请求';

    public function handle(): int
    {
        $input = $this->argument('request_id');

        // 查找请求日志 - 支持数字 ID 或 request_id
        $requestLog = is_numeric($input)
            ? RequestLog::find((int) $input)
            : RequestLog::where('request_id', $input)->first();

        if (! $requestLog) {
            $this->error("请求不存在：{$input}");

            return self::FAILURE;
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
        // 优先使用 to_request_body (转换后的请求体，走系统转发流程)
        if (! empty($log->to_request_body)) {
            if (is_string($log->to_request_body)) {
                return json_decode($log->to_request_body, true) ?? [];
            }

            return $log->to_request_body;
        }

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

        // 设置模型
        $body['model'] = $model;

        $this->newLine();
        $this->info('========== 发送真实请求 ==========');
        $this->info("模型：{$model}");

        try {
            $startTime = microtime(true);

            $this->sendSyncRequest($log, $body);

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
            ->withBody(json_encode($body), 'application/json')
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
                $this->info("  输入：{$result['usage']['prompt_tokens']}");
                $this->info("  输出：{$result['usage']['completion_tokens']}");
                $this->info("  总计：{$result['usage']['total_tokens']}");
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

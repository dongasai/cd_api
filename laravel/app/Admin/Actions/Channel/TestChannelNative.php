<?php

namespace App\Admin\Actions\Channel;

use App\Models\Channel;
use App\Models\ChannelRequestLog;
use App\Services\ChannelInheritance\ChannelInheritanceResolver;
use Dcat\Admin\Grid\RowAction;
use Illuminate\Support\Facades\Http;

/**
 * 原生SDK测试渠道
 *
 * 使用 openai-php/laravel 或 anthropic-ai/sdk 直接请求上游
 */
class TestChannelNative extends RowAction
{
    /**
     * 测试消息
     */
    protected string $testMessage = '你好，请介绍一下你自己';

    /**
     * 当前测试的请求上下文（用于失败时也能完整记录日志）
     *
     * 在 testVia* 方法构建请求时填充，确保 catch 分支可获取请求信息
     */
    protected array $requestContext = [];

    public function title()
    {
        return '<i class="fa fa-flask"></i> '.admin_trans_action('test_channel_native');
    }

    public function handle()
    {
        $id = $this->getKey();
        $channel = Channel::with('channelModels')->find($id);

        if (! $channel) {
            return $this->response()->error(admin_trans_action('channel_not_found'));
        }

        // 校验 API Key（通过继承解析获取有效值）
        $effectiveApiKey = $channel->getEffectiveApiKey();
        if (empty($effectiveApiKey)) {
            return $this->response()->error(admin_trans_action('channel_no_api_key'));
        }

        // 获取测试模型（通过继承解析，子渠道无默认模型时继承父渠道）
        $defaultModel = app(ChannelInheritanceResolver::class)->resolveDefaultModel($channel);
        if (! $defaultModel) {
            return $this->response()->error(admin_trans_action('channel_no_default_model'));
        }

        // 模型映射信息：原始名 -> 上游实际标识
        $modelName = $defaultModel['model_name'];      // 原始模型名（如 qwen3.6-lite）
        $model = $defaultModel['mapped_model'];        // 上游模型标识（如 xopqwen36v35b）

        $startTime = microtime(true);

        try {
            $result = match ($channel->getEffectiveProvider() ?? $channel->provider) {
                'anthropic' => $this->testViaAnthropic($channel, $model, $modelName),
                default => $this->testViaOpenAI($channel, $model, $modelName),
            };

            $latency = (int) ((microtime(true) - $startTime) * 1000);

            // 记录渠道请求日志
            $this->logChannelRequest($channel, $result, $latency, true);

            // 更新渠道统计
            $this->updateChannelStats($channel, true, $latency);

            $message = $this->buildSuccessMessage($result, $latency);

            return $this->response()
                ->success($message)
                ->refresh();
        } catch (\Throwable $e) {
            $latency = (int) ((microtime(true) - $startTime) * 1000);

            // 记录失败的渠道请求日志
            $this->logChannelRequest($channel, [
                'success' => false,
                'error' => $e->getMessage(),
            ], $latency, false);

            $this->updateChannelStats($channel, false, $latency);

            return $this->response()->error(admin_trans_action('test_channel_native_failed').': '.$e->getMessage());
        }
    }

    public function confirm()
    {
        return [
            admin_trans_action('test_channel_native_confirm'),
            admin_trans_action('test_channel_native_confirm_desc'),
        ];
    }

    /**
     * 通过 OpenAI 兼容接口测试
     *
     * @param  string  $model  上游实际模型标识（mapped_model）
     * @param  string  $modelName  原始模型名（用于日志追踪）
     * @return array{content: string, request_headers: array, request_body: array, response_status: int, response_body: array, usage: array|null}
     */
    protected function testViaOpenAI(Channel $channel, string $model, string $modelName): array
    {
        $effectiveApiKey = $channel->getEffectiveApiKey();
        $baseUrl = rtrim($channel->getEffectiveBaseUrl() ?: 'https://api.openai.com', '/');
        $endpoint = $baseUrl.'/v1/chat/completions';

        $requestBody = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $this->testMessage],
            ],
            'max_tokens' => 10240,
        ];

        $requestHeaders = [
            'Authorization' => 'Bearer '.substr($effectiveApiKey, 0, 8).'...',
            'Content-Type' => 'application/json',
        ];

        // 记录请求上下文（用于日志记录，确保失败时也能完整记录）
        $this->requestContext = [
            'path' => '/v1/chat/completions',
            'full_url' => $endpoint,
            'request_headers' => $requestHeaders,
            'request_body' => $requestBody,
            'model_name' => $modelName,      // 原始模型名
            'mapped_model' => $model,        // 上游实际模型标识
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$effectiveApiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post($endpoint, $requestBody);

        if (! $response->successful()) {
            throw new \RuntimeException('HTTP '.$response->status().': '.$response->body());
        }

        $data = $response->json();

        // 智能解析响应：兼容 OpenAI 和 Anthropic 两种格式
        $extracted = $this->extractContent($data);
        $usage = $data['usage'] ?? null;

        return [
            'reasoning' => $extracted['reasoning'],
            'content' => $extracted['content'],
            'request_headers' => $requestHeaders,
            'request_body' => $requestBody,
            'response_status' => $response->status(),
            'response_body' => $data,
            'usage' => $usage,
        ];
    }

    /**
     * 通过 Anthropic 接口测试
     *
     * @param  string  $model  上游实际模型标识（mapped_model）
     * @param  string  $modelName  原始模型名（用于日志追踪）
     * @return array{content: string, request_headers: array, request_body: array, response_status: int, response_body: array, usage: array|null}
     */
    protected function testViaAnthropic(Channel $channel, string $model, string $modelName): array
    {
        $effectiveApiKey = $channel->getEffectiveApiKey();
        $baseUrl = rtrim($channel->getEffectiveBaseUrl() ?: 'https://api.anthropic.com', '/');
        $endpoint = $baseUrl.'/v1/messages';

        $requestBody = [
            'model' => $model,
            'max_tokens' => 10240,
            'messages' => [
                ['role' => 'user', 'content' => $this->testMessage],
            ],
        ];

        $requestHeaders = [
            'x-api-key' => substr($effectiveApiKey, 0, 8).'...',
            'Content-Type' => 'application/json',
            'anthropic-version' => '2023-06-01',
        ];

        // 记录请求上下文（用于日志记录，确保失败时也能完整记录）
        $this->requestContext = [
            'path' => '/v1/messages',
            'full_url' => $endpoint,
            'request_headers' => $requestHeaders,
            'request_body' => $requestBody,
            'model_name' => $modelName,      // 原始模型名
            'mapped_model' => $model,        // 上游实际模型标识
        ];

        $response = Http::withHeaders([
            'x-api-key' => $effectiveApiKey,
            'Content-Type' => 'application/json',
            'anthropic-version' => '2023-06-01',
        ])->timeout(30)->post($endpoint, $requestBody);

        if (! $response->successful()) {
            throw new \RuntimeException('HTTP '.$response->status().': '.$response->body());
        }

        $data = $response->json();

        $extracted = $this->extractContent($data);

        if ($extracted['content'] === '' && $extracted['reasoning'] === null) {
            throw new \RuntimeException('响应中未找到有效文本内容: '.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        return [
            'reasoning' => $extracted['reasoning'],
            'content' => $extracted['content'],
            'request_headers' => $requestHeaders,
            'request_body' => $requestBody,
            'response_status' => $response->status(),
            'response_body' => $data,
            'usage' => $data['usage'] ?? null,
        ];
    }

    /**
     * 智能提取响应内容
     *
     * 兼容 OpenAI 格式 (choices) 和 Anthropic 格式 (content blocks)
     *
     * @return array{reasoning: string|null, content: string}
     */
    protected function extractContent(array $data): array
    {
        // OpenAI 格式: choices[0].message.content
        if (! empty($data['choices'])) {
            $message = $data['choices'][0]['message'] ?? [];

            return [
                'reasoning' => $message['reasoning_content'] ?? null,
                'content' => $message['content'] ?? '',
            ];
        }

        // Anthropic 格式: content[{type: "text", text: "..."}]
        // Anthropic 思考内容在 thinking blocks 中
        $reasoning = null;
        $textContent = '';

        if (! empty($data['content']) && is_array($data['content'])) {
            $parts = [];
            foreach ($data['content'] as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $parts[] = $block['text'] ?? '';
                } elseif (($block['type'] ?? '') === 'thinking') {
                    $reasoning = $block['thinking'] ?? null;
                }
            }
            $textContent = implode("\n", $parts);
        }

        return [
            'reasoning' => $reasoning,
            'content' => $textContent,
        ];
    }

    /**
     * 构建成功提示信息
     */
    protected function buildSuccessMessage(array $result, int $latency): string
    {
        $parts = [admin_trans_action('test_channel_native_success')." ({$latency}ms)"];

        // Token 统计
        $usage = $result['usage'] ?? null;
        if ($usage) {
            $tokenParts = [];

            // 输入 tokens（兼容 OpenAI/Anthropic 字段名）
            $inputTokens = $usage['prompt_tokens'] ?? $usage['input_tokens'] ?? null;
            $cachedTokens = $usage['prompt_tokens_details']['cached_tokens'] ?? $usage['cache_read_input_tokens'] ?? null;
            if ($inputTokens !== null) {
                $inputLabel = (int) $inputTokens;
                if ($cachedTokens !== null && $cachedTokens > 0) {
                    $inputLabel .= "/缓存{$cachedTokens}";
                }
                $tokenParts[] = "输入{$inputLabel}";
            }

            // 输出 tokens（兼容字段名）
            $outputTokens = $usage['completion_tokens'] ?? $usage['output_tokens'] ?? null;
            if ($outputTokens !== null) {
                $tokenParts[] = "输出{$outputTokens}";
            }

            if ($tokenParts) {
                $parts[] = '('.implode(', ', $tokenParts).')';
            }
        }

        // 思考内容
        $reasoning = $result['reasoning'] ?? null;
        if (! empty($reasoning)) {
            $parts[] = "\n[思考]\n".mb_substr($reasoning, 0, 300);
        }

        // 正文内容
        $content = $result['content'] ?? '';
        if (! empty($content)) {
            $parts[] = "\n[内容]\n".mb_substr($content, 0, 500);
        }

        return implode(' ', $parts);
    }

    /**
     * 记录渠道请求日志
     *
     * 字段映射遵循 ProxyServer 的规范：
     * - path: 端点路径（如 /v1/chat/completions），而非模型名
     * - full_url: base_url + path 拼接的完整 URL
     * - request_model 存入 metadata，避免污染 path 字段
     */
    protected function logChannelRequest(Channel $channel, array $result, int $latency, bool $success): void
    {
        // 合并请求上下文：成功时 result 已含请求信息，失败时从 requestContext 补全
        $ctx = array_merge($this->requestContext, $result);

        $requestBody = $ctx['request_body'] ?? [];
        $responseBody = $result['response_body'] ?? ['error' => $result['error'] ?? ''];

        ChannelRequestLog::create([
            'request_id' => 'test-'.uniqid(date('YmdHis')),
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'provider' => $channel->provider,
            'method' => 'POST',
            'path' => $ctx['path'] ?? '',
            'base_url' => $channel->getEffectiveBaseUrl() ?? $channel->base_url,
            'full_url' => $ctx['full_url'] ?? $channel->base_url,
            'request_headers' => $ctx['request_headers'] ?? [],
            'request_body' => $requestBody,
            'request_size' => strlen(json_encode($requestBody)),
            'response_status' => $result['response_status'] ?? 0,
            'response_body' => $responseBody,
            'response_size' => strlen(json_encode($responseBody)),
            'latency_ms' => $latency,
            'is_success' => $success,
            'error_message' => $success ? null : ($result['error'] ?? ''),
            'usage' => $result['usage'] ?? null,
            'metadata' => [
                'test_type' => 'native',
                'test_message' => $this->testMessage,
                'model_name' => $ctx['model_name'] ?? null,      // 原始模型名（如 qwen3.6-lite）
                'mapped_model' => $ctx['mapped_model'] ?? null,  // 上游实际模型标识（如 xopqwen36v35b）
            ],
            'sent_at' => now(),
        ]);
    }

    /**
     * 更新渠道统计
     */
    protected function updateChannelStats(Channel $channel, bool $success, int $latency): void
    {
        $data = ['last_check_at' => now()];

        if ($success) {
            $data['last_success_at'] = now();
            $data['success_count'] = ($channel->success_count ?? 0) + 1;
            $data['avg_latency_ms'] = $channel->avg_latency_ms
                ? round(($channel->avg_latency_ms + $latency) / 2, 2)
                : $latency;
        } else {
            $data['last_failure_at'] = now();
            $data['failure_count'] = ($channel->failure_count ?? 0) + 1;
        }

        $channel->update($data);
    }
}

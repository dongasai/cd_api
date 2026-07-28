<?php

namespace App\Admin\Actions\Channel;

use App\Models\Channel;
use App\Models\ChannelRequestLog;
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

        // 校验 API Key
        if (empty($channel->api_key)) {
            return $this->response()->error(admin_trans_action('channel_no_api_key'));
        }

        // 获取测试模型（必须配置默认模型）
        $model = $channel->getDefaultModelName();
        if (! $model) {
            return $this->response()->error(admin_trans_action('channel_no_default_model'));
        }

        $startTime = microtime(true);

        try {
            $result = match ($channel->provider) {
                'anthropic' => $this->testViaAnthropic($channel, $model),
                default => $this->testViaOpenAI($channel, $model),
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
     * @return array{content: string, request_headers: array, request_body: array, response_status: int, response_body: array, usage: array|null}
     */
    protected function testViaOpenAI(Channel $channel, string $model): array
    {
        $baseUrl = $channel->base_url ?: 'https://api.openai.com/v1';
        $endpoint = rtrim($baseUrl, '/').'/chat/completions';

        $requestBody = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $this->testMessage],
            ],
            'max_tokens' => 10240,
        ];

        $requestHeaders = [
            'Authorization' => 'Bearer '.substr($channel->api_key, 0, 8).'...',
            'Content-Type' => 'application/json',
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$channel->api_key,
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
     * @return array{content: string, request_headers: array, request_body: array, response_status: int, response_body: array, usage: array|null}
     */
    protected function testViaAnthropic(Channel $channel, string $model): array
    {
        $baseUrl = $channel->base_url ?: 'https://api.anthropic.com';
        $endpoint = rtrim($baseUrl, '/').'/v1/messages';

        $requestBody = [
            'model' => $model,
            'max_tokens' => 10240,
            'messages' => [
                ['role' => 'user', 'content' => $this->testMessage],
            ],
        ];

        $requestHeaders = [
            'x-api-key' => substr($channel->api_key, 0, 8).'...',
            'Content-Type' => 'application/json',
            'anthropic-version' => '2023-06-01',
        ];

        $response = Http::withHeaders([
            'x-api-key' => $channel->api_key,
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
                $parts[] = '(' . implode(', ', $tokenParts) . ')';
            }
        }

        // 思考内容
        $reasoning = $result['reasoning'] ?? null;
        if (! empty($reasoning)) {
            $parts[] = "\n[思考]\n" . mb_substr($reasoning, 0, 300);
        }

        // 正文内容
        $content = $result['content'] ?? '';
        if (! empty($content)) {
            $parts[] = "\n[内容]\n" . mb_substr($content, 0, 500);
        }

        return implode(' ', $parts);
    }

    /**
     * 记录渠道请求日志
     */
    protected function logChannelRequest(Channel $channel, array $result, int $latency, bool $success): void
    {
        ChannelRequestLog::create([
            'request_id' => 'test-'.uniqid(date('YmdHis')),
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'provider' => $channel->provider,
            'method' => 'POST',
            'path' => $success ? ($result['request_body']['model'] ?? '') : '',
            'base_url' => $channel->base_url,
            'full_url' => $channel->base_url,
            'request_headers' => $result['request_headers'] ?? [],
            'request_body' => $result['request_body'] ?? [],
            'request_size' => strlen(json_encode($result['request_body'] ?? [])),
            'response_status' => $result['response_status'] ?? 0,
            'response_body' => $result['response_body'] ?? ['error' => $result['error'] ?? ''],
            'response_size' => strlen(json_encode($result['response_body'] ?? [])),
            'latency_ms' => $latency,
            'is_success' => $success,
            'error_message' => $success ? null : ($result['error'] ?? ''),
            'usage' => $result['usage'] ?? null,
            'metadata' => ['test_type' => 'native', 'test_message' => $this->testMessage],
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

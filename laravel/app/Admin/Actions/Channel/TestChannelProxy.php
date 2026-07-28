<?php

namespace App\Admin\Actions\Channel;

use App\Models\Channel;
use App\Models\ChannelRequestLog;
use App\Services\Protocol\Driver\OpenAI\ChatCompletionRequest;
use App\Services\Provider\ProviderManager;
use App\Services\Shared\DTO\Message;
use App\Services\Shared\DTO\Request;
use App\Services\Shared\Enums\MessageRole;
use Dcat\Admin\Grid\RowAction;

/**
 * 中转测试渠道
 *
 * 通过系统自身的 Provider 层测试渠道连通性
 */
class TestChannelProxy extends RowAction
{
    /**
     * 测试消息
     */
    protected string $testMessage = '你好，请介绍一下你自己';

    public function title()
    {
        return '<i class="fa fa-exchange"></i> '.admin_trans_action('test_channel_proxy');
    }

    public function handle()
    {
        $id = $this->getKey();
        $channel = Channel::with('channelModels')->find($id);

        if (! $channel) {
            return $this->response()->error(admin_trans_action('channel_not_found'));
        }

        // 获取测试模型（必须配置默认模型）
        $model = $channel->getDefaultModelName();
        if (! $model) {
            return $this->response()->error(admin_trans_action('channel_no_default_model'));
        }

        $startTime = microtime(true);

        try {
            // 构建 Shared DTO
            $sharedRequest = new Request(
                model: $model,
                messages: [
                    new Message(
                        role: MessageRole::User,
                        content: $this->testMessage,
                    ),
                ],
                maxTokens: 10240,
            );

            // 转换为 ProtocolRequest
            $protocolRequest = ChatCompletionRequest::fromSharedDTO($sharedRequest);

            // 通过 ProviderManager 获取渠道驱动并发送请求
            $provider = app(ProviderManager::class)->getForChannel($channel);
            $response = $provider->send($protocolRequest);

            $latency = (int) ((microtime(true) - $startTime) * 1000);

            // 提取思考内容和正文
            $responseData = $response->toArray();
            $reasoning = $responseData['choices'][0]['message']['reasoning_content'] ?? null;
            $content = $response->toSharedDTO()->getContent();
            $usage = $response->getUsage();

            // 记录渠道请求日志
            $this->logChannelRequest($channel, $protocolRequest, $response, $latency, true);

            // 更新渠道统计
            $this->updateChannelStats($channel, true, $latency);

            $message = $this->buildSuccessMessage($content, $reasoning, $usage, $latency);

            return $this->response()
                ->success($message)
                ->refresh();
        } catch (\Throwable $e) {
            $latency = (int) ((microtime(true) - $startTime) * 1000);
            $this->logChannelRequest($channel, null, null, $latency, false, $e->getMessage());
            $this->updateChannelStats($channel, false, $latency);

            return $this->response()->error(admin_trans_action('test_channel_proxy_failed').': '.$e->getMessage());
        }
    }

    public function confirm()
    {
        return [
            admin_trans_action('test_channel_proxy_confirm'),
            admin_trans_action('test_channel_proxy_confirm_desc'),
        ];
    }

    /**
     * 构建成功提示信息
     */
    protected function buildSuccessMessage(?string $content, ?string $reasoning, ?object $usage, int $latency): string
    {
        $parts = [admin_trans_action('test_channel_proxy_success')." ({$latency}ms)"];

        // Token 统计
        if ($usage) {
            $tokenParts = [];

            $inputTokens = $usage->prompt_tokens ?? null;
            $cachedTokens = $usage->prompt_tokens_details['cached_tokens'] ?? null;
            if ($inputTokens !== null) {
                $inputLabel = (int) $inputTokens;
                if ($cachedTokens !== null && $cachedTokens > 0) {
                    $inputLabel .= "/缓存{$cachedTokens}";
                }
                $tokenParts[] = "输入{$inputLabel}";
            }

            $outputTokens = $usage->completion_tokens ?? null;
            $reasoningTokens = $usage->completion_tokens_details['reasoning_tokens'] ?? null;
            if ($outputTokens !== null) {
                $outputLabel = (int) $outputTokens;
                if ($reasoningTokens !== null && $reasoningTokens > 0) {
                    $outputLabel .= "/思考{$reasoningTokens}";
                }
                $tokenParts[] = "输出{$outputLabel}";
            }

            if ($tokenParts) {
                $parts[] = '(' . implode(', ', $tokenParts) . ')';
            }
        }

        // 思考内容
        if (! empty($reasoning)) {
            $parts[] = "\n[思考]\n" . mb_substr($reasoning, 0, 300);
        }

        // 正文内容
        if (! empty($content)) {
            $parts[] = "\n[内容]\n" . mb_substr($content, 0, 500);
        }

        return implode(' ', $parts);
    }

    /**
     * 记录渠道请求日志
     */
    protected function logChannelRequest(Channel $channel, $protocolRequest, $response, int $latency, bool $success, ?string $error = null): void
    {
        ChannelRequestLog::create([
            'request_id' => 'proxy-'.uniqid(date('YmdHis')),
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'provider' => $channel->provider,
            'method' => 'POST',
            'path' => $protocolRequest ? $protocolRequest->getModel() : '',
            'base_url' => $channel->base_url,
            'full_url' => $channel->base_url,
            'request_headers' => [],
            'request_body' => $protocolRequest ? $protocolRequest->toArray() : [],
            'request_size' => $protocolRequest ? strlen(json_encode($protocolRequest->toArray())) : 0,
            'response_status' => $success ? 200 : 0,
            'response_body' => $response ? $response->toArray() : ['error' => $error],
            'response_size' => $response ? strlen(json_encode($response->toArray())) : 0,
            'latency_ms' => $latency,
            'is_success' => $success,
            'error_message' => $error,
            'usage' => $response ? ($response->getUsage() ? (array) $response->getUsage() : null) : null,
            'metadata' => ['test_type' => 'proxy', 'test_message' => $this->testMessage],
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

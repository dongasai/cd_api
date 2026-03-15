<?php

namespace App\Services\Provider\Driver;

use App\Services\Shared\DTO\Request;
use App\Services\Shared\DTO\Response;
use App\Services\Shared\DTO\StreamChunk;
use App\Services\Shared\DTO\ToolCall;
use App\Services\Shared\DTO\Usage;
use App\Services\Shared\Enums\FinishReason;

/**
 * OpenAI 供应商
 *
 * OpenAI 官方 API 供应商实现
 */
class OpenAIProvider extends AbstractProvider
{
    /**
     * 支持的模型列表
     */
    protected array $supportedModels = [
        'gpt-4o',
        'gpt-4o-mini',
        'gpt-4-turbo',
        'gpt-4-turbo-preview',
        'gpt-4',
        'gpt-4-32k',
        'gpt-3.5-turbo',
        'gpt-3.5-turbo-16k',
        'o1',
        'o1-mini',
        'o1-preview',
    ];

    /**
     * 获取默认 API 基础 URL
     */
    public function getDefaultBaseUrl(): string
    {
        return 'https://api.openai.com/v1';
    }

    /**
     * 获取 API 端点
     */
    public function getEndpoint(Request $request): string
    {
        return '/chat/completions';
    }

    /**
     * 获取请求头
     */
    public function getHeaders(): array
    {
        $headers = [
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ];

        return $this->mergeForwardedHeaders($headers);
    }

    /**
     * 构建请求体
     */
    public function buildRequestBody(Request $request): array
    {
        return $this->toOpenAIFormat($request);
    }

    /**
     * 解析响应
     */
    public function parseResponse(array $response): Response
    {
        return $this->parseOpenAIResponse($response);
    }

    /**
     * 解析流式响应块
     */
    public function parseStreamChunk(string $rawChunk): ?StreamChunk
    {
        return $this->parseOpenAIStreamChunk($rawChunk);
    }

    /**
     * 获取支持的模型列表
     */
    public function getModels(): array
    {
        return $this->supportedModels;
    }

    /**
     * 获取供应商名称
     */
    public function getProviderName(): string
    {
        return 'openai';
    }

    /**
     * 将 Request 转换为 OpenAI 格式
     */
    protected function toOpenAIFormat(Request $request): array
    {
        $result = [
            'model' => $request->model,
            'messages' => array_map(fn ($m) => $m->toArray(), $request->messages),
        ];

        if ($request->maxTokens !== null) {
            $result['max_tokens'] = $request->maxTokens;
        }
        if ($request->temperature !== null) {
            $result['temperature'] = $request->temperature;
        }
        if ($request->topP !== null) {
            $result['top_p'] = $request->topP;
        }
        if ($request->topK !== null) {
            $result['top_k'] = $request->topK;
        }
        if ($request->stream) {
            $result['stream'] = true;
        }
        if ($request->stopSequences !== null) {
            $result['stop'] = $request->stopSequences;
        }
        if ($request->tools !== null) {
            $result['tools'] = $request->tools;
        }
        if ($request->toolChoice !== null) {
            $result['tool_choice'] = $request->toolChoice;
        }
        if ($request->user !== null) {
            $result['user'] = $request->user;
        }
        if ($request->metadata !== null) {
            $result['metadata'] = $request->metadata;
        }

        // 处理 system 提示：OpenAI 将 system 作为消息的第一条
        if ($request->system !== null) {
            $systemContent = $request->system;
            if (is_array($systemContent)) {
                $text = '';
                foreach ($systemContent as $block) {
                    if (is_string($block)) {
                        $text .= $block;
                    } elseif (isset($block['text'])) {
                        $text .= $block['text'];
                    }
                }
                $systemContent = $text;
            }
            if ($systemContent !== '') {
                array_unshift($result['messages'], [
                    'role' => 'system',
                    'content' => $systemContent,
                ]);
            }
        }

        return array_merge($result, $request->additionalParams);
    }

    /**
     * 解析 OpenAI 响应为 Response
     */
    protected function parseOpenAIResponse(array $response): Response
    {
        $choices = [];
        foreach ($response['choices'] ?? [] as $choice) {
            $choices[] = [
                'index' => $choice['index'] ?? 0,
                'message' => $choice['message'] ?? [],
                'finish_reason' => $choice['finish_reason'] ?? null,
            ];
        }

        $usage = null;
        if (isset($response['usage'])) {
            $usage = Usage::fromOpenAI($response['usage']);
        }

        $finishReason = null;
        if (isset($response['choices'][0]['finish_reason'])) {
            $finishReason = FinishReason::fromOpenAI($response['choices'][0]['finish_reason']);
        }

        // 解析工具调用
        $toolCalls = null;
        if (isset($response['choices'][0]['message']['tool_calls'])) {
            $toolCalls = array_map(
                fn ($tc) => ToolCall::fromOpenAI($tc),
                $response['choices'][0]['message']['tool_calls']
            );
        }

        return new Response(
            id: $response['id'] ?? '',
            model: $response['model'] ?? '',
            choices: $choices,
            usage: $usage,
            finishReason: $finishReason,
            systemFingerprint: $response['system_fingerprint'] ?? null,
            created: $response['created'] ?? 0,
            toolCalls: $toolCalls,
        );
    }

    /**
     * 解析 OpenAI 流式响应块
     */
    protected function parseOpenAIStreamChunk(string $rawChunk): ?StreamChunk
    {
        // 处理 "data: " 前缀
        if (str_starts_with($rawChunk, 'data: ')) {
            $rawChunk = substr($rawChunk, 6);
        }

        // 跳过空行和 "[DONE]"
        if (trim($rawChunk) === '' || trim($rawChunk) === '[DONE]') {
            return null;
        }

        $data = json_decode($rawChunk, true);
        if ($data === null) {
            return null;
        }

        $id = $data['id'] ?? '';
        $model = $data['model'] ?? '';
        $choices = $data['choices'] ?? [];
        $choice = $choices[0] ?? [];

        $delta = $choice['delta'] ?? [];
        $finishReason = isset($choice['finish_reason']) && $choice['finish_reason'] !== null
            ? FinishReason::fromOpenAI($choice['finish_reason'])
            : null;

        $contentDelta = $delta['content'] ?? null;
        $toolCalls = $delta['tool_calls'] ?? null;

        $usage = null;
        if (isset($data['usage'])) {
            $usage = Usage::fromOpenAI($data['usage']);
        }

        return new StreamChunk(
            id: $id,
            model: $model,
            contentDelta: $contentDelta,
            finishReason: $finishReason,
            index: $choice['index'] ?? 0,
            usage: $usage,
            event: '',
            data: $data,
            delta: $contentDelta ?? '',
            toolCalls: $toolCalls,
        );
    }
}

<?php

namespace App\Services\Provider\Driver;

use App\Services\Protocol\Contracts\ProtocolRequest;
use App\Services\Protocol\Driver\OpenAI\ChatCompletionRequest;
use App\Services\Protocol\Driver\OpenAI\Message;

/**
 * DeepSeek 供应商驱动
 *
 * 专门处理 DeepSeek API 的特殊逻辑：
 * - 根据是否进行工具调用决定是否保留 reasoning_content
 * - 有工具调用：必须保留 reasoning_content（DeepSeek 要求完整回传）
 * - 无工具调用：删除 reasoning_content（避免 API 返回 400 错误）
 *
 * @see https://api-docs.deepseek.com/zh-cn/guides/reasoning_model
 */
class DeepSeekProvider extends OpenAICompatibleProvider
{
    /**
     * 构建请求体
     *
     * DeepSeek 特殊处理：
     * - 根据是否进行工具调用决定是否保留 reasoning_content
     * - 有工具调用的轮次：必须完整回传 reasoning_content
     * - 无工具调用的轮次：删除 reasoning_content（避免 400 错误）
     */
    public function buildRequestBody(ProtocolRequest $request): array
    {
        // 如果是 OpenAI 协议请求，直接转数组
        if ($request instanceof ChatCompletionRequest) {
            $body = $request->toArray();

            // 检查渠道配置：是否强制附加 stream_options.include_usage
            $forceStreamOptionIncludeUsage = $this->config['force_stream_option_include_usage'] ?? false;

            // 流式请求时，根据配置决定是否附加 stream_options
            if ($forceStreamOptionIncludeUsage && ($body['stream'] ?? false) === true) {
                if (! isset($body['stream_options'])) {
                    $body['stream_options'] = ['include_usage' => true];
                } elseif (! isset($body['stream_options']['include_usage'])) {
                    $body['stream_options']['include_usage'] = true;
                }
            }

            // DeepSeek 特殊处理：根据 tool_calls 决定是否保留 reasoning_content
            if (isset($body['messages']) && is_array($body['messages'])) {
                $body['messages'] = $this->processReasoningContent($body['messages']);
            }

            return $body;
        }

        // 其他协议需要转换
        throw new \InvalidArgumentException('DeepSeekProvider requires ChatCompletionRequest');
    }

    /**
     * 处理消息中的 reasoning_content 字段
     *
     * DeepSeek API 要求：
     * - 有工具调用的轮次：必须完整回传 reasoning_content（让模型继续思考）
     * - 无工具调用的轮次：删除 reasoning_content（避免 400 错误）
     *
     * @param  array  $messages  消息数组
     * @return array 处理后的消息数组
     */
    protected function processReasoningContent(array $messages): array
    {
        return array_map(function ($message) {
            // 如果是数组格式的消息
            if (is_array($message)) {
                // 检查是否有 tool_calls
                $hasToolCalls = isset($message['tool_calls']) && ! empty($message['tool_calls']);

                // 如果没有工具调用，删除 reasoning_content
                if (! $hasToolCalls && isset($message['reasoning_content'])) {
                    unset($message['reasoning_content']);
                }

                // 处理 content 字段（如果是 ContentPart 数组）
                if (isset($message['content']) && is_array($message['content'])) {
                    $message['content'] = array_map(function ($part) {
                        // 如果是 ContentPart 对象或数组
                        if (is_array($part)) {
                            // 保留 reasoning_content（如果有）
                            // 这里不需要特别处理，因为 content 部分通常不包含 reasoning_content
                        }

                        return $part;
                    }, $message['content']);
                }
            }
            // 如果是 Message 对象
            elseif ($message instanceof Message) {
                // 重新构建数组
                $messageArray = $message->toArray();

                // 检查是否有 tool_calls
                $hasToolCalls = isset($messageArray['tool_calls']) && ! empty($messageArray['tool_calls']);

                // 如果没有工具调用，删除 reasoning_content
                if (! $hasToolCalls && isset($messageArray['reasoning_content'])) {
                    unset($messageArray['reasoning_content']);
                }

                return $messageArray;
            }

            return $message;
        }, $messages);
    }

    /**
     * 获取供应商名称
     */
    public function getProviderName(): string
    {
        return 'deepseek';
    }

    /**
     * 获取支持的模型列表
     */
    public function getModels(): array
    {
        // DeepSeek 支持的模型
        return [
            'deepseek-v4-pro',
            'deepseek-v4-flash',
            'deepseek-chat',      // 将于 2026-07-24 弃用，对应 v4-flash 非思考模式
            'deepseek-coder',     // 将于 2026-07-24 弃用
            'deepseek-reasoner',  // 将于 2026-07-24 弃用，对应 v4-flash 思考模式
        ];
    }
}

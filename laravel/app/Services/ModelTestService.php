<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\ModelTestLog;
use App\Models\PresetPrompt;
use App\Services\Provider\DTO\ProviderRequest;
use App\Services\Provider\ProviderManager;
use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * 模型测试服务
 *
 * 提供两种测试模式:
 * 1. 渠道直接测试: 直接使用 ProviderManager 与上游通信
 * 2. 系统API测试: 通过系统代理流程,使用默认测试 API Key
 */
class ModelTestService
{
    protected ProviderManager $providerManager;

    protected SettingService $settingService;

    public function __construct(ProviderManager $providerManager, SettingService $settingService)
    {
        $this->providerManager = $providerManager;
        $this->settingService = $settingService;
    }

    /**
     * 渠道直接测试
     *
     * 直接使用 ProviderManager 获取渠道驱动并执行测试请求
     *
     * @param  Channel  $channel  测试渠道
     * @param  string  $model  测试模型
     * @param  string|null  $userMessage  用户消息
     * @param  PresetPrompt|null  $presetPrompt  预设提示词
     * @param  bool  $isStream  是否流式
     * @return ModelTestLog 测试日志
     */
    public function testChannelDirect(
        Channel $channel,
        string $model,
        ?string $userMessage = null,
        ?PresetPrompt $presetPrompt = null,
        bool $isStream = false
    ): ModelTestLog {
        $startTime = microtime(true);
        $firstTokenTime = null;

        // 准备测试数据
        $systemPrompt = $presetPrompt?->content;
        $userMessage = $userMessage ?? '你好,请介绍一下你自己';
        $headers = $presetPrompt?->getHeaders() ?? [];

        // 创建测试日志记录
        $log = new ModelTestLog([
            'test_type' => ModelTestLog::TEST_TYPE_CHANNEL_DIRECT,
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'model' => $model,
            'prompt_preset_id' => $presetPrompt?->id,
            'system_prompt' => $systemPrompt,
            'user_message' => $userMessage,
            'request_headers' => $headers,
            'is_stream' => $isStream,
        ]);

        try {
            // 获取渠道 Provider
            $provider = $this->providerManager->getForChannel($channel, $headers);

            // 构建 ProviderRequest
            $messages = [];
            if ($systemPrompt) {
                $messages[] = ['role' => 'system', 'content' => $systemPrompt];
            }
            $messages[] = ['role' => 'user', 'content' => $userMessage];

            $providerRequest = new ProviderRequest(
                model: $model,
                messages: $messages,
                stream: $isStream
            );

            // 执行请求
            if ($isStream) {
                $response = '';
                $generator = $provider->sendStream($providerRequest);

                foreach ($generator as $chunk) {
                    if ($firstTokenTime === null) {
                        $firstTokenTime = microtime(true);
                    }
                    $response .= $chunk->content ?? '';
                }

                $log->assistant_response = $response;
            } else {
                $providerResponse = $provider->send($providerRequest);
                $log->assistant_response = $providerResponse->content;
                $log->prompt_tokens = $providerResponse->promptTokens;
                $log->completion_tokens = $providerResponse->completionTokens;
                $log->total_tokens = $providerResponse->totalTokens;
            }

            // 获取实际模型(如果有模型映射)
            $lastRequestInfo = $provider->getLastRequestInfo();
            $log->actual_model = $lastRequestInfo?->actualModel ?? $model;

            // 计算响应时间
            $endTime = microtime(true);
            $log->response_time_ms = (int) (($endTime - $startTime) * 1000);
            $log->first_token_ms = $firstTokenTime ? (int) (($firstTokenTime - $startTime) * 1000) : null;

            $log->status = ModelTestLog::STATUS_SUCCESS;
        } catch (\Exception $e) {
            $log->status = ModelTestLog::STATUS_FAILED;
            $log->error_message = $e->getMessage();
            $log->response_time_ms = (int) ((microtime(true) - $startTime) * 1000);
        }

        $log->save();

        return $log;
    }

    /**
     * 系统API测试
     *
     * 通过系统自身的API进行测试,使用默认测试 API Key
     *
     * @param  string  $model  测试模型
     * @param  string|null  $userMessage  用户消息
     * @param  PresetPrompt|null  $presetPrompt  预设提示词
     * @param  bool  $isStream  是否流式
     * @return ModelTestLog 测试日志
     */
    public function testSystemApi(
        string $model,
        ?string $userMessage = null,
        ?PresetPrompt $presetPrompt = null,
        bool $isStream = false
    ): ModelTestLog {
        $startTime = microtime(true);
        $firstTokenTime = null;

        // 获取默认测试 API Key
        $defaultApiKey = $this->settingService->get('test.default_test_api_key');

        if (! $defaultApiKey) {
            $log = new ModelTestLog([
                'test_type' => ModelTestLog::TEST_TYPE_SYSTEM_API,
                'model' => $model,
                'prompt_preset_id' => $presetPrompt?->id,
                'system_prompt' => $presetPrompt?->content,
                'user_message' => $userMessage ?? '你好,请介绍一下你自己',
                'is_stream' => $isStream,
                'status' => ModelTestLog::STATUS_FAILED,
                'error_message' => '未配置默认测试 API Key',
                'response_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
            ]);
            $log->save();

            return $log;
        }

        // 准备测试数据
        $systemPrompt = $presetPrompt?->content;
        $userMessage = $userMessage ?? '你好,请介绍一下你自己';
        $presetHeaders = $presetPrompt?->getHeaders() ?? [];

        // 创建测试日志记录
        $log = new ModelTestLog([
            'test_type' => ModelTestLog::TEST_TYPE_SYSTEM_API,
            'model' => $model,
            'prompt_preset_id' => $presetPrompt?->id,
            'system_prompt' => $systemPrompt,
            'user_message' => $userMessage,
            'is_stream' => $isStream,
        ]);

        try {
            // 构建请求
            $baseUrl = config('app.url');
            $endpoint = rtrim($baseUrl, '/').'/api/openai/v1/chat/completions';

            $messages = [];
            if ($systemPrompt) {
                $messages[] = ['role' => 'system', 'content' => $systemPrompt];
            }
            $messages[] = ['role' => 'user', 'content' => $userMessage];

            $requestData = [
                'model' => $model,
                'messages' => $messages,
                'stream' => $isStream,
            ];

            $requestHeaders = array_merge([
                'Authorization' => 'Bearer '.$defaultApiKey,
                'Content-Type' => 'application/json',
            ], $presetHeaders);

            $log->request_headers = $requestHeaders;

            // 执行请求
            if ($isStream) {
                $response = Http::withHeaders($requestHeaders)
                    ->timeout(120)
                    ->post($endpoint, $requestData);

                if (! $response->successful()) {
                    throw new \Exception('请求失败: '.$response->status().' - '.$response->body());
                }

                // 处理流式响应
                $fullContent = '';
                $body = $response->body();
                $lines = explode("\n", $body);

                foreach ($lines as $line) {
                    if (str_starts_with($line, 'data: ')) {
                        $data = substr($line, 6);
                        if ($data === '[DONE]') {
                            break;
                        }

                        $chunk = json_decode($data, true);
                        if (isset($chunk['choices'][0]['delta']['content'])) {
                            if ($firstTokenTime === null) {
                                $firstTokenTime = microtime(true);
                            }
                            $fullContent .= $chunk['choices'][0]['delta']['content'];
                        }

                        // 提取 usage 信息(通常在最后一个 chunk)
                        if (isset($chunk['usage'])) {
                            $log->prompt_tokens = $chunk['usage']['prompt_tokens'] ?? null;
                            $log->completion_tokens = $chunk['usage']['completion_tokens'] ?? null;
                            $log->total_tokens = $chunk['usage']['total_tokens'] ?? null;
                        }
                    }
                }

                $log->assistant_response = $fullContent;
            } else {
                $response = Http::withHeaders($requestHeaders)
                    ->timeout(120)
                    ->post($endpoint, $requestData);

                if (! $response->successful()) {
                    throw new \Exception('请求失败: '.$response->status().' - '.$response->body());
                }

                $data = $response->json();
                $log->assistant_response = $data['choices'][0]['message']['content'] ?? '';
                $log->prompt_tokens = $data['usage']['prompt_tokens'] ?? null;
                $log->completion_tokens = $data['usage']['completion_tokens'] ?? null;
                $log->total_tokens = $data['usage']['total_tokens'] ?? null;
            }

            // 计算响应时间
            $endTime = microtime(true);
            $log->response_time_ms = (int) (($endTime - $startTime) * 1000);
            $log->first_token_ms = $firstTokenTime ? (int) (($firstTokenTime - $startTime) * 1000) : null;

            $log->status = ModelTestLog::STATUS_SUCCESS;
        } catch (ConnectionException $e) {
            $log->status = ModelTestLog::STATUS_TIMEOUT;
            $log->error_message = '请求超时: '.$e->getMessage();
            $log->response_time_ms = (int) ((microtime(true) - $startTime) * 1000);
        } catch (\Exception $e) {
            $log->status = ModelTestLog::STATUS_FAILED;
            $log->error_message = $e->getMessage();
            $log->response_time_ms = (int) ((microtime(true) - $startTime) * 1000);
        }

        $log->save();

        return $log;
    }

    /**
     * 流式测试渠道(用于实时响应)
     *
     * @param  Channel  $channel  测试渠道
     * @param  string  $model  测试模型
     * @param  string|null  $userMessage  用户消息
     * @param  PresetPrompt|null  $presetPrompt  预设提示词
     * @return Generator 流式响应生成器
     */
    public function testChannelDirectStream(
        Channel $channel,
        string $model,
        ?string $userMessage = null,
        ?PresetPrompt $presetPrompt = null
    ): Generator {
        $startTime = microtime(true);
        $firstTokenTime = null;
        $fullResponse = '';

        // 准备测试数据
        $systemPrompt = $presetPrompt?->content;
        $userMessage = $userMessage ?? '你好,请介绍一下你自己';
        $headers = $presetPrompt?->getHeaders() ?? [];

        try {
            // 获取渠道 Provider
            $provider = $this->providerManager->getForChannel($channel, $headers);

            // 构建 ProviderRequest
            $messages = [];
            if ($systemPrompt) {
                $messages[] = ['role' => 'system', 'content' => $systemPrompt];
            }
            $messages[] = ['role' => 'user', 'content' => $userMessage];

            $providerRequest = new ProviderRequest(
                model: $model,
                messages: $messages,
                stream: true
            );

            // 执行流式请求
            $generator = $provider->sendStream($providerRequest);

            foreach ($generator as $chunk) {
                if ($firstTokenTime === null) {
                    $firstTokenTime = microtime(true);
                }
                $fullResponse .= $chunk->content ?? '';
                yield $chunk;
            }

            // 获取实际模型
            $lastRequestInfo = $provider->getLastRequestInfo();

            // 保存测试日志
            $log = new ModelTestLog([
                'test_type' => ModelTestLog::TEST_TYPE_CHANNEL_DIRECT,
                'channel_id' => $channel->id,
                'channel_name' => $channel->name,
                'model' => $model,
                'actual_model' => $lastRequestInfo?->actualModel ?? $model,
                'prompt_preset_id' => $presetPrompt?->id,
                'system_prompt' => $systemPrompt,
                'user_message' => $userMessage,
                'request_headers' => $headers,
                'assistant_response' => $fullResponse,
                'is_stream' => true,
                'response_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'first_token_ms' => $firstTokenTime ? (int) (($firstTokenTime - $startTime) * 1000) : null,
                'status' => ModelTestLog::STATUS_SUCCESS,
            ]);
            $log->save();
        } catch (\Exception $e) {
            // 保存失败日志
            $log = new ModelTestLog([
                'test_type' => ModelTestLog::TEST_TYPE_CHANNEL_DIRECT,
                'channel_id' => $channel->id,
                'channel_name' => $channel->name,
                'model' => $model,
                'prompt_preset_id' => $presetPrompt?->id,
                'system_prompt' => $systemPrompt,
                'user_message' => $userMessage,
                'request_headers' => $headers,
                'is_stream' => true,
                'status' => ModelTestLog::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'response_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
            ]);
            $log->save();

            throw $e;
        }
    }
}

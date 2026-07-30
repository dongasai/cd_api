<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 伪 LLM Mock 控制器。
 *
 * 提供固定内容的 OpenAI / Anthropic 兼容端点，用于测试与演示。
 * 支持流式（SSE）与非流式（JSON）响应，返回固定文案与随机思考内容。
 */
class MockController extends Controller
{
    /**
     * OpenAI Chat Completions 兼容端点。
     */
    public function openaiChatCompletions(Request $request): JsonResponse|StreamedResponse
    {
        $payload = $this->buildPayload($request, 'openai');
        $stream = (bool) ($request->json('stream', false));

        return $stream
            ? $this->streamOpenAi($payload)
            : $this->jsonOpenAi($payload);
    }

    /**
     * Anthropic Messages 兼容端点。
     */
    public function anthropicMessages(Request $request): JsonResponse|StreamedResponse
    {
        $payload = $this->buildPayload($request, 'anthropic');
        $stream = (bool) ($request->json('stream', false));

        return $stream
            ? $this->streamAnthropic($payload)
            : $this->jsonAnthropic($payload);
    }

    /**
     * OpenAI 模型列表。
     */
    public function openaiModels(): JsonResponse
    {
        return response()->json([
            'object' => 'list',
            'data' => [
                ['id' => 'mock-gpt', 'object' => 'model', 'created' => 0, 'owned_by' => 'mock'],
                ['id' => 'mock-claude', 'object' => 'model', 'created' => 0, 'owned_by' => 'mock'],
            ],
        ]);
    }

    /**
     * Anthropic 模型列表。
     */
    public function anthropicModels(): JsonResponse
    {
        return response()->json([
            'data' => [
                ['id' => 'mock-claude', 'display_name' => 'Mock Claude', 'created_at' => '2026-01-01T00:00:00Z'],
                ['id' => 'mock-gpt', 'display_name' => 'Mock GPT', 'created_at' => '2026-01-01T00:00:00Z'],
            ],
        ]);
    }

    /**
     * 构建响应载荷数据。
     */
    private function buildPayload(Request $request, string $protocol): array
    {
        $model = (string) ($request->json('model', $protocol === 'openai' ? 'mock-gpt' : 'mock-claude'));
        $now = Carbon::now()->toDateTimeString();
        $params = $this->extractParams($request);

        return [
            'model' => $model,
            'time' => $now,
            'params' => $params,
            'thinking' => $this->buildThinking($model, $now),
            'content' => $this->buildContent($model, $now, $params),
        ];
    }

    /**
     * 提取请求关键参数（用于回显）。
     */
    private function extractParams(Request $request): string
    {
        // OpenAI 标准参数
        $openaiFields = ['model', 'temperature', 'max_tokens', 'top_p', 'stream', 'system',
            'frequency_penalty', 'presence_penalty', 'n', 'user', 'response_format'];

        // Anthropic 标准参数
        $anthropicFields = ['top_k', 'stop_sequences', 'metadata'];

        $parts = [];

        // 提取 OpenAI 参数
        foreach ($openaiFields as $field) {
            if ($request->has($field)) {
                $value = $request->json($field);
                $parts[] = $field.'='.$this->formatParam($value);
            }
        }

        // 提取 Anthropic 参数
        foreach ($anthropicFields as $field) {
            if ($request->has($field)) {
                $value = $request->json($field);
                $parts[] = $field.'='.$this->formatParam($value);
            }
        }

        // 提取 stop 参数（可能是字符串或数组）
        if ($request->has('stop')) {
            $stop = $request->json('stop');
            $parts[] = 'stop='.$this->formatParam($stop);
        }

        // 提取 messages 摘要
        $messages = $request->json('messages', []);
        $msgCount = is_array($messages) ? count($messages) : 0;
        $parts[] = 'messages_count='.$msgCount;

        // 统计对话轮次和各角色消息数
        if (is_array($messages) && $msgCount > 0) {
            $userCount = 0;
            $assistantCount = 0;
            $systemCount = 0;

            foreach ($messages as $msg) {
                $role = $msg['role'] ?? '';
                if ($role === 'user') {
                    $userCount++;
                } elseif ($role === 'assistant') {
                    $assistantCount++;
                } elseif ($role === 'system') {
                    $systemCount++;
                }
            }

            // 对话轮次 = user 发言次数
            $rounds = $userCount;
            $parts[] = 'rounds='.$rounds;
            $parts[] = 'user_count='.$userCount;
            if ($assistantCount > 0) {
                $parts[] = 'assistant_count='.$assistantCount;
            }
            if ($systemCount > 0) {
                $parts[] = 'system_count='.$systemCount;
            }

            // 提取第一条和最后一条消息摘要
            $firstMsg = $messages[0];
            $lastMsg = $messages[$msgCount - 1];
            $parts[] = 'first_msg='.$this->formatMessage($firstMsg);
            if ($msgCount > 1) {
                $parts[] = 'last_msg='.$this->formatMessage($lastMsg);
            }
        }

        // 提取 tools 信息
        $tools = $request->json('tools', []);
        if (is_array($tools) && count($tools) > 0) {
            $parts[] = 'tools_count='.count($tools);
            $toolNames = array_map(function ($tool) {
                return $tool['type'] ?? 'unknown';
            }, $tools);
            $parts[] = 'tools_types='.implode(',', $toolNames);
        }

        // 提取请求头信息
        $headers = ['x-claude-code-session-id', 'user-agent', 'x-request-id', 'x-api-key'];
        foreach ($headers as $header) {
            $value = $request->headers->get($header);
            if ($value !== null && $value !== '') {
                $parts[] = $header.'='.$value;
            }
        }

        return implode(', ', $parts);
    }

    /**
     * 格式化参数值。
     */
    private function formatParam(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        return (string) $value;
    }

    /**
     * 格式化消息摘要（角色+内容前50字符）。
     */
    private function formatMessage(array $msg): string
    {
        $role = $msg['role'] ?? 'unknown';
        $content = $msg['content'] ?? '';

        if (is_array($content)) {
            // 处理多模态内容
            $content = '[multimodal]';
        } else {
            // 截取前50字符
            $content = mb_substr((string) $content, 0, 50);
        }

        return $role.':'.$content;
    }

    /**
     * 生成思考内容：固定头尾 + 随机 10-100 字符。
     */
    private function buildThinking(string $model, string $time): string
    {
        $length = random_int(10, 100);
        $random = Str::random($length);

        return sprintf('我是%s,当前时间%s,开始思考,%s,思考结束', $model, $time, $random);
    }

    /**
     * 生成正文内容。
     */
    private function buildContent(string $model, string $time, string $params): string
    {
        return sprintf('我是%s模型,当前时间%s,你的输入参数%s', $model, $time, $params);
    }

    /**
     * OpenAI 非流式响应。
     */
    private function jsonOpenAi(array $payload): JsonResponse
    {
        $id = 'chatcmpl-mock-'.Str::random(12);

        return response()->json([
            'id' => $id,
            'object' => 'chat.completion',
            'created' => time(),
            'model' => $payload['model'],
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => $payload['content'],
                        'reasoning_content' => $payload['thinking'],
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 1,
                'completion_tokens' => 1,
                'total_tokens' => 2,
            ],
        ]);
    }

    /**
     * OpenAI 流式响应（SSE）。
     */
    private function streamOpenAi(array $payload): StreamedResponse
    {
        $id = 'chatcmpl-mock-'.Str::random(12);
        $created = time();

        return response()->stream(function () use ($id, $created, $payload): void {
            // 思考内容（reasoning_content delta）
            foreach ($this->splitChunks($payload['thinking'], 8) as $chunk) {
                echo $this->sseData([
                    'id' => $id,
                    'object' => 'chat.completion.chunk',
                    'created' => $created,
                    'model' => $payload['model'],
                    'choices' => [['index' => 0, 'delta' => ['reasoning_content' => $chunk], 'finish_reason' => null]],
                ]);
                $this->delay();
            }

            // 正文内容（content delta）
            foreach ($this->splitChunks($payload['content'], 8) as $chunk) {
                echo $this->sseData([
                    'id' => $id,
                    'object' => 'chat.completion.chunk',
                    'created' => $created,
                    'model' => $payload['model'],
                    'choices' => [['index' => 0, 'delta' => ['content' => $chunk], 'finish_reason' => null]],
                ]);
                $this->delay();
            }

            // 结束标记
            echo $this->sseData([
                'id' => $id,
                'object' => 'chat.completion.chunk',
                'created' => $created,
                'model' => $payload['model'],
                'choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'stop']],
            ]);

            echo "data: [DONE]\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Anthropic 非流式响应。
     */
    private function jsonAnthropic(array $payload): JsonResponse
    {
        return response()->json([
            'id' => 'msg_mock_'.Str::random(12),
            'type' => 'message',
            'role' => 'assistant',
            'model' => $payload['model'],
            'content' => [
                ['type' => 'thinking', 'thinking' => $payload['thinking']],
                ['type' => 'text', 'text' => $payload['content']],
            ],
            'stop_reason' => 'end_turn',
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ]);
    }

    /**
     * Anthropic 流式响应（SSE）。
     */
    private function streamAnthropic(array $payload): StreamedResponse
    {
        $messageId = 'msg_mock_'.Str::random(12);

        return response()->stream(function () use ($messageId, $payload): void {
            // message_start
            echo $this->sseEvent('message_start', [
                'type' => 'message_start',
                'message' => [
                    'id' => $messageId,
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [],
                    'model' => $payload['model'],
                    'stop_reason' => null,
                    'stop_sequence' => null,
                    'usage' => ['input_tokens' => 1, 'output_tokens' => 0],
                ],
            ]);
            $this->delay();

            // thinking 块
            echo $this->sseEvent('content_block_start', [
                'type' => 'content_block_start',
                'index' => 0,
                'content_block' => ['type' => 'thinking', 'thinking' => ''],
            ]);
            $this->delay();

            foreach ($this->splitChunks($payload['thinking'], 8) as $chunk) {
                echo $this->sseEvent('content_block_delta', [
                    'type' => 'content_block_delta',
                    'index' => 0,
                    'delta' => ['type' => 'thinking_delta', 'thinking' => $chunk],
                ]);
                $this->delay();
            }

            echo $this->sseEvent('content_block_stop', [
                'type' => 'content_block_stop',
                'index' => 0,
            ]);
            $this->delay();

            // text 块
            echo $this->sseEvent('content_block_start', [
                'type' => 'content_block_start',
                'index' => 1,
                'content_block' => ['type' => 'text', 'text' => ''],
            ]);
            $this->delay();

            foreach ($this->splitChunks($payload['content'], 8) as $chunk) {
                echo $this->sseEvent('content_block_delta', [
                    'type' => 'content_block_delta',
                    'index' => 1,
                    'delta' => ['type' => 'text_delta', 'text' => $chunk],
                ]);
                $this->delay();
            }

            echo $this->sseEvent('content_block_stop', [
                'type' => 'content_block_stop',
                'index' => 1,
            ]);
            $this->delay();

            // message_delta + message_stop
            echo $this->sseEvent('message_delta', [
                'type' => 'message_delta',
                'delta' => ['stop_reason' => 'end_turn', 'stop_sequence' => null],
                'usage' => ['output_tokens' => 1],
            ]);
            $this->delay();

            echo $this->sseEvent('message_stop', [
                'type' => 'message_stop',
            ]);

            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * 将文本切分为指定长度的片段。
     *
     * @return string[]
     */
    private function splitChunks(string $text, int $size): array
    {
        if ($text === '') {
            return [];
        }

        return str_split($text, $size) ?: [$text];
    }

    /**
     * 格式化 SSE data 行（OpenAI 风格，无 event 名）。
     */
    private function sseData(array $data): string
    {
        return 'data: '.json_encode($data, JSON_UNESCAPED_UNICODE)."\n\n";
    }

    /**
     * 格式化 SSE 事件（Anthropic 风格，带 event 名）。
     */
    private function sseEvent(string $event, array $data): string
    {
        return "event: {$event}\ndata: ".json_encode($data, JSON_UNESCAPED_UNICODE)."\n\n";
    }

    /**
     * 模拟网络延迟（50-150ms）。
     */
    private function delay(): void
    {
        usleep(random_int(50000, 150000));
    }
}

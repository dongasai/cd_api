<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use Illuminate\Console\Command;
use OpenAI;

/**
 * 测试工具调用功能
 *
 * @example php artisan proxy:test-tool-call
 * @example php artisan proxy:test-tool-call "" deepseek-chat
 * @example php artisan proxy:test-tool-call --stream
 */
class TestToolCall extends Command
{
    protected $signature = 'proxy:test-tool-call
                            {key? : API 密钥 (不填则使用第一个活跃的 Key)}
                            {model? : 模型名称}
                            {--stream : 使用流式输出}
                            {--force : 强制工具调用 (tool_choice=required)}';

    protected $description = '测试本站代理 API 的工具调用功能';

    protected array $testTools = [
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_weather',
                'description' => '获取指定城市的天气信息',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'city' => [
                            'type' => 'string',
                            'description' => '城市名称，如：北京、上海',
                        ],
                        'unit' => [
                            'type' => 'string',
                            'enum' => ['celsius', 'fahrenheit'],
                            'description' => '温度单位',
                        ],
                    ],
                    'required' => ['city'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'calculate',
                'description' => '执行数学计算',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'expression' => [
                            'type' => 'string',
                            'description' => '数学表达式，如：2+2, 10*5',
                        ],
                    ],
                    'required' => ['expression'],
                ],
            ],
        ],
    ];

    protected array $testPrompts = [
        '今天北京天气怎么样？',
        '帮我计算 123 * 456 等于多少',
        '上海今天热吗？请用摄氏度告诉我',
        '计算 (100 + 200) / 3 的结果',
    ];

    public function handle(): int
    {
        $apiKey = $this->argument('key');

        if (empty($apiKey)) {
            $firstKey = ApiKey::where('status', 'active')
                ->orderBy('id')
                ->first();

            if (! $firstKey) {
                $this->error('没有找到活跃的 API Key，请手动指定密钥');

                return self::FAILURE;
            }

            $apiKey = $firstKey->key;
            $this->info("使用默认 Key: {$firstKey->getMaskedKey()} (ID: {$firstKey->id})");
        }

        $model = $this->argument('model');

        if (empty($model)) {
            $model = $this->askForModel();
        }

        $prompt = $this->choice('请选择测试提示语', $this->testPrompts, 0);
        $useStream = $this->option('stream');
        $forceTool = $this->option('force');
        $baseUrl = config('app.url').'/api/openai/v1';

        $this->info('=== 工具调用测试 ===');
        $this->info("Base URL: {$baseUrl}");
        $this->info("模型: {$model}");
        $this->info("提示语: {$prompt}");
        $this->info('流式输出: '.($useStream ? '是' : '否'));
        $this->info('强制工具调用: '.($forceTool ? '是' : '否'));
        $this->newLine();

        $this->info('可用工具:');
        foreach ($this->testTools as $tool) {
            $this->info("  - {$tool['function']['name']}: {$tool['function']['description']}");
        }
        $this->newLine();

        $client = OpenAI::factory()
            ->withApiKey($apiKey)
            ->withBaseUri($baseUrl)
            ->make();

        try {
            $startTime = microtime(true);

            if ($useStream) {
                return $this->streamResponse($client, $model, $prompt, $forceTool, $startTime);
            }

            return $this->standardResponse($client, $model, $prompt, $forceTool, $startTime);

        } catch (\Throwable $e) {
            $this->error('请求失败: '.$e->getMessage());
            $this->error('异常类型: '.get_class($e));

            if ($e->getPrevious()) {
                $this->error('前一个异常: '.$e->getPrevious()->getMessage());
            }

            return self::FAILURE;
        }
    }

    protected function standardResponse($client, string $model, string $prompt, bool $forceTool, float $startTime): int
    {
        $this->info('发送请求...');

        $params = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'tools' => $this->testTools,
            'max_tokens' => 1024,
        ];

        if ($forceTool) {
            $params['tool_choice'] = 'required';
        }

        $response = $client->chat()->create($params);

        $latency = round((microtime(true) - $startTime) * 1000, 2);

        $this->info('✓ 请求成功');
        $this->info("延迟: {$latency}ms");
        $this->newLine();

        $choice = $response->choices[0];
        $message = $choice->message;

        $this->info('--- 响应信息 ---');
        $this->info("模型: {$response->model}");
        $this->info("完成原因: {$choice->finishReason}");
        $this->newLine();

        if (isset($response->usage)) {
            $this->info('Token 使用:');
            $this->info("  提示词: {$response->usage->promptTokens}");
            $this->info("  完成: {$response->usage->completionTokens}");
            $this->info("  总计: {$response->usage->totalTokens}");
            $this->newLine();
        }

        if ($message->content) {
            $this->info('--- 文本内容 ---');
            $this->line($message->content);
            $this->newLine();
        }

        if (! empty($message->toolCalls)) {
            $this->info('--- 工具调用 ---');
            $this->info('✓ 模型正确调用了工具！');
            $this->newLine();

            foreach ($message->toolCalls as $index => $toolCall) {
                $this->info("工具调用 #{$index}:");
                $this->info("  ID: {$toolCall->id}");
                $this->info("  类型: {$toolCall->type}");
                $this->info("  函数名: {$toolCall->function->name}");
                $this->info('  参数: '.$toolCall->function->arguments);
                $this->newLine();
            }

            $this->info('--- 模拟工具响应 ---');
            $toolResults = $this->simulateToolCalls($message->toolCalls);

            foreach ($toolResults as $toolCallId => $result) {
                $this->info("结果: {$result}");
            }

            return self::SUCCESS;
        }

        $this->warn('⚠ 模型没有调用工具，而是直接返回了文本响应');
        $this->newLine();
        $this->info('建议:');
        $this->info('  1. 使用 --force 参数强制工具调用');
        $this->info('  2. 尝试其他工具调用能力更强的模型');
        $this->info('  3. 检查提示语是否明确需要使用工具');

        return self::SUCCESS;
    }

    protected function streamResponse($client, string $model, string $prompt, bool $forceTool, float $startTime): int
    {
        $this->info('发送流式请求...');
        $this->newLine();

        $params = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'tools' => $this->testTools,
            'max_tokens' => 1024,
        ];

        if ($forceTool) {
            $params['tool_choice'] = 'required';
        }

        $stream = $client->chat()->createStreamed($params);

        $fullContent = '';
        $firstToken = true;
        $ttft = 0;
        $toolCalls = [];
        $finishReason = null;

        $this->info('--- 响应流 ---');

        foreach ($stream as $chunk) {
            if ($firstToken) {
                $ttft = round((microtime(true) - $startTime) * 1000, 2);
                $firstToken = false;
            }

            $choice = $chunk->choices[0] ?? null;
            if (! $choice) {
                continue;
            }

            if ($choice->finishReason) {
                $finishReason = $choice->finishReason;
            }

            $delta = $choice->delta;

            if ($delta->content) {
                $this->getOutput()->write($delta->content);
                $fullContent .= $delta->content;
            }

            if (! empty($delta->toolCalls)) {
                foreach ($delta->toolCalls as $toolCallDelta) {
                    $index = $toolCallDelta->index ?? 0;

                    if (! isset($toolCalls[$index])) {
                        $toolCalls[$index] = [
                            'id' => '',
                            'type' => 'function',
                            'function' => [
                                'name' => '',
                                'arguments' => '',
                            ],
                        ];
                    }

                    if ($toolCallDelta->id) {
                        $toolCalls[$index]['id'] = $toolCallDelta->id;
                    }

                    if ($toolCallDelta->type) {
                        $toolCalls[$index]['type'] = $toolCallDelta->type;
                    }

                    if ($toolCallDelta->function) {
                        if ($toolCallDelta->function->name) {
                            $toolCalls[$index]['function']['name'] = $toolCallDelta->function->name;
                        }
                        if ($toolCallDelta->function->arguments) {
                            $toolCalls[$index]['function']['arguments'] .= $toolCallDelta->function->arguments;
                        }
                    }
                }
            }
        }

        $latency = round((microtime(true) - $startTime) * 1000, 2);

        $this->newLine();
        $this->info('--- 响应流结束 ---');
        $this->newLine();

        $this->info('✓ 请求成功');
        $this->info("首字延迟 (TTFT): {$ttft}ms");
        $this->info("总延迟: {$latency}ms");
        $this->info("完成原因: {$finishReason}");
        $this->newLine();

        if (! empty($toolCalls)) {
            $this->info('--- 工具调用 ---');
            $this->info('✓ 模型正确调用了工具！');
            $this->newLine();

            foreach ($toolCalls as $index => $toolCall) {
                $this->info("工具调用 #{$index}:");
                $this->info("  ID: {$toolCall['id']}");
                $this->info("  类型: {$toolCall['type']}");
                $this->info("  函数名: {$toolCall['function']['name']}");
                $this->info("  参数: {$toolCall['function']['arguments']}");
                $this->newLine();
            }

            return self::SUCCESS;
        }

        if ($fullContent) {
            $this->warn('⚠ 模型没有调用工具，而是直接返回了文本响应');
        } else {
            $this->warn('⚠ 模型没有返回任何内容');
        }

        return self::SUCCESS;
    }

    protected function simulateToolCalls(array $toolCalls): array
    {
        $results = [];

        foreach ($toolCalls as $toolCall) {
            $functionName = $toolCall->function->name;
            $arguments = json_decode($toolCall->function->arguments, true) ?? [];

            $result = match ($functionName) {
                'get_weather' => $this->simulateWeather($arguments['city'] ?? '未知'),
                'calculate' => $this->simulateCalculate($arguments['expression'] ?? '0'),
                default => "未知工具: {$functionName}",
            };

            $results[$toolCall->id] = $result;
        }

        return $results;
    }

    protected function simulateWeather(string $city): string
    {
        $weathers = ['晴天', '多云', '小雨', '大雨', '雪'];

        return sprintf(
            '%s今天天气: %s, 温度: %d°C, 湿度: %d%%',
            $city,
            $weathers[array_rand($weathers)],
            rand(10, 35),
            rand(30, 80)
        );
    }

    protected function simulateCalculate(string $expression): string
    {
        try {
            $expression = preg_replace('/[^0-9+\-*\/().]/', '', $expression);
            $result = eval("return {$expression};");

            return "计算结果: {$expression} = {$result}";
        } catch (\Throwable $e) {
            return "计算错误: 无法计算 {$expression}";
        }
    }

    protected function askForModel(): string
    {
        $models = [
            'gpt-4o',
            'gpt-4o-mini',
            'gpt-4-turbo',
            'claude-3-5-sonnet-20241022',
            'claude-3-haiku-20240307',
            'deepseek-chat',
            'deepseek-reasoner',
            'Step-3.5-Flash',
        ];

        $choices = array_merge($models, ['自定义']);
        $selected = $this->choice('请选择模型', $choices, 0);

        if ($selected === '自定义') {
            return $this->ask('请输入模型名称');
        }

        return $selected;
    }
}

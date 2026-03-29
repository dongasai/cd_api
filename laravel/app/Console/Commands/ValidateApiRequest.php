<?php

namespace App\Console\Commands;

use App\Models\ChannelRequestLog;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * 验证渠道请求日志的请求体是否符合OpenAPI规范
 */
class ValidateApiRequest extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cdapi:validate-request
                            {id : 渠道请求日志ID}
                            {--protocol= : 手动指定协议类型 (openai|anthropic)，默认自动判断}
                            {--show-body : 显示完整的请求体内容}';

    /**
     * The console command description.
     */
    protected $description = '根据OpenAPI规范验证渠道请求日志的请求体';

    /**
     * 协议类型与OpenAPI规范文件的映射
     */
    private array $protocolSpecMap = [
        'openai' => 'openai-openapi.yml',
        'anthropic' => 'anthropic-openapi.yml',
    ];

    /**
     * API路径与Schema名称的映射
     */
    private array $apiSchemaMap = [
        'openai' => [
            '/v1/chat/completions' => 'CreateChatCompletionRequest',
            '/chat/completions' => 'CreateChatCompletionRequest',
        ],
        'anthropic' => [
            '/v1/messages' => 'CreateMessageParams',
            '/messages' => 'CreateMessageParams',
        ],
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $id = $this->argument('id');

        // 1. 查询请求日志
        $this->info('正在查询请求日志...');
        $log = ChannelRequestLog::with('channel')->find($id);

        if (! $log) {
            $this->error("未找到请求日志ID: {$id}");

            return self::FAILURE;
        }

        $this->displayLogInfo($log);

        // 2. 确定协议类型
        $protocol = $this->determineProtocol($log);
        $this->info("协议类型: {$protocol}");

        // 3. 检查OpenAPI规范文件
        $specFile = storage_path($this->protocolSpecMap[$protocol]);
        if (! file_exists($specFile)) {
            $this->error("OpenAPI规范文件不存在: {$specFile}");
            $this->line('');
            $this->comment('请先下载OpenAPI规范文件：');
            if ($protocol === 'openai') {
                $this->line('  php artisan cdapi:update-openapi-spec --force');
            } else {
                $this->line('  php artisan cdapi:update-anthropic-spec --force');
            }

            return self::FAILURE;
        }

        // 4. 解析OpenAPI规范
        $this->info('解析OpenAPI规范...');
        $openapiArray = Yaml::parseFile($specFile);

        // 5. 确定Schema名称
        $schemaName = $this->determineSchemaName($protocol, $log->path);
        if (! $schemaName) {
            $this->error("未找到API路径对应的Schema: {$log->path}");
            $this->line('支持的路径：');
            foreach ($this->apiSchemaMap[$protocol] as $path => $name) {
                $this->line("  - {$path} => {$name}");
            }

            return self::FAILURE;
        }

        $this->info("Schema名称: {$schemaName}");

        // 6. 提取Schema定义
        if (! isset($openapiArray['components']['schemas'][$schemaName])) {
            $this->error("Schema定义不存在: {$schemaName}");

            return self::FAILURE;
        }

        $schemaArray = $openapiArray['components']['schemas'][$schemaName];

        // 7. 准备验证数据
        if (! $log->request_body) {
            $this->error('请求体为空，无法验证');

            return self::FAILURE;
        }

        // request_body 在模型中已被转换为数组
        $requestBody = $log->request_body;

        // 显示请求体（可选）
        if ($this->option('show-body')) {
            $this->line('');
            $this->comment('请求体内容：');
            $this->line(json_encode($requestBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        // 8. 执行验证
        $this->line('');
        $this->info('开始验证请求体...');

        // JSON Schema验证需要对象而非数组
        $data = json_decode(json_encode($requestBody));

        // 方法：使用简单的Laravel Validator进行基本验证
        // 因为OpenAPI Schema很复杂，$ref引用解析困难
        // 我们使用基本规则验证关键字段

        $this->comment('使用基本验证规则（OpenAPI Schema的简化版本）：');

        // 定义基本验证规则
        $rules = $this->getBasicValidationRules($protocol, $schemaName);

        // 使用Laravel Validator
        $validator = \Illuminate\Support\Facades\Validator::make($requestBody, $rules);

        if ($validator->fails()) {
            $this->error('❌ 请求体验证失败：');
            $errors = $validator->errors()->all();
            foreach ($errors as $index => $error) {
                $num = $index + 1;
                $this->line("  {$num}. {$error}");
            }

            return self::FAILURE;
        } else {
            $this->info('✅ 请求体基本验证通过！');
            $this->comment('注意：仅验证了关键字段，完整OpenAPI Schema验证暂未实现');

            return self::SUCCESS;
        }
    }

    /**
     * 显示日志基本信息
     */
    private function displayLogInfo(ChannelRequestLog $log): void
    {
        $this->line('');
        $this->comment('请求日志信息：');
        $this->line("  ID: {$log->id}");
        $this->line("  请求ID: {$log->request_id}");
        $this->line("  渠道: {$log->channel_name} (ID: {$log->channel_id})");
        $this->line("  Provider: {$log->provider}");
        $this->line("  路径: {$log->path}");
        $this->line("  方法: {$log->method}");
        $this->line("  创建时间: {$log->created_at}");
        $this->line("  请求体大小: {$log->request_size} 字节");
        if ($log->response_status) {
            $this->line("  响应状态: {$log->response_status}");
        }
    }

    /**
     * 确定协议类型
     */
    private function determineProtocol(ChannelRequestLog $log): string
    {
        // 如果手动指定，使用指定的协议
        if ($this->option('protocol')) {
            return $this->option('protocol');
        }

        // 从provider字段推断
        $provider = strtolower($log->provider ?? '');

        // OpenAI系列提供商
        if (in_array($provider, ['openai', 'azure', 'deepseek', 'moonshot', 'zhipu', 'qwen'])) {
            return 'openai';
        }

        // Anthropic系列提供商
        if (in_array($provider, ['anthropic', 'claude'])) {
            return 'anthropic';
        }

        // 默认使用OpenAI协议
        $this->warn('无法确定协议类型，使用默认协议: openai');

        return 'openai';
    }

    /**
     * 确定Schema名称
     */
    private function determineSchemaName(string $protocol, string $path): ?string
    {
        // 标准化路径（移除查询参数）
        $path = parse_url($path, PHP_URL_PATH) ?? $path;

        return $this->apiSchemaMap[$protocol][$path] ?? null;
    }

    /**
     * 获取基本验证规则
     */
    private function getBasicValidationRules(string $protocol, string $schemaName): array
    {
        if ($protocol === 'openai' && $schemaName === 'CreateChatCompletionRequest') {
            return [
                'model' => 'required|string',
                'messages' => 'required|array|min:1',
                'messages.*.role' => 'required|string|in:system,user,assistant,tool,function',
                'messages.*.content' => 'required_without:messages.*.tool_calls',
                'temperature' => 'nullable|numeric|between:0,2',
                'top_p' => 'nullable|numeric|between:0,1',
                'n' => 'nullable|integer|min:1|max:10',
                'stream' => 'nullable|boolean',
                'stop' => 'nullable|array',
                'max_tokens' => 'nullable|integer|min:1',
                'max_completion_tokens' => 'nullable|integer|min:1',
                'presence_penalty' => 'nullable|numeric|between:-2,2',
                'frequency_penalty' => 'nullable|numeric|between:-2,2',
                'tools' => 'nullable|array',
                'tool_choice' => 'nullable',
            ];
        }

        if ($protocol === 'anthropic' && $schemaName === 'CreateMessageParams') {
            return [
                'model' => 'required|string',
                'messages' => 'required|array|min:1',
                'messages.*.role' => 'required|string|in:user,assistant',
                'messages.*.content' => 'required',
                'max_tokens' => 'required|integer|min:1',
                'system' => 'nullable', // system可以是string或array
                'temperature' => 'nullable|numeric|between:0,1',
                'top_p' => 'nullable|numeric|between:0,1',
                'top_k' => 'nullable|integer|min:0',
                'stop_sequences' => 'nullable|array',
                'tools' => 'nullable|array',
                'tool_choice' => 'nullable',
            ];
        }

        // 默认规则
        return [
            'model' => 'required|string',
            'messages' => 'required|array',
        ];
    }
}

<?php

namespace App\Console\Commands;

use App\Models\ChannelRequestLog;
use App\Models\RequestLog;
use Illuminate\Console\Command;

/**
 * 分析 request_log 和 channel_request_logs 的参数差异
 * php artisan  analyze:request-diff 1290 --limit 1
 */
class AnalyzeRequestDiff extends Command
{
    protected $signature = 'analyze:request-diff
                            {audit_log_id? : 审计日志ID}
                            {--limit=10 : 显示差异的最大条数}';

    protected $description = '分析 request_log 和 channel_request_logs 的请求体差异';

    // 差异计数器
    private int $diffCount = 0;

    public function handle(): int
    {
        $auditLogId = $this->argument('audit_log_id');
        $diffLimit = (int) $this->option('limit');

        if ($auditLogId) {
            $this->analyzeByAuditLogId((int) $auditLogId, $diffLimit);
        } else {
            $this->error('请提供审计日志ID');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * 根据审计日志ID分析差异
     */
    private function analyzeByAuditLogId(int $auditLogId, int $diffLimit): void
    {
        $this->info('='.str_repeat('=', 80));
        $this->info("分析审计日志 ID: {$auditLogId}");
        $this->info('='.str_repeat('=', 80));
        $this->newLine();

        $channelLog = ChannelRequestLog::where('audit_log_id', $auditLogId)->first();

        if (! $channelLog) {
            $this->error('未找到 channel_request_logs 记录');

            return;
        }

        // 使用 request_id 关联
        $requestLog = RequestLog::where('request_id', $channelLog->request_id)->first();

        if (! $requestLog) {
            $this->error("未找到 request_log 记录 (request_id: {$channelLog->request_id})");

            return;
        }

        $this->compareLogs($requestLog, $channelLog, $diffLimit);
    }

    /**
     * 比较两个日志的差异
     */
    private function compareLogs(RequestLog $requestLog, ChannelRequestLog $channelLog, int $diffLimit): void
    {
        // 检查是否为 anthropic to anthropic 转发
        $isAnthropicToAnthropic = $this->isAnthropicToAnthropic($requestLog, $channelLog);

        // 显示基本信息
        $this->section('基本信息');
        $this->table(
            ['字段', 'Request Log', 'Channel Log'],
            [
                ['ID', $requestLog->id, $channelLog->id],
                ['Audit Log ID', $requestLog->audit_log_id ?? 'N/A', $channelLog->audit_log_id],
                ['Request ID', $requestLog->request_id, $channelLog->request_id],
                ['Model', $requestLog->model ?? 'N/A', $channelLog->metadata['model'] ?? 'N/A'],
                ['Path', $requestLog->path ?? 'N/A', $channelLog->path ?? 'N/A'],
                ['Provider', 'N/A', $channelLog->provider ?? 'N/A'],
                ['Status', 'N/A', $channelLog->response_status ?? 'N/A'],
                ['Is Success', 'N/A', $channelLog->is_success ? 'Yes' : 'No'],
                ['转发类型', 'N/A', $isAnthropicToAnthropic ? 'anthropic → anthropic' : '其他'],
            ]
        );

        // 解析请求体
        $requestBody = $this->parseBody($requestLog->body_text);
        $channelBody = $this->parseBody($channelLog->request_body);

        // 比较 messages
        $this->section('Messages 对比');
        $this->compareMessages($requestBody['messages'] ?? [], $channelBody['messages'] ?? [], $diffLimit);

        // 比较其他字段
        $this->section('其他字段对比');
        $this->compareOtherFields($requestBody, $channelBody, $diffLimit);

        // 显示总结
        $this->section('总结');
        if ($this->diffCount === 0) {
            $this->info('✓ 没有发现差异，请求体完全一致');
        } else {
            $this->warn("发现 {$this->diffCount} 处差异");
            if ($this->diffCount >= $diffLimit) {
                $this->warn("(已达到显示上限 {$diffLimit}，可能还有更多差异)");
            }
        }
    }

    /**
     * 检查是否为 anthropic to anthropic 转发
     */
    private function isAnthropicToAnthropic(RequestLog $requestLog, ChannelRequestLog $channelLog): bool
    {
        $isAnthropicPath = str_contains($requestLog->path ?? '', 'anthropic');
        $isAnthropicProvider = $channelLog->provider === 'anthropic';

        return $isAnthropicPath && $isAnthropicProvider;
    }

    /**
     * 解析请求体
     */
    private function parseBody(array|string|null $body): array
    {
        if (! $body) {
            return [];
        }

        if (is_array($body)) {
            return $body;
        }

        $decoded = json_decode($body, true);

        return $decoded ?? [];
    }

    /**
     * 比较消息列表
     */
    private function compareMessages(array $requestMessages, array $channelMessages, int $diffLimit): void
    {
        $requestCount = count($requestMessages);
        $channelCount = count($channelMessages);

        $this->info("消息数量: Request={$requestCount}, Channel={$channelCount}");

        if ($requestCount !== $channelCount) {
            $this->printDiff('消息数量', "Request: {$requestCount}", "Channel: {$channelCount}", $diffLimit);
        }

        $maxCount = max($requestCount, $channelCount);

        for ($i = 0; $i < $maxCount && $this->diffCount < $diffLimit; $i++) {
            $reqMsg = $requestMessages[$i] ?? null;
            $chanMsg = $channelMessages[$i] ?? null;

            if (! $reqMsg) {
                $this->printDiff("消息[{$i}]", '不存在', '存在', $diffLimit);

                continue;
            }

            if (! $chanMsg) {
                $this->printDiff("消息[{$i}]", '存在', '不存在', $diffLimit);

                continue;
            }

            $this->compareSingleMessage($i, $reqMsg, $chanMsg, $diffLimit);
        }
    }

    /**
     * 比较单条消息
     */
    private function compareSingleMessage(int $index, array $reqMsg, array $chanMsg, int $diffLimit): void
    {
        $reqRole = $reqMsg['role'] ?? 'unknown';
        $chanRole = $chanMsg['role'] ?? 'unknown';

        // 比较 role
        if ($reqRole !== $chanRole && $this->diffCount < $diffLimit) {
            $this->printDiff("消息[{$index}].role", $reqRole, $chanRole, $diffLimit);
        }

        // 比较 content
        $reqContent = $reqMsg['content'] ?? null;
        $chanContent = $chanMsg['content'] ?? null;

        if ($this->diffCount >= $diffLimit) {
            return;
        }

        // 类型不一致
        $reqType = gettype($reqContent);
        $chanType = gettype($chanContent);

        if ($reqType !== $chanType) {
            $this->printDiff(
                "消息[{$index}].content 类型",
                "{$reqType}: ".$this->truncate(json_encode($reqContent)),
                "{$chanType}: ".$this->truncate(json_encode($chanContent)),
                $diffLimit
            );

            return;
        }

        // 都是数组（内容块）
        if (is_array($reqContent) && is_array($chanContent)) {
            $this->compareContentBlocks($index, $reqRole, $reqContent, $chanContent, $diffLimit);

            return;
        }

        // 都是字符串
        if (is_string($reqContent) && is_string($chanContent)) {
            if ($reqContent !== $chanContent && $this->diffCount < $diffLimit) {
                $this->printDiff(
                    "消息[{$index}].content",
                    $this->truncate($reqContent),
                    $this->truncate($chanContent),
                    $diffLimit
                );
            }

            return;
        }

        // 其他类型
        if ($reqContent !== $chanContent && $this->diffCount < $diffLimit) {
            $this->printDiff(
                "消息[{$index}].content",
                $this->truncate(json_encode($reqContent)),
                $this->truncate(json_encode($chanContent)),
                $diffLimit
            );
        }
    }

    /**
     * 比较内容块
     */
    private function compareContentBlocks(int $msgIndex, string $role, array $reqContent, array $chanContent, int $diffLimit): void
    {
        $reqCount = count($reqContent);
        $chanCount = count($chanContent);

        if ($reqCount !== $chanCount && $this->diffCount < $diffLimit) {
            $this->printDiff(
                "消息[{$msgIndex}]({$role}) 内容块数量",
                "{$reqCount} 个",
                "{$chanCount} 个",
                $diffLimit
            );
        }

        $maxCount = max($reqCount, $chanCount);

        for ($i = 0; $i < $maxCount && $this->diffCount < $diffLimit; $i++) {
            $reqBlock = $reqContent[$i] ?? null;
            $chanBlock = $chanContent[$i] ?? null;

            if (! $reqBlock) {
                $chanType = $chanBlock['type'] ?? 'unknown';
                $this->printDiff(
                    "消息[{$msgIndex}].content[{$i}]",
                    '不存在',
                    "类型: {$chanType}",
                    $diffLimit
                );

                continue;
            }

            if (! $chanBlock) {
                $reqType = $reqBlock['type'] ?? 'unknown';
                $this->printDiff(
                    "消息[{$msgIndex}].content[{$i}]",
                    "类型: {$reqType}",
                    '不存在',
                    $diffLimit
                );

                continue;
            }

            // 比较类型
            $reqType = $reqBlock['type'] ?? 'unknown';
            $chanType = $chanBlock['type'] ?? 'unknown';

            if ($reqType !== $chanType && $this->diffCount < $diffLimit) {
                $this->printDiff(
                    "消息[{$msgIndex}].content[{$i}].type",
                    $reqType,
                    $chanType,
                    $diffLimit
                );
            }

            // 比较其他字段
            foreach ($reqBlock as $key => $value) {
                if ($key === 'type') {
                    continue;
                }

                if ($this->diffCount >= $diffLimit) {
                    break 2;
                }

                $chanValue = $chanBlock[$key] ?? null;

                if ($value !== $chanValue) {
                    $this->printDiff(
                        "消息[{$msgIndex}].content[{$i}].{$key}",
                        $this->truncate(json_encode($value)),
                        $this->truncate(json_encode($chanValue)),
                        $diffLimit
                    );
                }
            }

            // 检查 channel 有但 request 没有的字段
            foreach ($chanBlock as $key => $value) {
                if ($key === 'type' || isset($reqBlock[$key])) {
                    continue;
                }

                if ($this->diffCount >= $diffLimit) {
                    break 2;
                }

                $this->printDiff(
                    "消息[{$msgIndex}].content[{$i}].{$key}",
                    '不存在',
                    $this->truncate(json_encode($value)),
                    $diffLimit
                );
            }
        }
    }

    /**
     * 比较其他字段
     */
    private function compareOtherFields(array $requestBody, array $channelBody, int $diffLimit): void
    {
        $fields = ['model', 'max_tokens', 'temperature', 'stream', 'system', 'tools', 'tool_choice'];

        foreach ($fields as $field) {
            if ($this->diffCount >= $diffLimit) {
                break;
            }

            $reqValue = $requestBody[$field] ?? null;
            $chanValue = $channelBody[$field] ?? null;

            // 标准化比较
            $reqStr = $this->normalizeValue($reqValue);
            $chanStr = $this->normalizeValue($chanValue);

            if ($reqStr !== $chanStr) {
                $this->printDiff($field, $reqStr, $chanStr, $diffLimit);
            }
        }

        if ($this->diffCount === 0) {
            $this->info('所有字段一致');
        }
    }

    /**
     * 打印差异
     */
    private function printDiff(string $field, string $requestValue, string $channelValue, int $diffLimit): void
    {
        if ($this->diffCount >= $diffLimit) {
            return;
        }

        $this->diffCount++;

        $this->warn("差异 #{$this->diffCount}: {$field}");
        $this->line("  <fg=green>[Request]</> {$requestValue}");
        $this->line("  <fg=red>[Channel]</> {$channelValue}");
        $this->newLine();
    }

    /**
     * 截断长字符串
     */
    private function truncate(string $text, int $maxLength = 200): string
    {
        if (strlen($text) <= $maxLength) {
            return $text;
        }

        return substr($text, 0, $maxLength).'... ('.(strlen($text) - $maxLength).' more chars)';
    }

    /**
     * 标准化值用于比较
     */
    private function normalizeValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }

    /**
     * 输出章节标题
     */
    private function section(string $title): void
    {
        $this->newLine();
        $this->info("┌─ {$title} ".str_repeat('─', 70 - strlen($title)));
    }
}

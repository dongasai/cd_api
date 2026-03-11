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
                            {--limit=10 : 显示差异的最大条数}
                            {--show-diff : 对大型文本差异显示行级 diff}
                            {--diff-chars=200 : 单处 diff 最多显示的字符数}';

    protected $description = '分析 request_log 和 channel_request_logs 的请求体差异';

    // 差异计数器
    private int $diffCount = 0;

    // 是否显示行级 diff
    private bool $showDiff = false;

    // 单处 diff 字符限制
    private int $diffChars = 200;

    public function handle(): int
    {
        $auditLogId = $this->argument('audit_log_id');
        $diffLimit = (int) $this->option('limit');
        $this->showDiff = (bool) $this->option('show-diff');
        $this->diffChars = (int) $this->option('diff-chars');

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

        // 优先使用 request_log_id 直接关联（如果该字段存在）
        if (isset($channelLog->request_log_id) && $channelLog->request_log_id) {
            $requestLog = RequestLog::find($channelLog->request_log_id);

            if (! $requestLog) {
                $this->error("未找到 request_log 记录 (ID: {$channelLog->request_log_id})");

                return;
            }
        } else {
            // 降级方案：使用 request_id 关联
            $requestLog = RequestLog::where('request_id', $channelLog->request_id)->first();

            if (! $requestLog) {
                $this->error("未找到 request_log 记录 (request_id: {$channelLog->request_id})");

                return;
            }
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
                "{$reqType}: ",
                "{$chanType}: ",
                $diffLimit,
                $this->formatValue($reqContent),
                $this->formatValue($chanContent)
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
                $isLargeDiff = strlen($reqContent) > 100 || strlen($chanContent) > 100;
                $this->printDiff(
                    "消息[{$index}].content",
                    $isLargeDiff ? '[大型文本，见下方 diff]' : $reqContent,
                    $isLargeDiff ? '[大型文本，见下方 diff]' : $chanContent,
                    $diffLimit,
                    $reqContent,
                    $chanContent
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

                // 如果 channel 中不存在该字段，跳过（后面会专门处理缺失字段的情况）
                if (! array_key_exists($key, $chanBlock)) {
                    continue;
                }

                $chanValue = $chanBlock[$key];

                if ($value !== $chanValue) {
                    $isLargeText = is_string($value) && strlen($value) > 100;
                    $isLargeChanText = is_string($chanValue) && strlen($chanValue) > 100;

                    $this->printDiff(
                        "消息[{$msgIndex}].content[{$i}].{$key}",
                        ($isLargeText ? '[大型文本，见下方 diff]' : $this->truncate(json_encode($value))),
                        ($isLargeChanText ? '[大型文本，见下方 diff]' : $this->truncate(json_encode($chanValue))),
                        $diffLimit,
                        is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                        is_string($chanValue) ? $chanValue : json_encode($chanValue, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
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
                    "消息 [{$msgIndex}].content[{$i}].{$key}",
                    '不存在',
                    $this->truncate(json_encode($value)),
                    $diffLimit
                );
            }

            // 检查 request 有但 channel 没有的字段
            foreach ($reqBlock as $key => $value) {
                if ($key === 'type' || isset($chanBlock[$key])) {
                    continue;
                }

                if ($this->diffCount >= $diffLimit) {
                    break 2;
                }

                $this->printDiff(
                    "消息 [{$msgIndex}].content[{$i}].{$key}",
                    $this->truncate(json_encode($value)),
                    '不存在',
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
        // 获取所有字段（排除 messages，因为已经单独比较了）
        $allFields = array_unique(array_merge(
            array_keys($requestBody),
            array_keys($channelBody)
        ));

        $excludeFields = ['messages']; // 排除已经单独比较的字段

        foreach ($allFields as $field) {
            if (in_array($field, $excludeFields)) {
                continue;
            }

            if ($this->diffCount >= $diffLimit) {
                break;
            }

            $reqValue = $requestBody[$field] ?? null;
            $chanValue = $channelBody[$field] ?? null;

            // 如果一边存在另一边不存在
            if (! array_key_exists($field, $requestBody)) {
                $this->printDiff(
                    $field,
                    '不存在',
                    $this->truncate(json_encode($chanValue)),
                    $diffLimit
                );

                continue;
            }

            if (! array_key_exists($field, $channelBody)) {
                $this->printDiff(
                    $field,
                    $this->truncate(json_encode($reqValue)),
                    '不存在',
                    $diffLimit
                );

                continue;
            }

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
    private function printDiff(string $field, string $requestValue, string $channelValue, int $diffLimit, ?string $fullRequestValue = null, ?string $fullChannelValue = null): void
    {
        if ($this->diffCount >= $diffLimit) {
            return;
        }

        $this->diffCount++;

        $this->warn("差异 #{$this->diffCount}: {$field}");

        // 截断显示的值，最多显示 100 字符
        $displayRequest = $this->truncateForDisplay($requestValue, 100);
        $displayChannel = $this->truncateForDisplay($channelValue, 100);

        $this->line("  <fg=green>[Request]</> {$displayRequest}");
        $this->line("  <fg=red>[Channel]</> {$displayChannel}");

        // 如果是大型文本差异且启用了 show-diff，输出行级 diff
        if ($this->showDiff && ($fullRequestValue !== null || $fullChannelValue !== null)) {
            $this->printLineDiff($fullRequestValue ?? $requestValue, $fullChannelValue ?? $channelValue);
        }

        $this->newLine();
    }

    /**
     * 截断用于显示的值 - 显示头部 + 省略部分 + 尾部
     */
    private function truncateForDisplay(string $text, int $maxLength = 100): string
    {
        $textLength = strlen($text);
        if ($textLength <= $maxLength) {
            return $text;
        }

        // 头部和尾部各占一半（减3用于省略号）
        $headLength = (int) floor(($maxLength - 3) / 2);
        $tailLength = (int) ceil(($maxLength - 3) / 2);

        $head = substr($text, 0, $headLength);
        $tail = substr($text, -$tailLength);

        return $head.'...'.$tail.' ('.($textLength - $maxLength).' chars)';
    }

    /**
     * 打印行级 diff
     */
    private function printLineDiff(string $requestValue, string $channelValue): void
    {
        $requestLines = explode("\n", $requestValue);
        $channelLines = explode("\n", $channelValue);

        $this->line('');
        $this->line('  <fg=cyan>--- 行级 diff ---</>');

        $maxLines = max(count($requestLines), count($channelLines));
        $diffLines = 0;
        $maxDiffLines = 50; // 最多显示 50 行差异
        $totalChars = 0;
        $maxTotalChars = $this->diffChars; // 单处 diff 字符限制

        for ($i = 0; $i < $maxLines && $diffLines < $maxDiffLines && $totalChars < $maxTotalChars; $i++) {
            $reqLine = $requestLines[$i] ?? null;
            $chanLine = $channelLines[$i] ?? null;

            if ($reqLine !== $chanLine) {
                $diffLines++;
                $lineNum = $i + 1;

                // 先截断行内容
                $reqLineEscaped = $this->escapeForDiff($reqLine ?? '');
                $chanLineEscaped = $this->escapeForDiff($chanLine ?? '');

                // 计算当前输出行所需的字符数（使用实际输出的长度）
                $lineOutput = '';
                if ($reqLine !== null && $chanLine !== null) {
                    $lineOutput = "@@ 行 {$lineNum} @@\n- {$reqLineEscaped}\n+ {$chanLineEscaped}";
                } elseif ($reqLine !== null) {
                    $lineOutput = "@@ 行 {$lineNum} (Channel 缺少) @@\n- {$reqLineEscaped}";
                } else {
                    $lineOutput = "@@ 行 {$lineNum} (Request 缺少) @@\n+ {$chanLineEscaped}";
                }

                // 检查是否超出字符限制
                $lineOutputLength = strlen($lineOutput);
                if ($totalChars + $lineOutputLength > $maxTotalChars) {
                    $remainingChars = $maxTotalChars - $totalChars;
                    if ($remainingChars > 30) {
                        // 截断最后一行输出
                        $truncatedLine = substr($lineOutput, 0, $remainingChars);
                        $this->line("  <fg=yellow>{$truncatedLine}...</>");
                    }
                    $this->line("  <fg=cyan>... (已达到字符上限 {$maxTotalChars}，已截断)</>");
                    break;
                }

                $totalChars += $lineOutputLength;

                if ($reqLine !== null && $chanLine !== null) {
                    // 行内容不同
                    $this->line("  <fg=yellow>@@ 行 {$lineNum} @@</>");
                    $this->line("  <fg=green>- {$reqLineEscaped}</>");
                    $this->line("  <fg=red>+ {$chanLineEscaped}</>");
                } elseif ($reqLine !== null) {
                    // Channel 缺少该行
                    $this->line("  <fg=yellow>@@ 行 {$lineNum} (Channel 缺少) @@</>");
                    $this->line("  <fg=green>- {$reqLineEscaped}</>");
                } else {
                    // Request 缺少该行
                    $this->line("  <fg=yellow>@@ 行 {$lineNum} (Request 缺少) @@</>");
                    $this->line("  <fg=red>+ {$chanLineEscaped}</>");
                }
            }
        }

        if ($diffLines >= $maxDiffLines) {
            $this->line('  <fg=cyan>... (还有更多行差异，已截断)</>');
        }

        if ($totalChars >= $maxTotalChars && $diffLines < $maxDiffLines) {
            $this->line("  <fg=cyan>... (已达到字符上限 {$maxTotalChars}，已截断)</>");
        }

        if ($diffLines === 0) {
            $this->line('  <fg=cyan>(文本内容相同，可能是编码或格式差异)</>');
        }

        $this->line('  <fg=cyan>----------------</>');
    }

    /**
     * 为 diff 输出转义和截断文本
     */
    private function escapeForDiff(string $text): string
    {
        // 先截断过长的行（最多 50 字符）
        if (strlen($text) > 50) {
            $text = substr($text, 0, 50).'...';
        }

        // 转义终端控制字符
        return str_replace(
            ["\r", "\t", "\n"],
            ['\r', '\t', '\n'],
            $text
        );
    }

    /**
     * 转义终端特殊字符
     */
    private function escape(string $text): string
    {
        // 截断过长的行
        if (strlen($text) > 150) {
            $text = substr($text, 0, 150).'... ('.(strlen($text) - 150).' more chars)';
        }

        // 转义终端控制字符
        return str_replace(
            ["\r", "\t"],
            ['\r', '\t'],
            $text
        );
    }

    /**
     * 格式化值用于显示
     */
    private function formatValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        return (string) $value;
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

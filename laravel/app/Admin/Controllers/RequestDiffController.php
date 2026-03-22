<?php

namespace App\Admin\Controllers;

use App\Models\AuditLog;
use App\Models\ChannelRequestLog;
use App\Models\RequestLog;
use Dcat\Admin\Layout\Content;
use Illuminate\Routing\Controller;

/**
 * 请求差异比对控制器
 */
class RequestDiffController extends Controller
{
    /**
     * 比对页面
     */
    public function show(int $auditLogId, Content $content): Content
    {
        $auditLog = AuditLog::findOrFail($auditLogId);
        $channelLog = ChannelRequestLog::where('audit_log_id', $auditLogId)->first();

        if (! $channelLog) {
            return $content
                ->title('请求差异比对')
                ->body('<div class="alert alert-warning">未找到渠道请求日志</div>');
        }

        // 获取请求日志
        $requestLog = null;
        if (isset($channelLog->request_log_id) && $channelLog->request_log_id) {
            $requestLog = RequestLog::find($channelLog->request_log_id);
        } else {
            $requestLog = RequestLog::where('request_id', $channelLog->request_id)->first();
        }

        if (! $requestLog) {
            return $content
                ->title('请求差异比对')
                ->body('<div class="alert alert-warning">未找到请求日志</div>');
        }

        // 解析请求体
        $requestBody = $this->parseBody($requestLog->body_text);
        $channelBody = $this->parseBody($channelLog->request_body);

        // 转换为字符串并分行
        $requestStr = is_array($requestLog->body_text)
            ? json_encode($requestLog->body_text, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            : (string) ($requestLog->body_text ?? '');
        $channelStr = is_array($channelLog->request_body)
            ? json_encode($channelLog->request_body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            : (string) ($channelLog->request_body ?? '');

        $requestLines = explode("\n", $requestStr);
        $channelLines = explode("\n", $channelStr);

        // 找出所有差异行
        $diffLines = $this->findDiffLines($requestLines, $channelLines);

        return $content
            ->title('请求差异比对 #'.$auditLogId)
            ->body(view('admin.request-diff.show', [
                'auditLog' => $auditLog,
                'requestLog' => $requestLog,
                'channelLog' => $channelLog,
                'requestBody' => $requestBody,
                'channelBody' => $channelBody,
                'requestLines' => $requestLines,
                'channelLines' => $channelLines,
                'diffLines' => $diffLines,
            ]));
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
     * 找出所有差异行的索引
     */
    private function findDiffLines(array $requestLines, array $channelLines): array
    {
        $diffLines = [];
        $maxLines = max(count($requestLines), count($channelLines));

        for ($i = 0; $i < $maxLines; $i++) {
            $reqLine = $requestLines[$i] ?? null;
            $chanLine = $channelLines[$i] ?? null;

            if ($reqLine !== $chanLine) {
                $diffLines[] = $i;
            }
        }

        return $diffLines;
    }
}

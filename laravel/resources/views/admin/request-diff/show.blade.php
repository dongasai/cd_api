<?php

use App\Models\AuditLog;
use App\Models\ChannelRequestLog;
use App\Models\RequestLog;

/** @var AuditLog $auditLog */
/** @var RequestLog $requestLog */
/** @var ChannelRequestLog $channelLog */
/** @var array $requestBody */
/** @var array $channelBody */
/** @var array $requestLines */
/** @var array $channelLines */
/** @var array $diffLines */

$totalDiffs = count($diffLines);
$requestLineCount = count($requestLines);
$channelLineCount = count($channelLines);
?>

<div class="request-diff-page">
    {{-- 基本信息表格 --}}
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">基本信息</h5>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>字段</th>
                        <th>Request Log</th>
                        <th>Channel Log</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>ID</td>
                        <td>{{ $requestLog->id }}</td>
                        <td>{{ $channelLog->id }}</td>
                    </tr>
                    <tr>
                        <td>Audit Log ID</td>
                        <td>{{ $requestLog->audit_log_id ?? 'N/A' }}</td>
                        <td>{{ $channelLog->audit_log_id }}</td>
                    </tr>
                    <tr>
                        <td>Request ID</td>
                        <td>{{ $requestLog->request_id }}</td>
                        <td>{{ $channelLog->request_id }}</td>
                    </tr>
                    <tr>
                        <td>Model</td>
                        <td>{{ $requestLog->model ?? 'N/A' }}</td>
                        <td>{{ $channelLog->metadata['model'] ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td>Path</td>
                        <td>{{ $requestLog->path ?? 'N/A' }}</td>
                        <td>{{ $channelLog->path ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td>Provider</td>
                        <td>N/A</td>
                        <td>{{ $channelLog->provider ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td>Status</td>
                        <td>N/A</td>
                        <td>{{ $channelLog->response_status ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td>Is Success</td>
                        <td>N/A</td>
                        <td>
                            @if($channelLog->is_success)
                                <span class="badge bg-success">Yes</span>
                            @else
                                <span class="badge bg-danger">No</span>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- 原始 Body 逐行比对 --}}
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">原始 Body 逐行比对</h5>
            <div>
                <span class="text-muted me-2">{{ $requestLineCount }} 行 vs {{ $channelLineCount }} 行</span>
                @if($totalDiffs > 0)
                    <span class="badge bg-warning">发现 {{ $totalDiffs }} 行差异</span>
                @else
                    <span class="badge bg-success">完全一致</span>
                @endif
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 diff-table" style="font-size: 12px;">
                    <thead>
                        <tr>
                            <th style="width: 50px;">行号</th>
                            <th style="width: 50%;">Request (-)</th>
                            <th style="width: 50%;">Channel (+)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $contextLines = 2;
                            $lastDisplayedIndex = -1;
                            $displayedDiffs = 0;
                            $maxDiffDisplay = 100;
                            $maxLines = max($requestLineCount, $channelLineCount);
                        @endphp

                        @foreach($diffLines as $diffIndex)
                            @if($displayedDiffs >= $maxDiffDisplay)
                                <tr>
                                    <td colspan="3" class="text-center text-muted">
                                        ... 还有 {{ $totalDiffs - $displayedDiffs }} 行差异未显示
                                    </td>
                                </tr>
                                @break
                            @endif

                            {{-- 检查是否需要分隔符 --}}
                            @if($lastDisplayedIndex >= 0 && $diffIndex > $lastDisplayedIndex + $contextLines * 2 + 1)
                                <tr>
                                    <td colspan="3" class="text-center text-muted">...</td>
                                </tr>
                            @endif

                            {{-- 显示上下文行（差异之前的行）--}}
                            @php
                                $contextStart = max($lastDisplayedIndex + 1, $diffIndex - $contextLines);
                            @endphp
                            @for($i = $contextStart; $i < $diffIndex; $i++)
                                <tr>
                                    <td class="text-muted">{{ $i + 1 }}</td>
                                    <td colspan="2"><code>{{ $requestLines[$i] ?? '' }}</code></td>
                                </tr>
                            @endfor

                            {{-- 显示差异行 --}}
                            @php
                                $reqLine = $requestLines[$diffIndex] ?? null;
                                $chanLine = $channelLines[$diffIndex] ?? null;
                            @endphp

                            @if($reqLine !== null)
                                <tr class="table-danger">
                                    <td class="text-muted">{{ $diffIndex + 1 }}</td>
                                    <td><code>- {{ $reqLine }}</code></td>
                                    <td></td>
                                </tr>
                            @endif

                            @if($chanLine !== null)
                                <tr class="table-success">
                                    <td class="text-muted">{{ $diffIndex + 1 }}</td>
                                    <td></td>
                                    <td><code>+ {{ $chanLine }}</code></td>
                                </tr>
                            @endif

                            {{-- 显示差异之后的上下文行 --}}
                            @php
                                $contextEnd = min($diffIndex + $contextLines, $maxLines - 1);
                            @endphp
                            @for($i = $diffIndex + 1; $i <= $contextEnd; $i++)
                                @if(!in_array($i, $diffLines))
                                    <tr>
                                        <td class="text-muted">{{ $i + 1 }}</td>
                                        <td colspan="2"><code>{{ $requestLines[$i] ?? '' }}</code></td>
                                    </tr>
                                @endif
                            @endfor

                            @php
                                $displayedDiffs++;
                                $lastDisplayedIndex = $contextEnd;
                            @endphp
                        @endforeach

                        @if($totalDiffs === 0)
                            <tr>
                                <td colspan="3" class="text-center text-success py-4">
                                    <strong>✓ 没有发现差异，请求体完全一致</strong>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Messages 对比 --}}
    @php
        $requestMessages = $requestBody['messages'] ?? [];
        $channelMessages = $channelBody['messages'] ?? [];
        $requestMsgCount = count($requestMessages);
        $channelMsgCount = count($channelMessages);
    @endphp

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Messages 对比</h5>
            <div>
                @if($requestMsgCount !== $channelMsgCount)
                    <span class="badge bg-warning me-2">数量不同</span>
                @endif
                <span class="text-muted">Request: <span class="badge bg-primary">{{ $requestMsgCount }}</span> 条 | Channel: <span class="badge bg-primary">{{ $channelMsgCount }}</span> 条</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0" style="font-size: 12px;">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Request Role</th>
                            <th>Channel Role</th>
                            <th>Request Content</th>
                            <th>Channel Content</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $maxMsgCount = max($requestMsgCount, $channelMsgCount); @endphp
                        @for($i = 0; $i < $maxMsgCount; $i++)
                            @php
                                $reqMsg = $requestMessages[$i] ?? null;
                                $chanMsg = $channelMessages[$i] ?? null;
                                $reqRole = $reqMsg['role'] ?? '-';
                                $chanRole = $chanMsg['role'] ?? '-';
                                $roleDiff = ($reqRole !== $chanRole) ? 'table-warning' : '';
                            @endphp
                            <tr>
                                <td>{{ $i }}</td>
                                <td class="{{ $roleDiff }}">{{ $reqRole }}</td>
                                <td class="{{ $roleDiff }}">{{ $chanRole }}</td>
                                <td class="{{ $roleDiff }}">
                                    @if($reqMsg)
                                        @php $content = $reqMsg['content'] ?? null; @endphp
                                        @if(is_array($content))
                                            <span class="badge bg-info">{{ count($content) }} 个内容块</span>
                                        @elseif(is_string($content) && strlen($content) > 50)
                                            <span title="{{ $content }}">{{ Str::limit($content, 50) }}</span>
                                        @else
                                            {{ $content ?? '-' }}
                                        @endif
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="{{ $roleDiff }}">
                                    @if($chanMsg)
                                        @php $content = $chanMsg['content'] ?? null; @endphp
                                        @if(is_array($content))
                                            <span class="badge bg-info">{{ count($content) }} 个内容块</span>
                                        @elseif(is_string($content) && strlen($content) > 50)
                                            <span title="{{ $content }}">{{ Str::limit($content, 50) }}</span>
                                        @else
                                            {{ $content ?? '-' }}
                                        @endif
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- 其他字段对比 --}}
    @php
        $excludeFields = ['messages'];
        $allKeys = array_unique(array_merge(array_keys($requestBody), array_keys($channelBody)));
        $filteredKeys = array_filter($allKeys, fn($k) => !in_array($k, $excludeFields));
    @endphp

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">其他字段对比</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0" style="font-size: 12px;">
                    <thead>
                        <tr>
                            <th>字段</th>
                            <th>Request</th>
                            <th>Channel</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($filteredKeys as $key)
                            @php
                                $reqValue = $requestBody[$key] ?? null;
                                $chanValue = $channelBody[$key] ?? null;
                                $reqStr = is_array($reqValue) ? json_encode($reqValue, JSON_UNESCAPED_UNICODE) : (string) ($reqValue ?? '');
                                $chanStr = is_array($chanValue) ? json_encode($chanValue, JSON_UNESCAPED_UNICODE) : (string) ($chanValue ?? '');
                                $diffClass = ($reqStr !== $chanStr) ? 'table-warning' : '';
                            @endphp
                            <tr class="{{ $diffClass }}">
                                <td><code>{{ $key }}</code></td>
                                <td>
                                    @if($reqValue === null)
                                        <span class="text-muted">不存在</span>
                                    @elseif(is_bool($reqValue))
                                        <span class="badge {{ $reqValue ? 'bg-success' : 'bg-secondary' }}">{{ $reqValue ? 'true' : 'false' }}</span>
                                    @elseif(is_array($reqValue))
                                        <code>{{ strlen(json_encode($reqValue, JSON_UNESCAPED_UNICODE)) > 100 ? Str::limit(json_encode($reqValue, JSON_UNESCAPED_UNICODE), 100).'...' : json_encode($reqValue, JSON_UNESCAPED_UNICODE) }}</code>
                                    @else
                                        <code>{{ $reqValue }}</code>
                                    @endif
                                </td>
                                <td>
                                    @if($chanValue === null)
                                        <span class="text-muted">不存在</span>
                                    @elseif(is_bool($chanValue))
                                        <span class="badge {{ $chanValue ? 'bg-success' : 'bg-secondary' }}">{{ $chanValue ? 'true' : 'false' }}</span>
                                    @elseif(is_array($chanValue))
                                        <code>{{ strlen(json_encode($chanValue, JSON_UNESCAPED_UNICODE)) > 100 ? Str::limit(json_encode($chanValue, JSON_UNESCAPED_UNICODE), 100).'...' : json_encode($chanValue, JSON_UNESCAPED_UNICODE) }}</code>
                                    @else
                                        <code>{{ $chanValue }}</code>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.diff-table td {
    font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
    white-space: pre-wrap;
    word-break: break-all;
}
.diff-table tr.table-danger td:first-child {
    border-left: 3px solid #dc3545;
}
.diff-table tr.table-success td:first-child {
    border-left: 3px solid #198754;
}
</style>
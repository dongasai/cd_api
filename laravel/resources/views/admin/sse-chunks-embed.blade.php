<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSE Chunks 预览 - {{ $fieldLabel }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
            padding: 15px;
        }

        .container {
            max-width: 100%;
        }

        .summary {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 12px;
            font-size: 13px;
        }

        .summary strong {
            color: #333;
        }

        .chunks-list {
            background: #282c34;
            color: #abb2bf;
            padding: 15px;
            border-radius: 4px;
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
            font-size: 12px;
            max-height: 800px;
            overflow-y: auto;
        }

        .chunk-item {
            margin-bottom: 10px;
            padding: 10px;
            background: #2c323c;
            border-radius: 4px;
            border-left: 3px solid #61afef;
        }

        .chunk-number {
            color: #61afef;
            margin-bottom: 6px;
            font-weight: bold;
        }

        .event-type {
            color: #98c379;
            margin-bottom: 4px;
        }

        .data-content {
            color: #e5c07b;
            word-break: break-all;
        }

        .raw-content {
            color: #abb2bf;
            word-break: break-all;
            white-space: pre-wrap;
        }

        .truncated {
            color: #5c6370;
            font-style: italic;
        }

        .empty-message {
            text-align: center;
            color: #999;
            padding: 40px;
        }
    </style>
</head>
<body>
    <div class="container">
        @if($totalChunks > 0)
            {{-- 统计信息 --}}
            <div class="summary">
                <strong>总块数:</strong> {{ $totalChunks }}

                @if(!empty($eventTypes))
                    &nbsp;|&nbsp; <strong>事件类型:</strong>
                    @foreach($eventTypes as $type => $count)
                        {{ $type }}({{ $count }}){{ !$loop->last ? ', ' : '' }}
                    @endforeach
                @endif
            </div>

            {{-- 块列表 --}}
            <div class="chunks-list">
                @foreach($chunks as $index => $chunkData)
                    <div class="chunk-item">
                        <div class="chunk-number">#{{ $index + 1 }}</div>

                        @if($chunkData['parsed'])
                            @if(isset($chunkData['parsed']['event']))
                                <div class="event-type">event: {{ $chunkData['parsed']['event'] }}</div>
                            @endif
                            @if(isset($chunkData['parsed']['data']))
                                @php
                                    $data = $chunkData['parsed']['data'];
                                    if (is_string($data)) {
                                        $jsonStr = htmlspecialchars($data);
                                    } else {
                                        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                        if (strlen($json) > 300) {
                                            $jsonStr = htmlspecialchars(substr($json, 0, 300)) . ' <span class="truncated">...</span>';
                                        } else {
                                            $jsonStr = htmlspecialchars($json);
                                        }
                                    }
                                @endphp
                                <div class="data-content">data: {!! $jsonStr !!}</div>
                            @endif
                        @else
                            <div class="raw-content">{{ htmlspecialchars($chunkData['raw']) }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty-message">
                暂无数据
            </div>
        @endif
    </div>
</body>
</html>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>消息列表预览 - {{ $fieldLabel }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f0f2f5;
            padding: 15px;
        }

        .container {
            max-width: 100%;
        }

        .field-label {
            background: #f8f9fa;
            padding: 10px 12px;
            border-radius: 4px;
            margin-bottom: 12px;
            font-size: 13px;
            color: #666;
        }

        .messages-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .message-item {
            display: flex;
            gap: 10px;
            max-width: 90%;
        }

        /* 用户消息靠右 */
        .message-item.user {
            margin-left: auto;
            flex-direction: row-reverse;
        }

        /* AI消息靠左 */
        .message-item.assistant {
            margin-right: auto;
        }

        .message-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: bold;
            flex-shrink: 0;
        }

        /* AI头像 */
        .message-item.assistant .message-avatar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        /* 用户头像 */
        .message-item.user .message-avatar {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }

        /* 其他角色头像 */
        .message-item.system .message-avatar,
        .message-item.developer .message-avatar {
            background: #6c757d;
            color: white;
        }

        .message-body {
            flex: 1;
            min-width: 0;
        }

        .message-content {
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 14px;
            line-height: 1.5;
            word-break: break-word;
            white-space: pre-wrap;
        }

        /* AI消息气泡 */
        .message-item.assistant .message-content {
            background: white;
            color: #333;
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        /* 用户消息气泡 */
        .message-item.user .message-content {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom-right-radius: 4px;
        }

        /* 系统消息气泡 */
        .message-item.system .message-content,
        .message-item.developer .message-content {
            background: #e9ecef;
            color: #495057;
        }

        .message-role {
            font-size: 11px;
            color: #999;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .message-item.user .message-role {
            text-align: right;
            color: rgba(255, 255, 255, 0.7);
        }

        .empty-message {
            text-align: center;
            color: #999;
            padding: 40px;
            background: white;
            border-radius: 8px;
        }

        /* 系统消息和开发者消息居中显示 */
        .message-item.system,
        .message-item.developer {
            margin-left: auto;
            margin-right: auto;
            max-width: 100%;
        }

        .message-item.system .message-content,
        .message-item.developer .message-content {
            border-radius: 8px;
            text-align: center;
            font-style: italic;
        }

        /* 工具调用样式 */
        .tool-use {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-left: 3px solid #667eea;
            border-radius: 6px;
            margin: 8px 0;
            padding: 10px 12px;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 12px;
        }

        .tool-use-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            color: #667eea;
            font-weight: 600;
        }

        .tool-use-header .tool-icon {
            font-size: 14px;
        }

        .tool-use-name {
            color: #495057;
        }

        .tool-use-id {
            color: #999;
            font-size: 10px;
            margin-left: auto;
        }

        .tool-use-input {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 8px;
            margin-top: 8px;
            overflow-x: auto;
        }

        .tool-use-input pre {
            margin: 0;
            white-space: pre-wrap;
            word-break: break-word;
            color: #495057;
        }

        /* 工具结果样式 */
        .tool-result {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-left: 3px solid #ffc107;
            border-radius: 6px;
            margin: 8px 0;
            padding: 10px 12px;
            font-size: 13px;
        }

        .tool-result-header {
            color: #856404;
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 11px;
        }

        /* 内容块容器 */
        .content-blocks {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        /* 文本内容 */
        .content-text {
            white-space: pre-wrap;
            word-break: break-word;
        }
    </style>
</head>
<body>
    <div class="container">
        @if(!empty($messages))
            <div class="field-label">
                <strong>{{ $fieldLabel }}</strong> - 共 {{ count($messages) }} 条消息
            </div>

            <div class="messages-list">
                @foreach($messages as $message)
                    <div class="message-item {{ $message['role'] }}">
                        <div class="message-avatar">
                            @if($message['isAssistant'])
                                AI
                            @elseif($message['isUser'])
                                U
                            @elseif($message['role'] === 'system')
                                S
                            @elseif($message['role'] === 'developer')
                                D
                            @else
                                ?
                            @endif
                        </div>
                        <div class="message-body">
                            @if(!$message['isUser'] && !$message['isAssistant'])
                                <div class="message-role">{{ $message['role'] }}</div>
                            @endif
                            <div class="message-content">
                                @if(is_array($message['content']))
                                    <div class="content-blocks">
                                        @foreach($message['content'] as $block)
                                            @if($block['type'] === 'text')
                                                <div class="content-text">{{ $block['text'] }}</div>
                                            @elseif($block['type'] === 'tool_use')
                                                <div class="tool-use">
                                                    <div class="tool-use-header">
                                                        <span class="tool-icon">🔧</span>
                                                        <span>工具调用:</span>
                                                        <span class="tool-use-name">{{ $block['name'] }}</span>
                                                        @if(!empty($block['id']))
                                                            <span class="tool-use-id">{{ $block['id'] }}</span>
                                                        @endif
                                                    </div>
                                                    @if(!empty($block['input']))
                                                        <div class="tool-use-input">
                                                            <pre>{{ json_encode($block['input'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                        </div>
                                                    @endif
                                                </div>
                                            @elseif($block['type'] === 'tool_result')
                                                <div class="tool-result">
                                                    <div class="tool-result-header">📤 工具结果</div>
                                                    <div class="content-text">{{ $block['text'] }}</div>
                                                </div>
                                            @elseif($block['type'] === 'image')
                                                <div style="color: #667eea;">📷 {{ $block['text'] }}</div>
                                            @else
                                                <div class="content-text">{{ $block['text'] ?? json_encode($block, JSON_UNESCAPED_UNICODE) }}</div>
                                            @endif
                                        @endforeach
                                    </div>
                                @else
                                    {{ $message['content'] }}
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty-message">
                暂无消息数据
            </div>
        @endif
    </div>
</body>
</html>
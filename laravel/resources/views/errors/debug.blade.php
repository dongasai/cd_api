@php
    $request = request();
    $trace = $exception->getTrace();
    
    // 清理异常消息中的重复 View 路径
    function cleanMessage($msg) {
        if (empty($msg)) return 'Unknown Error';
        $msg = preg_replace('/\s*\(View: [^)]+\)/', '', $msg);
        $msg = preg_replace('/\s+/', ' ', $msg);
        return trim($msg);
    }
    
    $message = cleanMessage($exception->getMessage());
    
    // 处理 Previous Exception
    $previous = $exception->getPrevious();
    $previousData = null;
    if ($previous) {
        $previousData = [
            'class' => get_class($previous),
            'message' => cleanMessage($previous->getMessage()),
            'file' => $previous->getFile(),
            'line' => $previous->getLine(),
        ];
    }
@endphp
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $message }} - CdApi Debug</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #1a1a2e; color: #eee; min-height: 100vh; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #e94560 0%, #ff6b6b 100%); padding: 30px 20px; margin-bottom: 20px; }
        .header h1 { font-size: 1.5rem; font-weight: 600; margin-bottom: 10px; word-break: break-word; }
        .header .meta { font-size: 0.875rem; opacity: 0.9; }
        .exception-class { background: #16213e; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; font-family: "Fira Code", "JetBrains Mono", Menlo, Monaco, Consolas, monospace; font-size: 0.875rem; border-left: 4px solid #e94560; }
        .section { background: #16213e; border-radius: 8px; margin-bottom: 20px; overflow: hidden; }
        .section-header { background: #0f3460; padding: 12px 20px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: space-between; }
        .section-header:hover { background: #1a4a7a; }
        .section-header .icon { transition: transform 0.2s; }
        .section-header.collapsed .icon { transform: rotate(-90deg); }
        .section-content { padding: 15px 20px; max-height: 500px; overflow-y: auto; }
        .section-content.collapsed { display: none; }
        .stack-trace { font-family: "Fira Code", "JetBrains Mono", Menlo, Monaco, Consolas, monospace; font-size: 0.8rem; }
        .stack-frame { padding: 10px 0; border-bottom: 1px solid #2a2a4a; }
        .stack-frame:last-child { border-bottom: none; }
        .stack-frame-header { display: flex; align-items: center; gap: 10px; margin-bottom: 5px; }
        .stack-frame-number { background: #e94560; color: #fff; width: 24px; height: 24px; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 600; flex-shrink: 0; }
        .stack-frame-class { color: #4fc3f7; }
        .stack-frame-method { color: #81c784; }
        .stack-frame-file { color: #ffb74d; font-size: 0.75rem; cursor: pointer; }
        .stack-frame-file:hover { text-decoration: underline; }
        .stack-frame-line { color: #ce93d8; }
        .code-preview { background: #0d1117; border-radius: 6px; margin-top: 10px; overflow: hidden; display: none; }
        .code-preview.show { display: block; }
        .code-preview-header { background: #161b22; padding: 8px 12px; font-size: 0.75rem; color: #8b949e; border-bottom: 1px solid #30363d; }
        .code-preview-content { padding: 10px; font-family: "Fira Code", monospace; font-size: 0.8rem; line-height: 1.5; overflow-x: auto; }
        .code-line { display: flex; }
        .code-line-num { width: 50px; text-align: right; padding-right: 15px; color: #484f58; user-select: none; }
        .code-line-num.highlight { color: #e94560; font-weight: bold; }
        .code-line-content { flex: 1; color: #c9d1d9; white-space: pre; }
        .code-line.highlight { background: rgba(233, 69, 96, 0.15); }
        .table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        .table th, .table td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #2a2a4a; }
        .table th { background: #0f3460; font-weight: 600; width: 200px; }
        .table td { font-family: "Fira Code", monospace; font-size: 0.8rem; word-break: break-all; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
        .badge-get { background: #4caf50; }
        .badge-post { background: #2196f3; }
        .badge-put { background: #ff9800; }
        .badge-delete { background: #f44336; }
        .badge-patch { background: #9c27b0; }
        .code-block { background: #0d1117; padding: 15px; border-radius: 6px; font-family: "Fira Code", monospace; font-size: 0.8rem; overflow-x: auto; white-space: pre-wrap; word-break: break-all; }
        .env-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 10px; }
        .env-item { background: #0d1117; padding: 10px 15px; border-radius: 6px; font-family: monospace; font-size: 0.8rem; }
        .env-item .key { color: #4fc3f7; }
        .env-item .value { color: #81c784; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 0.875rem; }
        .copy-btn { background: #4fc3f7; color: #1a1a2e; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.875rem; transition: all 0.2s; }
        .copy-btn:hover { background: #81d4fa; }
        .copy-btn:active { transform: scale(0.95); }
        .copy-btn.copied { background: #4caf50; color: #fff; }
        .copy-btn-small { padding: 4px 10px; font-size: 0.75rem; }
        .header-actions { display: flex; gap: 10px; margin-top: 15px; }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .container { padding: 10px; }
            .header { padding: 20px 15px; }
            .header h1 { font-size: 1.1rem; }
            .header .meta { font-size: 0.75rem; }
            .section-header { padding: 10px 15px; font-size: 0.875rem; }
            .section-content { padding: 10px 15px; }
            .stack-frame-header { flex-wrap: wrap; }
            .stack-frame-number { width: 20px; height: 20px; font-size: 0.7rem; }
            .stack-frame-file { font-size: 0.7rem; word-break: break-all; }
            .table th, .table td { padding: 6px 8px; font-size: 0.8rem; }
            .table th { width: 120px; }
            .env-grid { grid-template-columns: 1fr; }
            .copy-btn { padding: 6px 12px; font-size: 0.8rem; }
            .code-line-num { width: 40px; padding-right: 10px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>{{ $message }}</h1>
            <div class="meta">
                <strong>{{ get_class($exception) }}</strong> in
                <span>{{ $exception->getFile() }}</span> on line
                <span>{{ $exception->getLine() }}</span>
            </div>
            <div class="header-actions">
                <button class="copy-btn" onclick="copyToMd()">📋 CopyToMd</button>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="exception-class">
            <div style="color: #e94560; margin-bottom: 5px;">Exception Class:</div>
            <div>{{ get_class($exception) }}</div>
        </div>

        <div class="section">
            <div class="section-header" onclick="toggleSection(this)">
                <span>Stack Trace ({{ count($trace) }} frames)</span>
                <span style="display: flex; align-items: center; gap: 10px;">
                    <button class="copy-btn copy-btn-small" onclick="event.stopPropagation(); copyStackTrace()">📋 Copy</button>
                    <span class="icon">▼</span>
                </span>
            </div>
            <div class="section-content">
                <div class="stack-trace">
                    @foreach($trace as $i => $frame)
                        @php
                            $hasCode = isset($frame['file']) && is_file($frame['file']);
                            $fileId = 'frame-' . $i;
                        @endphp
                        <div class="stack-frame">
                            <div class="stack-frame-header">
                                <span class="stack-frame-number">{{ $i }}</span>
                                <span>
                                    @if(isset($frame['class']))
                                        <span class="stack-frame-class">{{ $frame['class'] }}</span>
                                        <span style="color: #888;">{{ $frame['type'] ?? '->' }}</span>
                                    @endif
                                    <span class="stack-frame-method">{{ $frame['function'] ?? 'unknown' }}()</span>
                                </span>
                            </div>
                            @if(isset($frame['file']))
                                <div class="stack-frame-file" @if($hasCode) onclick="toggleCode('{{ $fileId }}', '{{ addslashes($frame['file']) }}', {{ $frame['line'] ?? 1 }})" @endif>
                                    {{ $frame['file'] }}:<span class="stack-frame-line">{{ $frame['line'] ?? '?' }}</span>
                                    @if($hasCode) <span style="color: #4fc3f7; margin-left: 5px;">[查看代码]</span> @endif
                                </div>
                                @if($hasCode)
                                <div id="{{ $fileId }}" class="code-preview">
                                    <div class="code-preview-header">Loading...</div>
                                    <div class="code-preview-content"></div>
                                </div>
                                @endif
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-header" onclick="toggleSection(this)">
                <span>Request Information</span>
                <span class="icon">▼</span>
            </div>
            <div class="section-content">
                <table class="table">
                    <tr>
                        <th>URL</th>
                        <td>{{ $request->fullUrl() }}</td>
                    </tr>
                    <tr>
                        <th>Method</th>
                        <td>
                            <span class="badge badge-{{ strtolower($request->method()) }}">
                                {{ $request->method() }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Route</th>
                        <td>{{ $request->route()?->getName() ?? $request->path() }}</td>
                    </tr>
                    <tr>
                        <th>Controller</th>
                        <td>{{ $request->route()?->getControllerClass() ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <th>IP Address</th>
                        <td>{{ $request->ip() }}</td>
                    </tr>
                    <tr>
                        <th>User Agent</th>
                        <td>{{ $request->userAgent() }}</td>
                    </tr>
                </table>
            </div>
        </div>

        @if($request->input())
        <div class="section">
            <div class="section-header collapsed" onclick="toggleSection(this)">
                <span>Request Input</span>
                <span class="icon">▼</span>
            </div>
            <div class="section-content collapsed">
                <div class="code-block">{{ json_encode($request->input(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</div>
            </div>
        </div>
        @endif

        <div class="section">
            <div class="section-header collapsed" onclick="toggleSection(this)">
                <span>Request Headers</span>
                <span class="icon">▼</span>
            </div>
            <div class="section-content collapsed">
                <table class="table">
                    @foreach($request->headers->all() as $key => $values)
                        <tr>
                            <th>{{ $key }}</th>
                            <td>{{ implode(', ', $values) }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>

        <div class="section">
            <div class="section-header collapsed" onclick="toggleSection(this)">
                <span>Server Environment</span>
                <span class="icon">▼</span>
            </div>
            <div class="section-content collapsed">
                <div class="env-grid">
                    @php
                        $envKeys = ['APP_ENV', 'APP_DEBUG', 'APP_URL', 'DB_CONNECTION', 'DB_HOST', 'DB_DATABASE'];
                    @endphp
                    @foreach($envKeys as $key)
                        <div class="env-item">
                            <span class="key">{{ $key }}:</span>
                            <span class="value">{{ env($key, 'N/A') }}</span>
                        </div>
                    @endforeach
                    <div class="env-item">
                        <span class="key">PHP_VERSION:</span>
                        <span class="value">{{ PHP_VERSION }}</span>
                    </div>
                    <div class="env-item">
                        <span class="key">LARAVEL_VERSION:</span>
                        <span class="value">{{ app()->version() }}</span>
                    </div>
                </div>
            </div>
        </div>

        @if($previousData)
        <div class="section">
            <div class="section-header collapsed" onclick="toggleSection(this)">
                <span>Previous Exception</span>
                <span class="icon">▼</span>
            </div>
            <div class="section-content collapsed">
                <div class="exception-class">
                    <div style="color: #e94560; margin-bottom: 5px;">{{ $previousData['class'] }}</div>
                    <div>{{ $previousData['message'] }}</div>
                    <div style="margin-top: 10px; font-size: 0.75rem; color: #888;">
                        {{ $previousData['file'] }}:{{ $previousData['line'] }}
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    <div class="footer">
        <p>CdApi Debug | Laravel {{ app()->version() }} | PHP {{ PHP_VERSION }}</p>
    </div>

    <script>
        function toggleSection(header) {
            header.classList.toggle('collapsed');
            const content = header.nextElementSibling;
            content.classList.toggle('collapsed');
        }

        const codeCache = {};

        async function toggleCode(elementId, filePath, lineNum) {
            const preview = document.getElementById(elementId);
            const isShowing = preview.classList.contains('show');
            
            if (isShowing) {
                preview.classList.remove('show');
                return;
            }

            const header = preview.querySelector('.code-preview-header');
            const content = preview.querySelector('.code-preview-content');
            
            preview.classList.add('show');
            header.textContent = filePath;

            if (codeCache[filePath]) {
                renderCode(content, codeCache[filePath], lineNum);
                return;
            }

            try {
                const response = await fetch(`/debug/file?path=${encodeURIComponent(filePath)}`);
                if (!response.ok) throw new Error('Failed to load file');
                const data = await response.json();
                codeCache[filePath] = data.lines;
                renderCode(content, data.lines, lineNum);
            } catch (err) {
                content.innerHTML = `<div style="color: #e94560;">Error loading file: ${err.message}</div>`;
            }
        }

        function renderCode(container, lines, highlightLine) {
            const startLine = Math.max(0, highlightLine - 6);
            const endLine = Math.min(lines.length, highlightLine + 5);
            
            let html = '';
            for (let i = startLine; i < endLine; i++) {
                const isHighlight = i + 1 === highlightLine;
                const lineContent = escapeHtml(lines[i]);
                html += `<div class="code-line ${isHighlight ? 'highlight' : ''}">
                    <span class="code-line-num ${isHighlight ? 'highlight' : ''}">${i + 1}</span>
                    <span class="code-line-content">${lineContent || ' '}</span>
                </div>`;
            }
            container.innerHTML = html;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function copyToMd() {
            const btn = event.target;
            const mdContent = generateMdContent();
            
            function showSuccess() {
                btn.textContent = '✓ Copied!';
                btn.classList.add('copied');
                setTimeout(() => {
                    btn.textContent = '📋 CopyToMd';
                    btn.classList.remove('copied');
                }, 2000);
            }

            function showError() {
                alert('Failed to copy to clipboard');
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(mdContent).then(showSuccess).catch(showError);
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = mdContent;
                textarea.style.position = 'fixed';
                textarea.style.left = '-9999px';
                document.body.appendChild(textarea);
                textarea.select();
                try {
                    document.execCommand('copy');
                    showSuccess();
                } catch (err) {
                    showError();
                }
                document.body.removeChild(textarea);
            }
        }

        function generateMdContent() {
            const exceptionClass = @json(get_class($exception));
            let exceptionMessage = @json($message);
            const exceptionFile = @json($exception->getFile());
            const exceptionLine = @json($exception->getLine());
            const request = {
                fullUrl: @json(request()->fullUrl()),
                method: @json(request()->method()),
                ip: @json(request()->ip()),
                userAgent: @json(request()->userAgent()),
                query: @json(request()->query->all()),
                input: @json(request()->input())
            };
            const trace = @json($exception->getTrace());

            let md = `# Error Report\n\n`;
            md += `## Exception\n\n`;
            md += `**Class:** \`${exceptionClass}\`\n\n`;
            md += `**Message:** ${exceptionMessage}\n\n`;
            md += `**Location:** \`${exceptionFile}:${exceptionLine}\`\n\n`;

            md += `## Request\n\n`;
            md += `| Property | Value |\n`;
            md += `|----------|-------|\n`;
            md += `| URL | ${request.fullUrl}\n`;
            md += `| Method | ${request.method}\n`;
            md += `| IP | ${request.ip}\n`;
            md += `| User Agent | ${request.userAgent}\n\n`;

            if (request.query && Object.keys(request.query).length > 0) {
                md += `### Query Parameters\n\n`;
                md += `\`\`\`json\n${JSON.stringify(request.query, null, 2)}\n\`\`\`\n\n`;
            }

            if (request.input && Object.keys(request.input).length > 0) {
                md += `### Request Input\n\n`;
                md += `\`\`\`json\n${JSON.stringify(request.input, null, 2)}\n\`\`\`\n\n`;
            }

            md += `## Stack Trace\n\n`;
            md += `\`\`\`\n`;
            const maxFrames = 20;
            const framesToInclude = trace.slice(0, maxFrames);
            framesToInclude.forEach((frame, i) => {
                const className = frame.class || '';
                const type = frame.type || '->';
                const func = frame.function || 'unknown';
                const file = frame.file || '';
                const line = frame.line || '?';
                if (className) {
                    md += `#${i} ${className}${type}${func}()\n`;
                } else {
                    md += `#${i} ${func}()\n`;
                }
                if (file) {
                    md += `    at ${file}:${line}\n`;
                }
            });
            if (trace.length > maxFrames) {
                md += `\n... and ${trace.length - maxFrames} more frames\n`;
            }
            md += `\`\`\`\n\n`;

            md += `## Environment\n\n`;
            md += `| Key | Value |\n`;
            md += `|-----|-------|\n`;
            md += `| PHP Version | {{ PHP_VERSION }}\n`;
            md += `| Laravel Version | {{ app()->version() }}\n`;
            md += `| APP_ENV | {{ env('APP_ENV', 'N/A') }}\n`;
            md += `| APP_DEBUG | {{ env('APP_DEBUG', 'N/A') }}\n`;

            @if($previousData)
            const previousData = @json($previousData);
            md += `\n## Previous Exception\n\n`;
            md += `**Class:** \`${previousData.class}\`\n\n`;
            md += `**Message:** ${previousData.message}\n\n`;
            md += `**Location:** \`${previousData.file}:${previousData.line}\`\n`;
            @endif

            md += `\n---\n`;
            md += `*Generated by CdApi Debug at {{ now()->toIso8601String() }}*\n`;

            return md;
        }

        function copyStackTrace() {
            const btn = event.target;
            const trace = @json($exception->getTrace());
            const maxFrames = 20;
            const framesToInclude = trace.slice(0, maxFrames);
            
            let content = `\`\`\`\n`;
            framesToInclude.forEach((frame, i) => {
                const className = frame.class || '';
                const type = frame.type || '->';
                const func = frame.function || 'unknown';
                const file = frame.file || '';
                const line = frame.line || '?';
                if (className) {
                    content += `#${i} ${className}${type}${func}()\n`;
                } else {
                    content += `#${i} ${func}()\n`;
                }
                if (file) {
                    content += `    at ${file}:${line}\n`;
                }
            });
            if (trace.length > maxFrames) {
                content += `\n... and ${trace.length - maxFrames} more frames\n`;
            }
            content += `\`\`\`\n`;

            function showSuccess() {
                const originalText = btn.textContent;
                btn.textContent = '✓';
                btn.classList.add('copied');
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.classList.remove('copied');
                }, 1500);
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(content).then(showSuccess);
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = content;
                textarea.style.position = 'fixed';
                textarea.style.left = '-9999px';
                document.body.appendChild(textarea);
                textarea.select();
                try {
                    document.execCommand('copy');
                    showSuccess();
                } catch (err) {
                    alert('Failed to copy');
                }
                document.body.removeChild(textarea);
            }
        }
    </script>
</body>
</html>

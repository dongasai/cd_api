<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $fieldLabel }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 14px;
            background: #f8f9fa;
        }

        .toolbar {
            padding: 8px 12px;
            background: #fff;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 4px 10px;
            font-size: 12px;
            border: 1px solid #dee2e6;
            background: #fff;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #333;
        }

        .btn:hover {
            background: #f8f9fa;
        }

        .btn-primary {
            background: #007bff;
            border-color: #007bff;
            color: #fff;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .json-container {
            font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
            font-size: 13px;
            line-height: 1.6;
            color: #2d3748;
            padding: 12px;
            background: #fff;
        }

        .json-item {
            margin: 2px 0;
            padding-left: 20px;
            position: relative;
        }

        .json-item.collapsed > .json-item {
            display: none;
        }

        .json-toggle {
            position: absolute;
            left: 0;
            cursor: pointer;
            width: 16px;
            height: 16px;
            line-height: 16px;
            text-align: center;
            color: #666;
            font-size: 10px;
            font-family: monospace;
            user-select: none;
        }

        .json-toggle:hover {
            color: #000;
        }

        .json-toggle::before {
            content: '▼';
            display: inline-block;
            transform: rotate(0deg);
            transition: transform 0.1s;
        }

        .json-toggle.collapsed::before {
            transform: rotate(-90deg);
        }

        .json-key {
            color: #005cc5;
        }

        .json-string {
            color: #22863a;
        }

        .json-number {
            color: #005cc5;
        }

        .json-boolean {
            color: #d73a49;
        }

        .json-null {
            color: #6f42c1;
        }

        .json-bracket {
            color: #2d3748;
        }

        .json-count {
            color: #999;
            font-size: 11px;
            margin-left: 4px;
        }

        .json-comma {
            color: #2d3748;
        }

        /* 滚动条样式 */
        body::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        body::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        body::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        body::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="btn" onclick="expandAll()">
            <i>▼</i> 全部展开
        </button>
        <button type="button" class="btn" onclick="collapseAll()">
            <i>▶</i> 全部折叠
        </button>
        <button type="button" class="btn btn-primary" onclick="copyToClipboard()">
            <i>📋</i> 复制
        </button>
    </div>
    <div id="json-container" class="json-container"></div>

    <script>
        // 存储原始JSON数据
        let originalJson = {!! json_encode($originalData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!};

        // 渲染JSON树
        function renderJson(data, depth = 0) {
            if (data === null) {
                return '<span class="json-null">null</span>';
            }

            if (typeof data === 'boolean') {
                return '<span class="json-boolean">' + data + '</span>';
            }

            if (typeof data === 'number') {
                return '<span class="json-number">' + data + '</span>';
            }

            if (typeof data === 'string') {
                const escaped = data.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/\n/g, '\\n').replace(/\r/g, '\\r').replace(/\t/g, '\\t');
                return '<span class="json-string">"' + escaped + '"</span>';
            }

            if (Array.isArray(data)) {
                if (data.length === 0) {
                    return '<span class="json-bracket">[]</span>';
                }

                let html = '<span class="json-bracket">[</span><span class="json-count">' + data.length + ' items</span>';
                html += '<div class="json-item">';

                data.forEach((item, index) => {
                    const canCollapse = typeof item === 'object' && item !== null;
                    const toggleHtml = canCollapse ? '<span class="json-toggle" onclick="toggleItem(this)"></span>' : '';

                    html += '<div class="json-item' + (canCollapse ? '' : ' collapsed') + '">' + toggleHtml +
                            renderJson(item, depth + 1) +
                            (index < data.length - 1 ? '<span class="json-comma">,</span>' : '') +
                            '</div>';
                });

                html += '</div><span class="json-bracket">]</span>';
                return html;
            }

            if (typeof data === 'object') {
                const keys = Object.keys(data);
                if (keys.length === 0) {
                    return '<span class="json-bracket">{}</span>';
                }

                let html = '<span class="json-bracket">{</span><span class="json-count">' + keys.length + ' keys</span>';
                html += '<div class="json-item">';

                keys.forEach((key, index) => {
                    const value = data[key];
                    const canCollapse = typeof value === 'object' && value !== null;
                    const toggleHtml = canCollapse ? '<span class="json-toggle" onclick="toggleItem(this)"></span>' : '';

                    html += '<div class="json-item' + (canCollapse ? '' : ' collapsed') + '">' + toggleHtml +
                            '<span class="json-key">"' + key + '"</span>: ' +
                            renderJson(value, depth + 1) +
                            (index < keys.length - 1 ? '<span class="json-comma">,</span>' : '') +
                            '</div>';
                });

                html += '</div><span class="json-bracket">}</span>';
                return html;
            }

            return String(data);
        }

        // 切换折叠状态
        function toggleItem(element) {
            const parent = element.parentElement;
            parent.classList.toggle('collapsed');
            element.classList.toggle('collapsed');
        }

        // 全部展开
        function expandAll() {
            document.querySelectorAll('#json-container .json-item').forEach(item => {
                item.classList.remove('collapsed');
            });
            document.querySelectorAll('#json-container .json-toggle').forEach(toggle => {
                toggle.classList.remove('collapsed');
            });
        }

        // 全部折叠
        function collapseAll() {
            document.querySelectorAll('#json-container .json-item').forEach(item => {
                if (item.querySelector('.json-toggle')) {
                    item.classList.add('collapsed');
                }
            });
            document.querySelectorAll('#json-container .json-toggle').forEach(toggle => {
                toggle.classList.add('collapsed');
            });
        }

        // 复制到剪贴板
        function copyToClipboard() {
            try {
                const text = JSON.stringify(originalJson, null, 2);

                // 使用 textarea 降级方案（更可靠，特别是 iframe 环境）
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.left = '-9999px';
                textarea.style.top = '0';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();

                const successful = document.execCommand('copy');
                document.body.removeChild(textarea);

                if (successful) {
                    showCopySuccess();
                } else {
                    // 如果 execCommand 失败，尝试 clipboard API
                    navigator.clipboard.writeText(text).then(function() {
                        showCopySuccess();
                    }).catch(function(err) {
                        console.error('复制失败:', err);
                        alert('复制失败，请手动复制');
                    });
                }
            } catch (err) {
                console.error('复制出错:', err);
                alert('复制出错: ' + err.message);
            }
        }

        // 显示复制成功提示
        function showCopySuccess() {
            const btn = event.target.closest('.btn');
            if (btn) {
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i>✓</i> 已复制';
                btn.style.background = '#28a745';
                btn.style.borderColor = '#28a745';
                btn.style.color = '#fff';
                setTimeout(function() {
                    btn.innerHTML = originalText;
                    btn.style.background = '';
                    btn.style.borderColor = '';
                    btn.style.color = '';
                }, 2000);
            }
        }

        // 初始化渲染
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('json-container');
            try {
                const jsonData = typeof originalJson === 'string' ? JSON.parse(originalJson) : originalJson;
                container.innerHTML = renderJson(jsonData);
            } catch (e) {
                container.innerHTML = '<pre style="margin: 0;">' + originalJson + '</pre>';
            }
        });
    </script>
</body>
</html>
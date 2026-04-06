<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CdApi 系统升级</title>
    <style>
        :root {
            --primary: #3498db;
            --success: #27ae60;
            --error: #e74c3c;
            --warning: #f39c12;
            --text: #333;
            --text-light: #666;
            --bg: #f5f5f5;
            --card-bg: #fff;
            --border: #ddd;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .header {
            background: var(--card-bg);
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid var(--border);
        }

        .header h1 {
            font-size: 24px;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .card {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .card-title {
            font-size: 20px;
            margin-bottom: 20px;
            color: var(--text);
        }

        .version-info {
            padding: 12px;
            background: var(--bg);
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .version-info label {
            font-weight: 500;
            margin-right: 8px;
        }

        .migration-list {
            margin-bottom: 20px;
        }

        .migration-item {
            padding: 12px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
        }

        .migration-item:last-child {
            border-bottom: none;
        }

        .migration-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--warning);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            color: white;
            font-size: 12px;
        }

        .migration-name {
            flex: 1;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-secondary {
            background: var(--border);
            color: var(--text);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .log-output {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 16px;
            border-radius: 4px;
            font-family: "Courier New", monospace;
            font-size: 12px;
            max-height: 300px;
            overflow-y: auto;
            white-space: pre-wrap;
        }

        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid var(--border);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .message {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .message-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .message-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .footer {
            text-align: center;
            padding: 20px;
            color: var(--text-light);
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>CdApi 系统升级</h1>
        <p>数据库迁移升级</p>
    </div>

    <div class="content">
        <div class="card">
            <h2 class="card-title">系统升级</h2>

            <div class="version-info">
                <label>当前版本:</label>
                <span>{{ $currentVersion }}</span>
            </div>

            @if(count($pendingMigrations) > 0)
            <div class="message message-warning">
                有 {{ count($pendingMigrations) }} 个待执行的数据库迁移
            </div>

            <div class="migration-list">
                <h3 style="margin-bottom: 10px;">待执行的迁移:</h3>
                @foreach($pendingMigrations as $migration)
                <div class="migration-item">
                    <div class="migration-icon">!</div>
                    <div class="migration-name">{{ $migration['name'] }}</div>
                </div>
                @endforeach
            </div>

            <div class="btn-group">
                <button class="btn btn-primary" onclick="executeUpgrade()">执行升级</button>
                <a href="{{ url('/admin') }}" class="btn btn-secondary">返回后台</a>
            </div>

            <div id="upgrade-result" style="margin-top: 20px;"></div>
            @else
            <div class="message message-success">
                没有待执行的迁移，系统已是最新版本
            </div>

            <div class="btn-group">
                <a href="{{ url('/admin') }}" class="btn btn-primary">返回后台</a>
            </div>
            @endif
        </div>
    </div>

    <div class="footer">
        CdApi &copy; 2024
    </div>

    <script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function fetchAPI(url, method = 'GET', data = null) {
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        };

        if (data && method !== 'GET') {
            options.body = JSON.stringify(data);
        }

        return fetch(url, options).then(res => res.json());
    }

    function executeUpgrade() {
        const resultDiv = document.getElementById('upgrade-result');
        resultDiv.innerHTML = '<div class="loading"><div class="spinner"></div><p>正在执行迁移...</p></div>';

        fetchAPI('{{ route("upgrade.execute") }}', 'POST')
            .then(response => {
                if (response.success) {
                    resultDiv.innerHTML = '<div class="message message-success">' + response.message + '</div>';
                    resultDiv.innerHTML += '<div class="log-output">' + (response.output || '执行完成') + '</div>';
                    resultDiv.innerHTML += '<div class="btn-group" style="margin-top: 20px;"><a href="{{ url("/admin") }}" class="btn btn-primary">返回后台</a></div>';
                } else {
                    resultDiv.innerHTML = '<div class="message message-error">' + response.message + '</div>';
                    resultDiv.innerHTML += '<div class="log-output">' + (response.output || '') + '</div>';
                    resultDiv.innerHTML += '<button class="btn btn-primary" onclick="executeUpgrade()">重试</button>';
                }
            })
            .catch(error => {
                resultDiv.innerHTML = '<div class="message message-error">升级失败: ' + error.message + '</div>';
            });
    }
    </script>
</body>
</html>
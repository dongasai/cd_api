<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CdApi 安装向导</title>
    <script>
        async function fetchAPI(url, method = 'GET', data = null) {
            const options = {
                method,
                headers: {
                    'Content-Type': 'application/json'
                }
            };
            if (data && method !== 'GET') {
                options.body = JSON.stringify(data);
            }
            return fetch(url, options).then(res => res.json());
        }
    </script>
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

        .progress-bar {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 30px 20px;
            background: var(--card-bg);
        }

        .progress-step {
            display: flex;
            align-items: center;
        }

        .step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 80px;
        }

        .step-number {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 8px;
            background: var(--border);
            color: var(--text-light);
        }

        .step-item.active .step-number {
            background: var(--primary);
            color: white;
        }

        .step-item.completed .step-number {
            background: var(--success);
            color: white;
        }

        .step-label {
            font-size: 12px;
            color: var(--text-light);
        }

        .step-item.active .step-label,
        .step-item.completed .step-label {
            color: var(--text);
        }

        .step-line {
            width: 40px;
            height: 2px;
            background: var(--border);
        }

        .step-line.completed {
            background: var(--success);
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

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
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

        .btn-secondary:hover {
            background: #ccc;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #219a52;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .status-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid var(--border);
        }

        .status-item:last-child {
            border-bottom: none;
        }

        .status-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 12px;
        }

        .status-icon.success {
            background: var(--success);
            color: white;
        }

        .status-icon.error {
            background: var(--error);
            color: white;
        }

        .status-icon.warning {
            background: var(--warning);
            color: white;
        }

        .status-name {
            flex: 1;
        }

        .status-result {
            font-size: 12px;
            color: var(--text-light);
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
        <h1>CdApi 安装向导</h1>
        <p>欢迎使用 CdApi AI 网关工具</p>
    </div>

    @if(isset($step))
    <div class="progress-bar">
        <div class="progress-step">
            @php
            $steps = [
            1 => '开始',
            2 => '环境检测',
            3 => '配置',
            4 => '数据库检查',
            5 => '迁移',
            6 => '数据填充',
            7 => '完成',
            ];
            @endphp
            @foreach($steps as $num => $label)
            <div class="step-item {{ $step >= $num ? 'active' : '' }} {{ $step > $num ? 'completed' : '' }}">
                <div class="step-number">{{ $step > $num ? '✓' : $num }}</div>
                <div class="step-label">{{ $label }}</div>
            </div>
            @if($num < count($steps))
                <div class="step-line {{ $step > $num ? 'completed' : '' }}">
        </div>
        @endif
        @endforeach
    </div>
    </div>
    @endif

    <div class="content">
        <div class="card">
            @yield('content')
        </div>
    </div>

    <div class="footer">
        CdApi &copy; 2024
    </div>
</body>

</html>
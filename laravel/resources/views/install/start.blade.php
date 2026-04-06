<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CdApi - 需要安装</title>
    <style>
        :root {
            --primary: #3498db;
            --text: #333;
            --bg: #f5f5f5;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            text-align: center;
            padding: 40px;
        }
        h1 { color: var(--primary); font-size: 32px; margin-bottom: 20px; }
        p { color: var(--text); margin-bottom: 20px; }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: var(--primary);
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 16px;
        }
        .btn:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="container">
        <h1>CdApi 安装向导</h1>
        <p>系统尚未安装，请点击下方按钮开始安装</p>
        <a href="/install.php" class="btn">开始安装</a>
    </div>
</body>
</html>
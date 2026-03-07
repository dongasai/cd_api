<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - {{ config('app.name', 'CdApi') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f9fafb;
            color: #111827;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .header {
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            padding: 0 1rem;
        }
        .header-content {
            max-width: 80rem;
            margin: 0 auto;
            height: 4rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .logo { font-size: 1.25rem; font-weight: 700; color: #4f46e5; text-decoration: none; }
        .nav-link {
            font-size: 0.875rem;
            color: #4b5563;
            text-decoration: none;
        }
        .nav-link:hover { color: #111827; }
        .main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .error-container {
            text-align: center;
            max-width: 32rem;
            margin: 0 auto;
        }
        .error-code {
            font-size: 6rem;
            font-weight: 800;
            color: #4f46e5;
            line-height: 1;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #4f46e5 0%, #9333ea 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .error-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.75rem;
        }
        .error-message {
            color: #6b7280;
            margin-bottom: 2rem;
            line-height: 1.6;
            font-size: 1rem;
        }
        .btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: center;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.625rem 1.25rem;
            border-radius: 0.5rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 0.875rem;
        }
        .btn-primary {
            background: #4f46e5;
            color: #fff;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .btn-primary:hover { background: #4338ca; }
        .btn-secondary {
            background: #fff;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        .btn-secondary:hover { background: #f9fafb; }
        .btn svg { width: 1rem; height: 1rem; margin-right: 0.5rem; }
        .error-icon {
            width: 5rem;
            height: 5rem;
            margin: 0 auto 1.5rem;
            color: #4f46e5;
        }
        .footer {
            background: #fff;
            border-top: 1px solid #e5e7eb;
            padding: 1rem;
            text-align: center;
            font-size: 0.75rem;
            color: #6b7280;
        }
        .footer a { color: #4f46e5; text-decoration: none; }
        .footer a:hover { text-decoration: underline; }
        @media (max-width: 640px) {
            .error-code { font-size: 4rem; }
            .error-title { font-size: 1.25rem; }
            .btn-group { flex-direction: column; align-items: stretch; }
            .btn { justify-content: center; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <a href="{{ url('/') }}" class="logo">CdApi</a>
            <nav>
                <a href="{{ url('/admin') }}" class="nav-link">管理中心</a>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="error-container">
            @yield('content')
        </div>
    </main>

    <footer class="footer">
        <p>&copy; {{ date('Y') }} CdApi. All rights reserved.</p>
    </footer>
</body>
</html>

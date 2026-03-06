<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'CdApi') }}</title>

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
            .logo { font-size: 1.25rem; font-weight: 700; color: #4f46e5; }
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
            .hero {
                text-align: center;
                max-width: 48rem;
                margin: 0 auto;
            }
            .icon-box {
                width: 4rem;
                height: 4rem;
                border-radius: 0.75rem;
                background: linear-gradient(135deg, #6366f1 0%, #9333ea 100%);
                display: inline-flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 1rem;
                box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            }
            .icon { width: 2rem; height: 2rem; color: #fff; }
            .title {
                font-size: 2.5rem;
                font-weight: 800;
                letter-spacing: -0.025em;
                margin-bottom: 0.5rem;
                background: linear-gradient(90deg, #4f46e5, #9333ea, #ec4899);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }
            .subtitle {
                font-size: 1.25rem;
                color: #4b5563;
                margin-bottom: 0.75rem;
                font-weight: 500;
            }
            .description {
                color: #6b7280;
                margin-bottom: 1.5rem;
                max-width: 42rem;
                margin-left: auto;
                margin-right: auto;
                line-height: 1.5;
                font-size: 0.9rem;
            }
            .btn-group {
                display: flex;
                flex-wrap: wrap;
                gap: 0.75rem;
                justify-content: center;
                margin-bottom: 2rem;
            }
            .btn {
                display: inline-flex;
                align-items: center;
                padding: 0.5rem 1rem;
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
            .features {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 1rem;
                margin-top: 0;
            }
            .feature {
                padding: 1rem;
                border-radius: 0.75rem;
                background: #fff;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                border: 1px solid #f3f4f6;
            }
            .feature-icon {
                width: 2.5rem;
                height: 2.5rem;
                border-radius: 0.5rem;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 0.5rem;
            }
            .feature-icon.blue { background: #dbeafe; }
            .feature-icon.blue svg { color: #2563eb; }
            .feature-icon.green { background: #d1fae5; }
            .feature-icon.green svg { color: #059669; }
            .feature-icon.purple { background: #e9d5ff; }
            .feature-icon.purple svg { color: #9333ea; }
            .feature-icon svg { width: 1.25rem; height: 1.25rem; }
            .feature h3 { font-weight: 600; margin-bottom: 0.25rem; font-size: 0.9rem; }
            .feature p { font-size: 0.75rem; color: #6b7280; }
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
            .footer p + p { margin-top: 0.5rem; }
            @media (max-width: 640px) {
                .title { font-size: 2rem; }
                .subtitle { font-size: 1rem; }
                .btn-group { flex-direction: column; align-items: stretch; }
                .btn { justify-content: center; }
                .features { grid-template-columns: 1fr; }
            }
        </style>
    </head>
    <body>
        {{-- Header --}}
        <header class="header">
            <div class="header-content">
                <div class="logo">CdApi</div>
                <nav>
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/admin') }}" class="nav-link">管理后台</a>
                        @else
                            <a href="{{ route('login') }}" class="nav-link">登录</a>
                        @endauth
                    @endif
                </nav>
            </div>
        </header>

        {{-- Hero Section --}}
        <main class="main">
            <div class="hero">
                {{-- Logo --}}
                <div class="icon-box">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                    </svg>
                </div>

                {{-- Title --}}
                <h1 class="title">CdApi</h1>

                {{-- Subtitle --}}
                <p class="subtitle">Vibe Coding Api 基座</p>

                {{-- Description --}}
                <p class="description">
                    基于 Laravel 12 + Filament 构建的现代化 API 基座，为 AI Coding 平台提供稳定、可扩展的渠道管理服务。
                </p>

                {{-- CTA Buttons --}}
                <div class="btn-group">
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/admin') }}" class="btn btn-primary">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                                </svg>
                                进入管理后台
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-primary">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                                </svg>
                                立即登录
                            </a>
                        @endauth
                    @endif
                    <a href="https://github.com" target="_blank" class="btn btn-secondary">
                        <svg fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd"/>
                        </svg>
                        查看文档
                    </a>
                </div>

                {{-- Features --}}
                <div class="features">
                    <div class="feature">
                        <div class="feature-icon blue">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <h3>快速开发</h3>
                        <p>Laravel 生态丰富，开发效率极高</p>
                    </div>
                    <div class="feature">
                        <div class="feature-icon green">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <h3>安全可靠</h3>
                        <p>完善的认证与授权机制</p>
                    </div>
                    <div class="feature">
                        <div class="feature-icon purple">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                            </svg>
                        </div>
                        <h3>易于扩展</h3>
                        <p>模块化设计，灵活扩展</p>
                    </div>
                </div>
            </div>
        </main>

        {{-- Footer --}}
        <footer class="footer">
            <p>&copy; {{ date('Y') }} CdApi. All rights reserved.</p>
            <p>Powered by <a href="https://laravel.com" target="_blank">Laravel 12</a></p>
        </footer>
    </body>
</html>

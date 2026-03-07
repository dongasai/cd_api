@extends('errors.layout')

@section('title', '429 请求过于频繁')

@section('content')
<svg class="error-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
</svg>
<div class="error-code">429</div>
<h1 class="error-title">请求过于频繁</h1>
<p class="error-message">
    抱歉，您的请求过于频繁。请稍后再试，或联系管理员了解详情。
</p>
<div class="btn-group">
    <a href="{{ url('/') }}" class="btn btn-primary">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
        </svg>
        返回首页
    </a>
    <a href="{{ url()->previous() }}" class="btn btn-secondary">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        返回上一页
    </a>
</div>
@endsection

@extends('install.layout')

@section('content')
<?php $step = 7; ?>

<h2 class="card-title">安装完成</h2>

<div class="message message-success">
    CdApi 安装成功！
</div>

<p>系统已完成安装，您可以开始使用了。</p>

<div style="margin-top: 20px;">
    <p><strong>下一步:</strong></p>
    <ul style="margin-left: 20px; margin-top: 10px;">
        <li>访问后台管理: <a href="{{ url('/admin') }}" target="_blank">/admin</a></li>
        <li>使用刚创建的管理员账号登录</li>
    </ul>
</div>

<div class="btn-group" style="margin-top: 30px;">
    <a href="{{ url('/admin') }}" class="btn btn-primary">进入后台</a>
</div>
@endsection
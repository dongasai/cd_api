@extends('install.layout')

@section('content')
<?php $step = 1; ?>

<h2 class="card-title">准备安装</h2>

<table style="width:100%;border-collapse:collapse;font-size:14px;margin-bottom:20px;">
    <tr style="border-bottom:1px solid #eee;">
        <td style="padding:10px;width:30px;color:#27ae60;font-weight:bold;">✓</td>
        <td style="padding:10px;">.env 配置文件</td>
        <td style="padding:10px;text-align:right;color:#666;">{{ $hasEnvFile ? '已创建' : '不存在' }}</td>
    </tr>
    <tr style="border-bottom:1px solid #eee;">
        <td style="padding:10px;width:30px;color:{{ $hasAppKey ? '#27ae60' : '#e74c3c' }};font-weight:bold;">{{ $hasAppKey ? '✓' : '✗' }}</td>
        <td style="padding:10px;">APP_KEY</td>
        <td style="padding:10px;text-align:right;color:#666;">{{ $hasAppKey ? '已配置' : '未配置' }}</td>
    </tr>
</table>

@if($hasAppKey)
<div class="message message-success">
    系统环境检查通过
</div>
<p>系统已准备好进行安装，点击下方按钮开始安装流程。</p>
<div class="btn-group">
    <a href="{{ route('install.environment') }}" class="btn btn-primary">开始安装</a>
</div>
@else
<div class="message message-warning">
    需要生成 APP_KEY 才能继续安装
</div>
<div class="btn-group">
    <button id="generateKeyBtn" class="btn btn-primary">生成 APP_KEY</button>
</div>

<script>
document.getElementById('generateKeyBtn').addEventListener('click', function() {
    this.disabled = true;
    this.textContent = '正在生成...';

    fetch('{{ route('install.generate_key') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('生成失败: ' + data.message);
            this.disabled = false;
            this.textContent = '生成 APP_KEY';
        }
    })
    .catch(error => {
        alert('请求失败');
        this.disabled = false;
        this.textContent = '生成 APP_KEY';
    });
});
</script>
@endif
@endsection
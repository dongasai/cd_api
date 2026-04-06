@extends('install.layout')

@section('content')
<?php $step = 4; ?>

<h2 class="card-title">数据库检查</h2>

@if($hasData)
<div class="message message-warning">
    检测到数据库中已存在数据表，继续安装可能会覆盖现有数据。
</div>

<p style="margin-bottom: 16px;">已有数据表 ({{ count($existingTables) }} 个)：</p>
<div style="max-height: 200px; overflow-y: auto; background: #f5f5f5; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
    @foreach($existingTables as $table)
    <span style="display: inline-block; background: #e2e8f0; padding: 4px 8px; border-radius: 4px; margin: 4px; font-size: 13px;">{{ $table }}</span>
    @endforeach
</div>

<p style="margin-bottom: 20px; color: #666;">请选择如何处理：</p>

<div class="btn-group">
    <a href="{{ route('install.config') }}" class="btn btn-secondary">返回修改配置</a>
    <button id="cleanBtn" class="btn btn-error" style="background: #e74c3c; color: white;">清空数据并继续</button>
    <button id="forceBtn" class="btn btn-primary">保留数据并继续</button>
</div>

@else
<div class="message message-success">
    数据库为空，可以继续安装。
</div>

<div class="btn-group">
    <a href="{{ route('install.config') }}" class="btn btn-secondary">上一步</a>
    <a href="{{ route('install.migrate') }}" class="btn btn-primary">下一步</a>
</div>
@endif

<script>
@if($hasData)
document.getElementById('cleanBtn').addEventListener('click', function() {
    if (!confirm('确定要清空数据库吗？此操作不可恢复！')) return;

    this.disabled = true;
    this.textContent = '清空中...';

    fetchAPI('{{ route("install.clean_database") }}', 'POST')
        .then(response => {
            if (response.success) {
                location.href = '{{ route("install.migrate") }}';
            } else {
                alert('清空失败: ' + response.message);
                this.disabled = false;
                this.textContent = '清空数据并继续';
            }
        })
        .catch(error => {
            alert('请求失败');
            this.disabled = false;
            this.textContent = '清空数据并继续';
        });
});

document.getElementById('forceBtn').addEventListener('click', function() {
    location.href = '{{ route("install.migrate") }}';
});
@endif
</script>
@endsection
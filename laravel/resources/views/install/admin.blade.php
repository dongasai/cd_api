@extends('install.layout')

@section('content')
<?php $step = 6; ?>

<h2 class="card-title">数据填充</h2>

<p>创建管理员账号并初始化系统数据...</p>

<form id="admin-form">
    <div class="form-group">
        <label>用户名</label>
        <input type="text" name="username" required minlength="3" maxlength="50" placeholder="admin">
    </div>

    <div class="form-group">
        <label>密码</label>
        <input type="password" name="password" required minlength="8" placeholder="至少8位">
        <small style="color: var(--text-light);">建议包含大小写字母和数字</small>
    </div>

    <div class="form-group">
        <label>姓名</label>
        <input type="text" name="name" required maxlength="100" placeholder="管理员">
    </div>

    <div class="btn-group">
        <a href="{{ route('install.migrate') }}" class="btn btn-secondary">上一步</a>
        <button type="submit" class="btn btn-primary">创建管理员</button>
    </div>
</form>

<div id="admin-result"></div>

<script>
document.getElementById('admin-form').addEventListener('submit', function(e) {
    // 先进行 HTML5 表单验证
    if (!this.checkValidity()) {
        // 验证失败，显示浏览器默认验证提示
        this.reportValidity();
        return;
    }

    e.preventDefault();
    const formData = new FormData(this);
    const data = {};
    formData.forEach((value, key) => data[key] = value);

    const resultDiv = document.getElementById('admin-result');
    resultDiv.innerHTML = '<div class="loading"><div class="spinner"></div><p>初始化数据并创建管理员...</p></div>';

    fetchAPI('{{ route("install.create_admin") }}', 'POST', data)
        .then(response => {
            if (response.success) {
                resultDiv.innerHTML = '<div class="message message-success">' + response.message + '</div>';
                if (response.admin) {
                    resultDiv.innerHTML += '<div style="margin-top: 20px;"><p><strong>用户名:</strong> ' + response.admin.username + '</p><p><strong>姓名:</strong> ' + response.admin.name + '</p></div>';
                }
                setTimeout(() => {
                    window.location.href = '{{ route("install.complete") }}';
                }, 2000);
            } else if (response.errors) {
                // 验证错误，显示具体错误信息
                let errorMessages = Object.values(response.errors).flat();
                resultDiv.innerHTML = '<div class="message message-error">' + errorMessages.join('<br>') + '</div>';
            } else {
                resultDiv.innerHTML = '<div class="message message-error">' + (response.message || '创建失败') + '</div>';
            }
        })
        .catch(error => {
            resultDiv.innerHTML = '<div class="message message-error">请求失败</div>';
        });
});
</script>
@endsection
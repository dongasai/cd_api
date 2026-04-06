@extends('install.layout')

@section('content')
<?php $step = 3; ?>

<h2 class="card-title">配置填写</h2>

<form id="config-form">
    <div class="form-group">
        <label>应用地址 (APP_URL)</label>
        <input type="url" name="app_url" value="{{ $currentConfig['app_url'] }}" required placeholder="http://localhost">
    </div>

    <div class="form-group">
        <label>数据库类型</label>
        <select name="db_connection" id="db_connection" required onchange="toggleMySQLFields()">
            <option value="mysql" {{ $currentConfig['db_connection'] === 'mysql' ? 'selected' : '' }}>MySQL</option>
            <option value="sqlite" {{ $currentConfig['db_connection'] === 'sqlite' ? 'selected' : '' }}>SQLite</option>
        </select>
    </div>

    <div id="mysql-fields">
        <div class="form-group">
            <label>数据库地址</label>
            <input type="text" name="db_host" value="{{ $currentConfig['db_host'] }}" placeholder="127.0.0.1">
        </div>

        <div class="form-group">
            <label>数据库端口</label>
            <input type="number" name="db_port" value="{{ $currentConfig['db_port'] }}" placeholder="3306">
        </div>

        <div class="form-group">
            <label>数据库名</label>
            <input type="text" name="db_database" value="{{ $currentConfig['db_database'] }}" placeholder="laravel">
        </div>

        <div class="form-group">
            <label>数据库用户</label>
            <input type="text" name="db_username" value="{{ $currentConfig['db_username'] }}" placeholder="root">
        </div>

        <div class="form-group">
            <label>数据库密码</label>
            <input type="password" name="db_password" value="{{ $currentConfig['db_password'] }}" placeholder="">
        </div>

        <button type="button" class="btn btn-secondary" onclick="testConnection()">测试连接</button>
        <div id="test-result"></div>
    </div>

    <div class="btn-group">
        <a href="{{ route('install.environment') }}" class="btn btn-secondary">上一步</a>
        <button type="submit" class="btn btn-primary">保存配置</button>
    </div>
</form>

<script>
function toggleMySQLFields() {
    const dbConnection = document.getElementById('db_connection').value;
    const mysqlFields = document.getElementById('mysql-fields');
    mysqlFields.style.display = dbConnection === 'mysql' ? 'block' : 'none';
}

// 初始化显示状态
toggleMySQLFields();

function testConnection() {
    const form = document.getElementById('config-form');
    const formData = new FormData(form);
    const data = {};
    formData.forEach((value, key) => data[key] = value);

    const resultDiv = document.getElementById('test-result');
    resultDiv.innerHTML = '<div class="loading"><div class="spinner"></div></div>';

    fetchAPI('{{ route("install.test_connection") }}', 'POST', data)
        .then(response => {
            if (response.success) {
                resultDiv.innerHTML = '<div class="message message-success">' + response.message + '</div>';
            } else {
                resultDiv.innerHTML = '<div class="message message-error">' + response.message + '</div>';
            }
        });
}

document.getElementById('config-form').addEventListener('submit', function(e) {
    // 先进行 HTML5 表单验证
    if (!this.checkValidity()) {
        this.reportValidity();
        return;
    }

    e.preventDefault();
    const formData = new FormData(this);
    const data = {};
    formData.forEach((value, key) => data[key] = value);

    const card = document.querySelector('.card');
    const originalContent = card.innerHTML;
    card.innerHTML = '<div class="loading"><div class="spinner"></div><p>保存配置...</p></div>';

    fetchAPI('{{ route("install.save_config") }}', 'POST', data)
        .then(response => {
            if (response.success) {
                window.location.href = '{{ route("install.database_check") }}';
            } else if (response.errors) {
                card.innerHTML = originalContent;
                let errorMessages = Object.values(response.errors).flat();
                alert(errorMessages.join('\n'));
            } else {
                card.innerHTML = originalContent;
                alert(response.message || '保存失败');
            }
        })
        .catch(error => {
            card.innerHTML = originalContent;
            alert('请求失败');
        });
});
</script>
@endsection
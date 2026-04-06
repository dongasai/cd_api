@extends('install.layout')

@section('content')
<?php $step = 5; ?>

<h2 class="card-title">数据库迁移</h2>

<div id="migrate-status" style="margin-bottom: 16px;">
    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
        <div class="spinner" id="main-spinner" style="width: 20px; height: 20px; border-width: 2px;"></div>
        <span id="status-text">正在获取迁移列表...</span>
    </div>
    <div style="background: #eee; border-radius: 4px; height: 8px; overflow: hidden;">
        <div id="progress-bar" style="background: var(--primary); height: 100%; width: 0%; transition: width 0.3s;"></div>
    </div>
    <div id="progress-text" style="font-size: 12px; color: #666; margin-top: 4px;"></div>
</div>

<div id="migrate-log" class="log-output" style="max-height: 200px; overflow-y: auto; font-size: 12px;">
</div>

<div id="migrate-result"></div>

<div class="btn-group" style="display: none;" id="btn-group">
    <a href="{{ route('install.database_check') }}" class="btn btn-secondary">上一步</a>
    <a href="{{ route('install.admin') }}" class="btn btn-primary" id="next-btn" style="display: none;">下一步</a>
</div>

<script>
(function() {
    const statusText = document.getElementById('status-text');
    const progressBar = document.getElementById('progress-bar');
    const progressText = document.getElementById('progress-text');
    const logDiv = document.getElementById('migrate-log');
    const mainSpinner = document.getElementById('main-spinner');
    const resultDiv = document.getElementById('migrate-result');
    const btnGroup = document.getElementById('btn-group');
    const nextBtn = document.getElementById('next-btn');

    let pendingMigrations = [];
    let currentIndex = 0;
    let successCount = 0;
    let failCount = 0;

    function log(message, type = 'info') {
        const colors = { success: '#27ae60', error: '#e74c3c', info: '#60a5fa', warning: '#f59e0b' };
        const time = new Date().toLocaleTimeString();
        logDiv.innerHTML += `<div style="color: ${colors[type] || '#d4d4d4'}">[${time}] ${message}</div>`;
        logDiv.scrollTop = logDiv.scrollHeight;
    }

    function updateProgress() {
        const total = pendingMigrations.length;
        const percent = total > 0 ? Math.round((currentIndex / total) * 100) : 0;
        progressBar.style.width = percent + '%';
        progressText.textContent = `${currentIndex} / ${total}`;
    }

    async function runMigration() {
        if (currentIndex >= pendingMigrations.length) {
            // 全部完成
            mainSpinner.style.display = 'none';
            statusText.textContent = '迁移完成';
            btnGroup.style.display = 'flex';

            if (failCount === 0) {
                resultDiv.innerHTML = `<div class="message message-success">数据库迁移成功 (${successCount} 个文件)</div>`;
                nextBtn.style.display = 'inline-block';
            } else {
                resultDiv.innerHTML = `<div class="message message-error">迁移完成，${successCount} 成功，${failCount} 失败</div>`;
            }
            return;
        }

        const migration = pendingMigrations[currentIndex];
        statusText.textContent = `正在执行: ${migration.name.substring(0, 50)}...`;

        try {
            const response = await fetchAPI('{{ route("install.migrate_one") }}', 'POST', { migration: migration.name });

            if (response.success) {
                log(`✓ ${migration.file}`, 'success');
                successCount++;
            } else {
                log(`✗ ${migration.file}: ${response.message}`, 'error');
                failCount++;
                // 失败时停止
                mainSpinner.style.display = 'none';
                statusText.textContent = '迁移失败';
                btnGroup.style.display = 'flex';
                resultDiv.innerHTML = `<div class="message message-error">迁移失败: ${response.message}</div>`;
                return;
            }
        } catch (e) {
            log(`✗ ${migration.file}: 请求失败`, 'error');
            failCount++;
        }

        currentIndex++;
        updateProgress();

        // 间隔 500ms 执行下一个
        setTimeout(runMigration, 500);
    }

    async function startMigrate() {
        try {
            const response = await fetchAPI('{{ route("install.pending_migrations") }}', 'POST');

            if (!response.success || response.count === 0) {
                mainSpinner.style.display = 'none';
                statusText.textContent = '无需迁移';
                log('数据库已是最新状态', 'success');
                btnGroup.style.display = 'flex';
                nextBtn.style.display = 'inline-block';
                return;
            }

            pendingMigrations = response.pending;
            log(`待执行迁移: ${response.count} 个文件`);
            updateProgress();

            // 开始逐个执行
            setTimeout(runMigration, 500);
        } catch (e) {
            mainSpinner.style.display = 'none';
            statusText.textContent = '获取迁移列表失败';
            log('获取迁移列表失败: ' + e.message, 'error');
            btnGroup.style.display = 'flex';
        }
    }

    // 开始
    startMigrate();
})();
</script>
@endsection
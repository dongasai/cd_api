@extends('install.layout')

@section('content')
<?php $step = 2; ?>

<h2 class="card-title">环境检测</h2>

<p style="margin-bottom: 20px;">检测系统运行环境和依赖项...</p>

<div id="check-results" class="loading">
    <div class="spinner"></div>
</div>

<div class="btn-group" style="display: none;" id="btn-group">
    <a href="{{ route('install.index') }}" class="btn btn-secondary">上一步</a>
    <a href="{{ route('install.config') }}" class="btn btn-primary" id="next-btn">下一步</a>
</div>

<script>
(function() {
    const resultsDiv = document.getElementById('check-results');
    const btnGroup = document.getElementById('btn-group');
    const nextBtn = document.getElementById('next-btn');

    fetchAPI('{{ route("install.check_environment") }}', 'POST')
        .then(response => {
            if (response.success) {
                let html = '<table style="width:100%;border-collapse:collapse;font-size:14px;">';
                for (const [group, items] of Object.entries(response.results)) {
                    for (const item of items) {
                        const icon = item.status ? '✓' : '✗';
                        const color = item.status ? '#27ae60' : '#e74c3c';
                        html += '<tr style="border-bottom:1px solid #eee;">' +
                            '<td style="padding:10px;width:30px;color:' + color + ';font-weight:bold;">' + icon + '</td>' +
                            '<td style="padding:10px;">' + item.name + '</td>' +
                            '<td style="padding:10px;text-align:right;color:#666;">' + item.actual + '</td>' +
                            '</tr>';
                    }
                }
                html += '</table>';
                resultsDiv.innerHTML = html;
                btnGroup.style.display = 'flex';

                if (!response.all_passed) {
                    nextBtn.className = 'btn btn-secondary';
                    nextBtn.style.pointerEvents = 'none';
                }
            }
        })
        .catch(error => {
            resultsDiv.innerHTML = '<div class="message message-error">检测失败: ' + error.message + '</div>';
        });
})();
</script>
@endsection
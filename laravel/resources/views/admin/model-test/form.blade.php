<div class="model-test-form">
    <form id="test-form" class="form-horizontal">
        <!-- 测试类型选择 -->
        <div class="form-group">
            <label class="col-sm-2 control-label">测试类型<span class="text-danger">*</span></label>
            <div class="col-sm-8">
                <select name="test_type" id="test_type" class="form-control" required>
                    @foreach($testTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <span class="help-block">
                    <small>渠道直接测试: 直接调用上游API,不经过系统代理流程<br>系统API测试: 使用默认测试Key,完整走系统代理流程</small>
                </span>
            </div>
        </div>

        <!-- 渠道选择(仅渠道直接测试显示) -->
        <div class="form-group" id="channel-select-group">
            <label class="col-sm-2 control-label">选择渠道<span class="text-danger">*</span></label>
            <div class="col-sm-8">
                <select name="channel_id" id="channel_id" class="form-control">
                    <option value="">请选择渠道</option>
                    @foreach($channels as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- 模型选择 -->
        <div class="form-group">
            <label class="col-sm-2 control-label">测试模型<span class="text-danger">*</span></label>
            <div class="col-sm-8">
                <input type="text" name="model" id="model" class="form-control" placeholder="如: gpt-4, claude-3-opus-20240229" required>
                <span class="help-block">
                    <small id="model-help">选择渠道后可查看支持的模型列表</small>
                </span>
            </div>
        </div>

        <!-- 预设提示词 -->
        <div class="form-group">
            <label class="col-sm-2 control-label">预设提示词</label>
            <div class="col-sm-8">
                <select name="preset_prompt_id" id="preset_prompt_id" class="form-control">
                    <option value="">不使用预设</option>
                    @foreach($presetPrompts as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- 用户消息 -->
        <div class="form-group">
            <label class="col-sm-2 control-label">用户消息</label>
            <div class="col-sm-8">
                <textarea name="user_message" id="user_message" class="form-control" rows="3" placeholder="输入测试消息,默认: 你好,请介绍一下你自己"></textarea>
            </div>
        </div>

        <!-- 流式输出 -->
        <div class="form-group">
            <label class="col-sm-2 control-label">流式输出</label>
            <div class="col-sm-8">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="is_stream" id="is_stream" value="1">
                        启用流式响应(SSE)
                    </label>
                </div>
            </div>
        </div>

        <!-- 提交按钮 -->
        <div class="form-group">
            <div class="col-sm-offset-2 col-sm-8">
                <button type="submit" class="btn btn-primary" id="submit-btn">
                    <i class="fa fa-play"></i> 开始测试
                </button>
                <button type="button" class="btn btn-default" id="reset-btn">
                    <i class="fa fa-refresh"></i> 重置
                </button>
            </div>
        </div>
    </form>
</div>

<style>
.model-test-form .form-group {
    margin-bottom: 15px;
}
.model-test-form .control-label {
    font-weight: 600;
}
.model-test-form .help-block {
    color: #737373;
    margin-top: 5px;
}
#model-help {
    display: block;
}
</style>

<script>
$(function() {
    var testTypeSelect = $('#test_type');
    var channelSelect = $('#channel_id');
    var channelGroup = $('#channel-select-group');
    var modelInput = $('#model');
    var modelHelp = $('#model-help');
    var presetSelect = $('#preset_prompt_id');
    var userMessageInput = $('#user_message');
    var isStreamCheckbox = $('#is_stream');
    var submitBtn = $('#submit-btn');
    var testForm = $('#test-form');

    // 测试类型切换
    testTypeSelect.on('change', function() {
        var testType = $(this).val();
        if (testType === 'channel_direct') {
            channelGroup.show();
            channelSelect.prop('required', true);
        } else {
            channelGroup.hide();
            channelSelect.prop('required', false);
        }
    }).trigger('change');

    // 渠道选择后加载模型列表
    channelSelect.on('change', function() {
        var channelId = $(this).val();
        if (!channelId) {
            modelHelp.text('选择渠道后可查看支持的模型列表');
            return;
        }

        modelHelp.text('加载模型列表...');

        $.ajax({
            url: '{{ admin_url('model-test/channel-models') }}/' + channelId,
            method: 'GET',
            success: function(response) {
                if (response.success && response.data) {
                    var models = response.data;
                    var modelList = Object.keys(models).join(', ');
                    modelHelp.html('<strong>支持的模型:</strong> ' + modelList);
                } else {
                    modelHelp.text('未找到支持的模型');
                }
            },
            error: function() {
                modelHelp.text('加载模型列表失败');
            }
        });
    });

    // 预设提示词选择后填充默认消息
    presetSelect.on('change', function() {
        // 预设提示词的详细内容可以在后续版本中通过 AJAX 加载
    });

    // 表单提交
    testForm.on('submit', function(e) {
        e.preventDefault();

        var testType = testTypeSelect.val();
        var channelId = channelSelect.val();
        var model = modelInput.val().trim();
        var presetPromptId = presetSelect.val();
        var userMessage = userMessageInput.val().trim();
        var isStream = isStreamCheckbox.is(':checked');

        // 验证
        if (!model) {
            Dcat.error('请输入测试模型');
            return;
        }

        if (testType === 'channel_direct' && !channelId) {
            Dcat.error('请选择测试渠道');
            return;
        }

        // 禁用按钮
        submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> 测试中...');

        // 清空结果区域
        $('#result-content').html('<div class="text-center"><i class="fa fa-spinner fa-spin fa-2x"></i><p>测试进行中...</p></div>');
        $('#result-metrics').html('');

        var requestData = {
            test_type: testType,
            model: model,
            user_message: userMessage,
            preset_prompt_id: presetPromptId || null,
            is_stream: isStream,
            channel_id: channelId
        };

        if (isStream) {
            // 流式请求
            executeStreamTest(requestData);
        } else {
            // 非流式请求
            executeNormalTest(requestData);
        }
    });

    // 重置按钮
    $('#reset-btn').on('click', function() {
        testForm[0].reset();
        $('#result-content').html('<p class="text-muted">测试结果将在这里显示</p>');
        $('#result-metrics').html('');
    });

    // 非流式测试
    function executeNormalTest(data) {
        $.ajax({
            url: '{{ admin_url('model-test/test') }}',
            method: 'POST',
            data: data,
            success: function(response) {
                submitBtn.prop('disabled', false).html('<i class="fa fa-play"></i> 开始测试');

                if (response.success) {
                    displayTestResult(response.data);
                } else {
                    Dcat.error(response.message || '测试失败');
                    $('#result-content').html('<div class="alert alert-danger">' + (response.message || '测试失败') + '</div>');
                }
            },
            error: function(xhr) {
                submitBtn.prop('disabled', false).html('<i class="fa fa-play"></i> 开始测试');
                var errorMsg = xhr.responseJSON?.message || '请求失败';
                Dcat.error(errorMsg);
                $('#result-content').html('<div class="alert alert-danger">' + errorMsg + '</div>');
            }
        });
    }

    // 流式测试
    function executeStreamTest(data) {
        var eventSource = new EventSource(
            '{{ admin_url('model-test/test') }}?' + $.param(data)
        );

        var fullContent = '';

        eventSource.onmessage = function(e) {
            var result = JSON.parse(e.data);

            if (result.status === 'start') {
                $('#result-content').html('<div class="stream-content"></div>');
            } else if (result.status === 'streaming') {
                fullContent += result.content;
                $('#result-content .stream-content').html(
                    '<pre style="white-space: pre-wrap; word-wrap: break-word;">' +
                    fullContent +
                    '</pre>'
                );
            } else if (result.status === 'done') {
                submitBtn.prop('disabled', false).html('<i class="fa fa-play"></i> 开始测试');
                eventSource.close();
                Dcat.success('测试完成');
            } else if (result.status === 'error') {
                submitBtn.prop('disabled', false).html('<i class="fa fa-play"></i> 开始测试');
                eventSource.close();
                Dcat.error(result.message || '测试失败');
                $('#result-content').html('<div class="alert alert-danger">' + (result.message || '测试失败') + '</div>');
            }
        };

        eventSource.onerror = function() {
            submitBtn.prop('disabled', false).html('<i class="fa fa-play"></i> 开始测试');
            eventSource.close();
            Dcat.error('连接失败');
        };
    }

    // 显示测试结果
    function displayTestResult(data) {
        var statusLabel = data.status === 'success' ?
            '<span class="label label-success">成功</span>' :
            '<span class="label label-danger">失败</span>';

        var metricsHtml = `
            <div class="row">
                <div class="col-sm-3">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3>${data.response_time_ms || '-'}</h3>
                            <p>响应时间(ms)</p>
                        </div>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3>${data.first_token_ms || '-'}</h3>
                            <p>首Token(ms)</p>
                        </div>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3>${data.prompt_tokens || '-'}</h3>
                            <p>输入Token</p>
                        </div>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <h3>${data.total_tokens || '-'}</h3>
                            <p>总Token</p>
                        </div>
                    </div>
                </div>
            </div>
        `;

        var contentHtml = `
            <div class="result-header">
                <h4>AI响应 ${statusLabel}</h4>
                ${data.actual_model ? '<p class="text-muted">实际模型: ' + data.actual_model + '</p>' : ''}
                ${data.error_message ? '<div class="alert alert-danger">' + data.error_message + '</div>' : ''}
            </div>
            <div class="result-body">
                <pre style="white-space: pre-wrap; word-wrap: break-word; background: #f5f5f5; padding: 15px; border-radius: 4px; max-height: 400px; overflow-y: auto;">${data.assistant_response || ''}</pre>
            </div>
        `;

        $('#result-metrics').html(metricsHtml);
        $('#result-content').html(contentHtml);

        if (data.status === 'success') {
            Dcat.success('测试完成');
        } else {
            Dcat.error('测试失败: ' + (data.error_message || '未知错误'));
        }
    }
});
</script>
<div class="model-test-container">
    <div class="row">
        <!-- 左侧：测试表单 -->
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">测试配置</h3>
                </div>
                <div class="box-body">
                    <form id="test-form" class="form-horizontal">
                        <!-- 测试类型选择 -->
                        <div class="form-group">
                            <label class="col-sm-3 control-label">测试类型<span class="text-danger">*</span></label>
                            <div class="col-sm-9">
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

                        <!-- 渠道选择 -->
                        <div class="form-group" id="channel-select-group">
                            <label class="col-sm-3 control-label">选择渠道<span class="text-danger">*</span></label>
                            <div class="col-sm-9">
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
                            <label class="col-sm-3 control-label">测试模型<span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <!-- 渠道直接测试：下拉选择 -->
                                <select name="model" id="model_select" class="form-control" style="display: none;" required>
                                    <option value="">请先选择渠道</option>
                                </select>
                                <!-- 系统API测试：手动输入 -->
                                <input type="text" name="model_input" id="model_input" class="form-control" placeholder="如: gpt-4, claude-3-opus-20240229" style="display: none;" required>
                                <span class="help-block">
                                    <small id="model-help">选择渠道后将自动加载支持的模型列表</small>
                                </span>
                            </div>
                        </div>

                        <!-- 预设提示词 -->
                        <div class="form-group">
                            <label class="col-sm-3 control-label">预设提示词</label>
                            <div class="col-sm-9">
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
                            <label class="col-sm-3 control-label">用户消息</label>
                            <div class="col-sm-9">
                                <textarea name="user_message" id="user_message" class="form-control" rows="3" placeholder="输入测试消息,默认: 你好,请介绍一下你自己"></textarea>
                            </div>
                        </div>

                        <!-- 流式输出 -->
                        <div class="form-group">
                            <label class="col-sm-3 control-label">流式输出</label>
                            <div class="col-sm-9">
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
                            <div class="col-sm-offset-3 col-sm-9">
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
            </div>
        </div>

        <!-- 右侧：测试结果 -->
        <div class="col-md-6">
            <div class="box box-success">
                <div class="box-header with-border">
                    <h3 class="box-title">测试结果</h3>
                </div>
                <div class="box-body">
                    <div id="result-metrics"></div>
                    <div id="result-content">
                        <p class="text-muted">测试结果将在这里显示</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.model-test-container {
    padding: 20px;
}
.model-test-container .form-group {
    margin-bottom: 15px;
}
.model-test-container .control-label {
    font-weight: 600;
}
.model-test-container .help-block {
    color: #737373;
    margin-top: 5px;
}
#model-help {
    display: block;
}
.result-header {
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e7e7e7;
}
.result-header h4 {
    margin: 0;
}
.result-body {
    margin-top: 15px;
}
.small-box {
    border-radius: 4px;
    margin-bottom: 15px;
}
.small-box .inner {
    padding: 10px;
}
.small-box h3 {
    font-size: 24px;
    margin: 0;
}
.small-box p {
    margin: 0;
    font-size: 12px;
}
</style>

<script>
$(function() {
    var testTypeSelect = $('#test_type');
    var channelSelect = $('#channel_id');
    var channelGroup = $('#channel-select-group');
    var modelSelect = $('#model_select');
    var modelInput = $('#model_input');
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
            // 显示下拉选择，隐藏文本输入
            modelSelect.show().prop('required', true);
            modelInput.hide().prop('required', false);
            // 重置模型下拉
            if (!channelSelect.val()) {
                modelSelect.html('<option value="">请先选择渠道</option>');
            }
        } else {
            channelGroup.hide();
            channelSelect.prop('required', false);
            // 显示文本输入，隐藏下拉选择
            modelSelect.hide().prop('required', false);
            modelInput.show().prop('required', true);
            modelHelp.text('请输入要测试的模型名称');
        }
    }).trigger('change');

    // 渠道选择后加载模型列表
    channelSelect.on('change', function() {
        var channelId = $(this).val();
        if (!channelId) {
            modelSelect.html('<option value="">请先选择渠道</option>');
            modelHelp.text('选择渠道后将自动加载支持的模型列表');
            return;
        }

        modelHelp.text('加载模型列表...');
        modelSelect.html('<option value="">加载中...</option>');

        $.ajax({
            url: '{{ admin_url('model-test/old/channel-models') }}/' + channelId,
            method: 'GET',
            success: function(response) {
                if (response.success && response.data) {
                    var models = response.data;
                    var options = '<option value="">请选择模型</option>';
                    var firstModelId = null;
                    for (var modelId in models) {
                        if (models.hasOwnProperty(modelId)) {
                            var modelInfo = models[modelId];
                            var displayName = typeof modelInfo === 'object' ? (modelInfo.name || modelId) : modelInfo;
                            options += '<option value="' + modelId + '">' + displayName + '</option>';
                            if (!firstModelId) {
                                firstModelId = modelId;
                            }
                        }
                    }
                    modelSelect.html(options);
                    // 默认选中第一个模型
                    if (firstModelId) {
                        modelSelect.val(firstModelId);
                    }
                    modelHelp.html('<strong class="text-success">已加载 ' + Object.keys(models).length + ' 个模型</strong>');
                } else {
                    modelSelect.html('<option value="">该渠道暂无可用模型</option>');
                    modelHelp.text('未找到支持的模型');
                }
            },
            error: function() {
                modelSelect.html('<option value="">加载失败</option>');
                modelHelp.text('加载模型列表失败');
            }
        });
    });

    // 默认选中第一个渠道
    var firstChannel = channelSelect.find('option[value!=""]').first();
    if (firstChannel.length) {
        channelSelect.val(firstChannel.val()).trigger('change');
    }

    // 默认选中第一个预设提示词
    var firstPreset = presetSelect.find('option[value!=""]').first();
    if (firstPreset.length) {
        presetSelect.val(firstPreset.val());
    }

    // 表单提交
    testForm.on('submit', function(e) {
        e.preventDefault();

        var testType = testTypeSelect.val();
        var channelId = channelSelect.val();
        // 根据测试类型获取模型值
        var model = testType === 'channel_direct' ? modelSelect.val().trim() : modelInput.val().trim();
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
            channel_id: channelId
        };

        // 只有勾选时才发送 is_stream
        if (isStream) {
            requestData.is_stream = true;
        }

        if (isStream) {
            executeStreamTest(requestData);
        } else {
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
            url: '{{ admin_url('model-test/old/test') }}',
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
                var errorMsg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : '请求失败';
                Dcat.error(errorMsg);
                $('#result-content').html('<div class="alert alert-danger">' + errorMsg + '</div>');
            }
        });
    }

    // 流式测试
    function executeStreamTest(data) {
        var eventSource = new EventSource(
            '{{ admin_url('model-test/old/test') }}?' + $.param(data)
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

        var metricsHtml =
            '<div class="row">' +
                '<div class="col-sm-3">' +
                    '<div class="small-box bg-info">' +
                        '<div class="inner">' +
                            '<h3>' + (data.response_time_ms || '-') + '</h3>' +
                            '<p>响应时间(ms)</p>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="col-sm-3">' +
                    '<div class="small-box bg-success">' +
                        '<div class="inner">' +
                            '<h3>' + (data.first_token_ms || '-') + '</h3>' +
                            '<p>首Token(ms)</p>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="col-sm-3">' +
                    '<div class="small-box bg-warning">' +
                        '<div class="inner">' +
                            '<h3>' + (data.prompt_tokens || '-') + '</h3>' +
                            '<p>输入Token</p>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="col-sm-3">' +
                    '<div class="small-box bg-primary">' +
                        '<div class="inner">' +
                            '<h3>' + (data.total_tokens || '-') + '</h3>' +
                            '<p>总Token</p>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';

        var contentHtml =
            '<div class="result-header">' +
                '<h4>AI响应 ' + statusLabel + '</h4>' +
                (data.actual_model ? '<p class="text-muted">实际模型: ' + data.actual_model + '</p>' : '') +
                (data.error_message ? '<div class="alert alert-danger">' + data.error_message + '</div>' : '') +
            '</div>' +
            '<div class="result-body">' +
                '<pre style="white-space: pre-wrap; word-wrap: break-word; background: #f5f5f5; padding: 15px; border-radius: 4px; max-height: 400px; overflow-y: auto;">' + (data.assistant_response || '') + '</pre>' +
            '</div>';

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
<?php

namespace App\Admin\Extensions\Grid\Displayers;

use Dcat\Admin\Admin;
use Dcat\Admin\Grid\Displayers\AbstractDisplayer;
use Dcat\Admin\Support\Helper;

/**
 * 可复制指定值的列显示扩展
 *
 * 使用示例：
 * 1. 复制指定字段值：$grid->column('name')->copyableValue('id')
 * 2. 复制当前字段原始值：$grid->column('key')->display('查看密钥')->copyableValue()
 * 3. 使用闭包动态获取值：$grid->column('name')->copyableValue(function() { return $this->id; })
 */
class CopyableValue extends AbstractDisplayer
{
    /**
     * 添加复制功能的 JavaScript 脚本
     */
    protected function addScript()
    {
        $script = <<<'JS'
$('.grid-column-copyable-value').off('click').on('click', function (e) {
    e.preventDefault();

    var content = $(this).data('content');

    var $temp = $('<input>');

    $("body").append($temp);
    $temp.val(content).select();
    document.execCommand("copy");
    $temp.remove();

    $(this).tooltip('show');

    // 1秒后隐藏提示
    setTimeout(() => {
        $(this).tooltip('hide');
    }, 1000);
});
JS;
        Admin::script($script);
    }

    /**
     * 显示可复制的值
     *
     * @param  mixed  $value  要复制的值，可以是：
     *                        - 字符串：直接复制该值
     *                        - 字段名：复制当前行的指定字段值
     *                        - 闭包：动态返回要复制的值
     * @return string
     */
    public function display($value = null)
    {
        $this->addScript();

        // 如果未指定值，使用当前列的原始值（从数据库查询的值）
        if ($value === null) {
            $copyValue = $this->column->getOriginal();
        }
        // 如果是闭包，执行闭包获取值
        elseif ($value instanceof \Closure) {
            $copyValue = $value->call($this->row, $this->value);
        }
        // 如果是字段名（当前行存在该字段）
        elseif (isset($this->row->{$value})) {
            $copyValue = $this->row->{$value};
        }
        // 否则直接使用传入的值
        else {
            $copyValue = $value;
        }

        // 如果要复制的值为空，直接返回显示值
        if ($copyValue === '' || $copyValue === null) {
            return $this->value;
        }

        // HTML 实体编码，防止 XSS 攻击
        $copyValue = Helper::htmlEntityEncode($copyValue);
        $displayValue = Helper::htmlEntityEncode($this->value);

        // 生成 HTML
        $html = <<<HTML
<a href="javascript:void(0);" class="grid-column-copyable-value text-muted" data-content="{$copyValue}" title="已复制到剪贴板" data-placement="bottom" data-trigger="manual">
    <i class="fa fa-copy"></i>
</a>&nbsp;{$displayValue}
HTML;

        return $html;
    }
}
<?php

namespace App\Admin\Extensions\Grid\Displayers;

use Dcat\Admin\Grid\Displayers\AbstractDisplayer;
use Dcat\Admin\Support\Helper;

/**
 * 多字段分行显示扩展
 *
 * 在一列中显示多个字段的值，每个字段单独一行
 *
 * 使用示例：
 * 1. 基本用法：$grid->column('name', '信息')->multiFields(['name', 'email'])
 * 2. 带标签：$grid->column('name', '信息')->multiFields(['name' => '姓名', 'email' => '邮箱'])
 * 3. 混合模式：$grid->column('name', '信息')->multiFields(['name' => '姓名', 'phone'])
 * 4. 自定义分隔符：$grid->column('name', '信息')->multiFields(['name', 'email'], '<br>')
 */
class MultiFields extends AbstractDisplayer
{
    /**
     * 显示多个字段值
     *
     * @param  array  $fields  字段配置，支持两种格式：
     *                         - 索引数组：['field1', 'field2'] - 只显示字段值
     *                         - 关联数组：['field1' => '标签1', 'field2' => '标签2'] - 带标签显示
     * @param  string  $separator  行分隔符，默认为 '<br>'
     * @return string
     */
    public function display($fields = [], $separator = '<br>')
    {
        if (empty($fields)) {
            return $this->value;
        }

        $lines = [];

        foreach ($fields as $key => $value) {
            // 判断是关联数组还是索引数组
            if (is_int($key)) {
                // 索引数组：$value 是字段名
                $fieldName = $value;
                $label = null;
            } else {
                // 关联数组：$key 是字段名，$value 是标签
                $fieldName = $key;
                $label = $value;
            }

            // 获取字段值
            $fieldValue = $this->row->{$fieldName} ?? null;

            // 跳过空值
            if ($fieldValue === null || $fieldValue === '') {
                continue;
            }

            // HTML 实体编码
            $fieldValue = Helper::htmlEntityEncode($fieldValue);

            // 构建行内容
            if ($label !== null) {
                $label = Helper::htmlEntityEncode($label);
                $lines[] = "<span class=\"text-muted\">{$label}:</span> {$fieldValue}";
            } else {
                $lines[] = $fieldValue;
            }
        }

        return implode($separator, $lines);
    }
}

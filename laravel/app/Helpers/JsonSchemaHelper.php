<?php

namespace App\Helpers;

/**
 * JSON Schema 修复工具
 *
 * 修复 PHP json_decode 导致的 JSON Schema 无效字段问题：
 * - PHP 的 json_decode 将空对象 {} 转为空数组 []
 * - 但 JSON Schema 要求 properties 等字段必须是 object
 * - Anthropic API 要求 input_schema 必须是 object
 */
class JsonSchemaHelper
{
    /**
     * 递归修复 JSON Schema 中的无效字段
     *
     * @param  array  $schema  JSON Schema 定义
     * @param  bool  $fillEmptySchema  顶层空 schema 是否填充最小合法结构
     * @return array 修复后的 Schema
     */
    public static function fixJsonSchema(array $schema, bool $fillEmptySchema = false): array
    {
        // 顶层空 schema 填充最小合法结构
        if ($fillEmptySchema && empty($schema)) {
            return ['type' => 'object'];
        }

        // 修复空的 properties 字段：[] -> {}
        if (isset($schema['properties']) && is_array($schema['properties']) && empty($schema['properties'])) {
            $schema['properties'] = new \stdClass;
        }

        // 修复空的 additionalProperties 字段：[] -> false
        if (isset($schema['additionalProperties']) && is_array($schema['additionalProperties']) && empty($schema['additionalProperties'])) {
            $schema['additionalProperties'] = false;
        }

        // 递归处理嵌套的 properties
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $key => $value) {
                if (is_array($value)) {
                    $schema['properties'][$key] = self::fixJsonSchema($value);
                }
            }
        }

        // 递归处理 items
        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = self::fixJsonSchema($schema['items']);
        }

        // 递归处理组合 schema
        foreach (['anyOf', 'allOf', 'oneOf', 'not'] as $keyword) {
            if (isset($schema[$keyword]) && is_array($schema[$keyword])) {
                foreach ($schema[$keyword] as $i => $value) {
                    if (is_array($value)) {
                        $schema[$keyword][$i] = self::fixJsonSchema($value);
                    }
                }
            }
        }

        return $schema;
    }
}

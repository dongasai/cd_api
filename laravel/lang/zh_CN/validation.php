<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 验证语言行
    |--------------------------------------------------------------------------
    |
    | 以下语言行包含验证类使用的默认错误消息。某些规则有多个版本，
    | 如大小规则。您可以在这里调整每条消息。
    |
    */

    'accepted' => '必须接受 :attribute。',
    'accepted_if' => '当 :other 为 :value 时，必须接受 :attribute。',
    'active_url' => ':attribute 必须是有效的 URL。',
    'after' => ':attribute 必须是 :date 之后的日期。',
    'after_or_equal' => ':attribute 必须是 :date 之后或等于 :date 的日期。',
    'alpha' => ':attribute 只能包含字母。',
    'alpha_dash' => ':attribute 只能包含字母、数字、破折号和下划线。',
    'alpha_num' => ':attribute 只能包含字母和数字。',
    'any_of' => ':attribute 无效。',
    'array' => ':attribute 必须是数组。',
    'ascii' => ':attribute 只能包含单字节字母数字字符和符号。',
    'before' => ':attribute 必须是 :date 之前的日期。',
    'before_or_equal' => ':attribute 必须是 :date 之前或等于 :date 的日期。',
    'between' => [
        'array' => ':attribute 必须有 :min 到 :max 个元素。',
        'file' => ':attribute 必须在 :min 到 :max KB 之间。',
        'numeric' => ':attribute 必须在 :min 到 :max 之间。',
        'string' => ':attribute 必须在 :min 到 :max 个字符之间。',
    ],
    'boolean' => ':attribute 必须是 true 或 false。',
    'can' => ':attribute 包含未授权的值。',
    'confirmed' => ':attribute 确认不匹配。',
    'contains' => ':attribute 缺少必需的值。',
    'current_password' => '密码错误。',
    'date' => ':attribute 必须是有效的日期。',
    'date_equals' => ':attribute 必须是等于 :date 的日期。',
    'date_format' => ':attribute 必须匹配格式 :format。',
    'decimal' => ':attribute 必须有 :decimal 位小数。',
    'declined' => '必须拒绝 :attribute。',
    'declined_if' => '当 :other 为 :value 时，必须拒绝 :attribute。',
    'different' => ':attribute 和 :other 必须不同。',
    'digits' => ':attribute 必须是 :digits 位数字。',
    'digits_between' => ':attribute 必须在 :min 到 :max 位数字之间。',
    'dimensions' => ':attribute 图片尺寸无效。',
    'distinct' => ':attribute 有重复值。',
    'doesnt_contain' => ':attribute 不能包含以下内容: :values。',
    'doesnt_end_with' => ':attribute 不能以以下内容结尾: :values。',
    'doesnt_start_with' => ':attribute 不能以以下内容开头: :values。',
    'email' => ':attribute 必须是有效的邮箱地址。',
    'encoding' => ':attribute 必须以 :encoding 编码。',
    'ends_with' => ':attribute 必须以以下内容结尾: :values。',
    'enum' => '选择的 :attribute 无效。',
    'exists' => '选择的 :attribute 无效。',
    'extensions' => ':attribute 必须是以下扩展名之一: :values。',
    'file' => ':attribute 必须是文件。',
    'filled' => ':attribute 必须有值。',
    'gt' => [
        'array' => ':attribute 必须超过 :value 个元素。',
        'file' => ':attribute 必须大于 :value KB。',
        'numeric' => ':attribute 必须大于 :value。',
        'string' => ':attribute 必须超过 :value 个字符。',
    ],
    'gte' => [
        'array' => ':attribute 必须有 :value 个或更多元素。',
        'file' => ':attribute 必须大于或等于 :value KB。',
        'numeric' => ':attribute 必须大于或等于 :value。',
        'string' => ':attribute 必须大于或等于 :value 个字符。',
    ],
    'hex_color' => ':attribute 必须是有效的十六进制颜色。',
    'image' => ':attribute 必须是图片。',
    'in' => '选择的 :attribute 无效。',
    'in_array' => ':attribute 必须存在于 :other 中。',
    'in_array_keys' => ':attribute 必须包含以下至少一个键: :values。',
    'integer' => ':attribute 必须是整数。',
    'ip' => ':attribute 必须是有效的 IP 地址。',
    'ipv4' => ':attribute 必须是有效的 IPv4 地址。',
    'ipv6' => ':attribute 必须是有效的 IPv6 地址。',
    'json' => ':attribute 必须是有效的 JSON 字符串。',
    'list' => ':attribute 必须是列表。',
    'lowercase' => ':attribute 必须是小写。',
    'lt' => [
        'array' => ':attribute 必须少于 :value 个元素。',
        'file' => ':attribute 必须小于 :value KB。',
        'numeric' => ':attribute 必须小于 :value。',
        'string' => ':attribute 必须少于 :value 个字符。',
    ],
    'lte' => [
        'array' => ':attribute 不能超过 :value 个元素。',
        'file' => ':attribute 必须小于或等于 :value KB。',
        'numeric' => ':attribute 必须小于或等于 :value。',
        'string' => ':attribute 不能超过 :value 个字符。',
    ],
    'mac_address' => ':attribute 必须是有效的 MAC 地址。',
    'max' => [
        'array' => ':attribute 不能超过 :max 个元素。',
        'file' => ':attribute 不能超过 :max KB。',
        'numeric' => ':attribute 不能超过 :max。',
        'string' => ':attribute 不能超过 :max 个字符。',
    ],
    'max_digits' => ':attribute 不能超过 :max 位数字。',
    'mimes' => ':attribute 必须是以下类型之一: :values。',
    'mimetypes' => ':attribute 必须是以下类型之一: :values。',
    'min' => [
        'array' => ':attribute 至少要有 :min 个元素。',
        'file' => ':attribute 至少要 :min KB。',
        'numeric' => ':attribute 至少要 :min。',
        'string' => ':attribute 至少要 :min 个字符。',
    ],
    'min_digits' => ':attribute 至少要 :min 位数字。',
    'missing' => ':attribute 必须不存在。',
    'missing_if' => '当 :other 为 :value 时，:attribute 必须不存在。',
    'missing_unless' => '除非 :other 为 :value，否则 :attribute 必须不存在。',
    'missing_with' => '当 :values 存在时，:attribute 必须不存在。',
    'missing_with_all' => '当 :values 都存在时，:attribute 必须不存在。',
    'multiple_of' => ':attribute 必须是 :value 的倍数。',
    'not_in' => '选择的 :attribute 无效。',
    'not_regex' => ':attribute 格式无效。',
    'numeric' => ':attribute 必须是数字。',
    'password' => [
        'letters' => ':attribute 必须包含至少一个字母。',
        'mixed' => ':attribute 必须包含至少一个大写和一个小写字母。',
        'numbers' => ':attribute 必须包含至少一个数字。',
        'symbols' => ':attribute 必须包含至少一个符号。',
        'uncompromised' => '该 :attribute 已在数据泄露中出现，请选择其他 :attribute。',
    ],
    'present' => ':attribute 必须存在。',
    'present_if' => '当 :other 为 :value 时，:attribute 必须存在。',
    'present_unless' => '除非 :other 为 :value，否则 :attribute 必须存在。',
    'present_with' => '当 :values 存在时，:attribute 必须存在。',
    'present_with_all' => '当 :values 都存在时，:attribute 必须存在。',
    'prohibited' => ':attribute 被禁止。',
    'prohibited_if' => '当 :other 为 :value 时，:attribute 被禁止。',
    'prohibited_if_accepted' => '当 :other 被接受时，:attribute 被禁止。',
    'prohibited_if_declined' => '当 :other 被拒绝时，:attribute 被禁止。',
    'prohibited_unless' => '除非 :other 为 :values，否则 :attribute 被禁止。',
    'prohibits' => ':attribute 禁止 :other 存在。',
    'regex' => ':attribute 格式无效。',
    'required' => ':attribute 不能为空。',
    'required_array_keys' => ':attribute 必须包含以下条目: :values。',
    'required_if' => '当 :other 为 :value 时，:attribute 不能为空。',
    'required_if_accepted' => '当 :other 被接受时，:attribute 不能为空。',
    'required_if_declined' => '当 :other 被拒绝时，:attribute 不能为空。',
    'required_unless' => '除非 :other 为 :values，否则 :attribute 不能为空。',
    'required_with' => '当 :values 存在时，:attribute 不能为空。',
    'required_with_all' => '当 :values 都存在时，:attribute 不能为空。',
    'required_without' => '当 :values 不存在时，:attribute 不能为空。',
    'required_without_all' => '当 :values 都不存在时，:attribute 不能为空。',
    'same' => ':attribute 必须与 :other 匹配。',
    'size' => [
        'array' => ':attribute 必须包含 :size 个元素。',
        'file' => ':attribute 必须是 :size KB。',
        'numeric' => ':attribute 必须是 :size。',
        'string' => ':attribute 必须是 :size 个字符。',
    ],
    'starts_with' => ':attribute 必须以以下内容开头: :values。',
    'string' => ':attribute 必须是字符串。',
    'timezone' => ':attribute 必须是有效的时区。',
    'unique' => ':attribute 已被占用。',
    'uploaded' => ':attribute 上传失败。',
    'uppercase' => ':attribute 必须是大写。',
    'url' => ':attribute 必须是有效的 URL。',
    'ulid' => ':attribute 必须是有效的 ULID。',
    'uuid' => ':attribute 必须是有效的 UUID。',

    /*
    |--------------------------------------------------------------------------
    | 自定义验证语言行
    |--------------------------------------------------------------------------
    |
    | 您可以使用 "attribute.rule" 约定指定属性的自定义验证消息。
    | 这使得为给定属性规则指定特定的自定义语言行变得快速。
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => '自定义消息',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 自定义验证属性名称
    |--------------------------------------------------------------------------
    |
    | 以下语言行用于将属性占位符替换为更友好的名称，
    | 例如用"邮箱地址"而不是"email"。这有助于让消息更清晰。
    |
    */

    'attributes' => [
        'username' => '用户名',
        'password' => '密码',
        'name' => '姓名',
        'email' => '邮箱',
    ],

];

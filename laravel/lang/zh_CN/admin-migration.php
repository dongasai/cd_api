<?php

return [
    // 字段翻译
    'fields' => [
        'name' => '迁移名称',
        'status' => '状态',
        'batch' => '批次',
        'file' => '文件名',
    ],

    // 标签翻译
    'labels' => [
        'title' => '数据库迁移管理',
        'list' => '迁移列表',
        'pending' => '待执行',
        'ran' => '已执行',
    ],

    // 选项翻译
    'options' => [
        'status' => [
            'pending' => '待执行',
            'ran' => '已执行',
        ],
    ],

    // 操作翻译
    'actions' => [
        'migrate_one' => '执行',
        'rollback_one' => '回滚',
        'batch_migrate' => '批量执行',
        'migrate_all' => '执行全部',
    ],

    // 确认对话框
    'confirm' => [
        'migrate_one' => '确定执行此迁移？',
        'migrate_one_desc' => '执行后数据库结构将发生变化，请确认操作无误',
        'rollback_one' => '确定回滚此迁移？',
        'rollback_one_desc' => '回滚将撤销数据库结构变更，可能导致数据丢失，请谨慎操作',
        'batch_migrate' => '确定批量执行选中的迁移？',
        'batch_migrate_desc' => '将依次执行选中的迁移文件，请确认操作无误',
        'migrate_all' => '确定执行全部待执行迁移？',
        'migrate_all_desc' => '将执行所有未执行的迁移文件，请确认操作无误',
    ],
];

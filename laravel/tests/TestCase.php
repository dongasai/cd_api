<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // 🚫 安全检查：禁止连接生产数据库
        $this->ensureNotProductionDatabase();
    }

    /**
     * 确保不是生产数据库
     */
    protected function ensureNotProductionDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        // 生产数据库特征
        $productionPatterns = [
            'cdapi',           // 生产数据库名
            'production',      // 包含 production
            'prod',            // 包含 prod
        ];

        foreach ($productionPatterns as $pattern) {
            if (str_contains($database, $pattern)) {
                $this->fail("🚫 测试禁止连接生产数据库：{$database}");
            }
        }

        // 只允许测试数据库
        $allowedPatterns = [
            ':memory:',        // SQLite 内存数据库
            'testing',         // 测试数据库
            'test_',           // test_ 前缀
            '_test',           // _test 后缀
        ];

        $isAllowed = false;
        foreach ($allowedPatterns as $pattern) {
            if (str_contains($database, $pattern)) {
                $isAllowed = true;
                break;
            }
        }

        if (! $isAllowed) {
            $this->fail("🚫 测试只能使用测试数据库（{$database} 不符合安全规则）");
        }
    }
}

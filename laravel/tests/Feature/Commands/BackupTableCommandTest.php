<?php

namespace Tests\Feature\Commands;

use App\Services\Backup\BackupFileManager;
use App\Services\Backup\TableResolver;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * 数据库表备份命令测试
 *
 * 注意：由于备份命令依赖真实数据库连接，本测试套件主要测试服务类的功能
 */
class BackupTableCommandTest extends TestCase
{
    protected string $backupPath;

    protected function setUp(): void
    {
        parent::setUp();

        // 使用临时目录进行测试
        $this->backupPath = storage_path('framework/testing/backups');

        // 确保测试目录存在
        if (! File::isDirectory($this->backupPath)) {
            File::makeDirectory($this->backupPath, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // 清理测试备份文件
        if (File::isDirectory($this->backupPath)) {
            File::deleteDirectory($this->backupPath);
        }

        parent::tearDown();
    }

    /** @test */
    public function it_fails_without_group_or_tables_option()
    {
        $this->artisan('backup:table')
            ->assertFailed()
            ->expectsOutput('请使用 --group 或 --tables 参数指定要备份的表');
    }

    /** @test */
    public function table_resolver_can_resolve_exact_table()
    {
        $resolver = app(TableResolver::class);

        // 获取所有表
        $allTables = $resolver->getAllTables();

        if (count($allTables) > 0) {
            // 测试精确匹配
            $tables = $resolver->resolvePattern($allTables[0]);
            $this->assertEquals([$allTables[0]], $tables);
        } else {
            $this->markTestSkipped('数据库中没有表');
        }
    }

    /** @test */
    public function table_resolver_can_resolve_wildcard()
    {
        $resolver = app(TableResolver::class);

        // 获取所有表
        $allTables = $resolver->getAllTables();

        if (count($allTables) > 0) {
            // 测试通配符 *
            $tables = $resolver->resolvePattern('*');
            $this->assertGreaterThan(0, count($tables));
        } else {
            $this->markTestSkipped('数据库中没有表');
        }
    }

    /** @test */
    public function table_resolver_can_filter_excluded_tables()
    {
        $resolver = app(TableResolver::class);

        // 测试排除表功能
        $resolved = $resolver->resolveTables(['*']);

        // 验证排除的表不在结果中
        $excluded = config('backup.exclude_tables', []);
        foreach ($excluded as $table) {
            $this->assertNotContains($table, $resolved, "排除表 {$table} 不应出现在结果中");
        }
    }

    /** @test */
    public function file_manager_can_compress_file()
    {
        $testFile = $this->backupPath.'/test.sql';
        File::put($testFile, 'test content');

        $fileManager = app(BackupFileManager::class);
        $compressedFile = $fileManager->compressFile($testFile);

        $this->assertTrue(File::exists($compressedFile));
        $this->assertEquals('gz', pathinfo($compressedFile, PATHINFO_EXTENSION));
        $this->assertFalse(File::exists($testFile)); // 原文件应被删除
    }

    /** @test */
    public function file_manager_generates_correct_filename()
    {
        $fileManager = app(BackupFileManager::class);

        $filename = $fileManager->generateFilename('users');

        $this->assertStringContainsString('users', $filename);
        $this->assertMatchesRegularExpression('/users_\d{8}_\d{6}/', $filename);
    }

    /** @test */
    public function file_manager_can_cleanup_old_files()
    {
        // 创建测试文件
        for ($i = 0; $i < 15; $i++) {
            $file = $this->backupPath.'/test_'.date('Ymd_His', time() - $i * 3600).'.sql.gz';
            File::put($file, 'test');
        }

        $fileManager = app(BackupFileManager::class);
        $deleted = $fileManager->cleanupOldBackups($this->backupPath, 'test', 5);

        // 验证删除了 10 个文件
        $this->assertEquals(10, $deleted);

        // 验证只保留了 5 个
        $files = File::files($this->backupPath);
        $this->assertCount(5, $files);
    }

    /** @test */
    public function file_manager_can_format_bytes()
    {
        $fileManager = app(BackupFileManager::class);

        $this->assertEquals('0 B', $fileManager->formatBytes(0));
        $this->assertEquals('512 B', $fileManager->formatBytes(512));
        $this->assertEquals('1024 B', $fileManager->formatBytes(1024)); // 等于1024不转换
        $this->assertEquals('1.5 KB', $fileManager->formatBytes(1536)); // > 1024 转换
        $this->assertEquals('1024 KB', $fileManager->formatBytes(1048576)); // 1024*1024
        $this->assertEquals('1024 MB', $fileManager->formatBytes(1073741824)); // 1024*1024*1024
    }

    /** @test */
    public function config_has_required_groups()
    {
        $groups = config('backup.groups');

        $this->assertIsArray($groups);
        $this->assertArrayHasKey('core', $groups);
        $this->assertArrayHasKey('audit', $groups);
    }

    /** @test */
    public function config_has_defaults()
    {
        $defaults = config('backup.defaults');

        $this->assertIsArray($defaults);
        $this->assertArrayHasKey('path', $defaults);
        $this->assertArrayHasKey('with_structure', $defaults);
        $this->assertArrayHasKey('compress', $defaults);
        $this->assertArrayHasKey('chunk_size', $defaults);
        $this->assertArrayHasKey('retention_count', $defaults);
    }

    /** @test */
    public function config_has_exclude_tables()
    {
        $excludeTables = config('backup.exclude_tables');

        $this->assertIsArray($excludeTables);
        $this->assertContains('cache', $excludeTables);
        $this->assertContains('sessions', $excludeTables);
    }
}

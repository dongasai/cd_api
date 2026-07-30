<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * 安全的数据库重置命令
 *
 * 替代 migrate:fresh，增加多重保护：
 * 1. 生产环境禁止
 * 2. 必须输入确认短语
 * 3. 自动备份
 */
class SafeDatabaseReset extends Command
{
    protected $signature = 'cdapi:safe-reset {--force : 强制执行，跳过确认}';

    protected $description = '安全的数据库重置（仅在开发环境）';

    public function handle(): int
    {
        // 1. 生产环境绝对禁止
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('🚫 生产环境禁止执行此命令！');
            $this->error('如需强制执行，请使用 --force 选项');

            return self::FAILURE;
        }

        // 2. 显示警告
        $this->warn('⚠️  警告：此操作将清空所有数据！');
        $this->warn('数据库：'.config('database.default'));
        $this->warn('环境：'.app()->environment());

        // 3. 需要输入确认短语
        if (! $this->option('force')) {
            $confirmPhrase = 'DELETE ALL DATA';
            $this->warn("请输入确认短语：{$confirmPhrase}");
            $input = $this->ask('确认短语');

            if ($input !== $confirmPhrase) {
                $this->error('确认短语错误，操作取消');

                return self::FAILURE;
            }
        }

        // 4. 自动备份（可选）
        if ($this->confirm('是否先执行备份？', true)) {
            $this->call('cdapi:backup:table', ['--group' => 'core']);
        }

        // 5. 执行重置
        $this->info('开始重置数据库...');
        $this->call('migrate:fresh', [
            '--seed' => true,
            '--force' => true,
        ]);

        $this->info('✅ 数据库重置完成');

        return self::SUCCESS;
    }
}

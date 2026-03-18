<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * 备份文件管理器
 *
 * 负责管理备份文件的生成、压缩、清理等操作。
 */
class BackupFileManager
{
    /**
     * 生成备份文件名
     *
     * @param  string  $table  表名
     * @param  string|null  $format  文件名格式（支持占位符）
     * @return string 文件名（不含扩展名）
     */
    public function generateFilename(string $table, ?string $format = null): string
    {
        $format = $format ?? config('backup.defaults.filename_format', '{table}_{date}_{time}');

        $replacements = [
            '{table}' => $table,
            '{date}' => now()->format('Ymd'),
            '{time}' => now()->format('His'),
            '{timestamp}' => now()->timestamp,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $format);
    }

    /**
     * 生成完整的备份文件路径
     *
     * @param  string  $table  表名
     * @param  string  $path  备份目录路径
     * @param  bool  $compress  是否压缩
     * @return string 完整文件路径
     */
    public function generateFilePath(string $table, string $path, bool $compress = true): string
    {
        $filename = $this->generateFilename($table);
        $extension = $compress ? 'sql.gz' : 'sql';

        return rtrim($path, '/').'/'.$filename.'.'.$extension;
    }

    /**
     * 压缩文件
     *
     * @param  string  $filepath  源文件路径
     * @return string 压缩后的文件路径
     *
     * @throws \RuntimeException 当压缩失败时抛出异常
     */
    public function compressFile(string $filepath): string
    {
        if (! file_exists($filepath)) {
            throw new \RuntimeException("文件不存在: {$filepath}");
        }

        $compressedPath = $filepath.'.gz';

        // 使用 zlib 压缩
        $content = File::get($filepath);
        $compressed = gzencode($content, 9); // 最高压缩级别

        File::put($compressedPath, $compressed);

        // 删除原文件
        File::delete($filepath);

        return $compressedPath;
    }

    /**
     * 清理旧备份文件
     *
     * @param  string  $path  备份目录路径
     *                        string $tablePattern 表名模式（支持通配符）
     * @param  int  $keep  保留最近 N 个备份
     * @return int 删除的文件数量
     */
    public function cleanupOldBackups(string $path, string $tablePattern, int $keep = 10): int
    {
        if (! config('backup.cleanup.enabled', true)) {
            return 0;
        }

        // 获取所有匹配的备份文件
        $files = $this->getBackupFiles($path, $tablePattern);

        if (count($files) <= $keep) {
            return 0;
        }

        // 按修改时间排序（新到旧）
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        // 删除超出保留数量的文件
        $toDelete = array_slice($files, $keep);
        $deleted = 0;

        foreach ($toDelete as $file) {
            if (File::delete($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * 获取备份文件列表
     *
     * @param  string  $path  备份目录路径
     * @param  string|null  $tablePattern  表名模式（可选）
     * @return array 文件路径数组
     */
    public function getBackupFiles(string $path, ?string $tablePattern = null): array
    {
        if (! File::isDirectory($path)) {
            return [];
        }

        $pattern = $tablePattern
            ? $path.'/'.$tablePattern.'_*.sql*'
            : $path.'/*.sql*';

        $files = glob($pattern);

        return array_filter($files, function ($file) {
            return File::isFile($file);
        });
    }

    /**
     * 获取备份历史记录
     *
     * @param  string  $path  备份目录路径
     * @param  string|null  $tablePattern  表名模式（可选）
     * @return array 备份记录数组
     */
    public function getBackupHistory(string $path, ?string $tablePattern = null): array
    {
        $files = $this->getBackupFiles($path, $tablePattern);

        return array_map(function ($file) {
            return [
                'file' => basename($file),
                'path' => $file,
                'size' => File::size($file),
                'size_human' => $this->formatBytes(File::size($file)),
                'modified' => filemtime($file),
                'modified_at' => date('Y-m-d H:i:s', filemtime($file)),
                'compressed' => Str::endsWith($file, '.gz'),
            ];
        }, $files);
    }

    /**
     * 确保备份目录存在
     *
     * @param  string  $path  目录路径
     * @return bool 是否成功创建或目录已存在
     */
    public function ensureDirectoryExists(string $path): bool
    {
        if (File::isDirectory($path)) {
            return true;
        }

        return File::makeDirectory($path, 0755, true);
    }

    /**
     * 检查磁盘空间是否足够
     *
     * @param  string  $path  目标路径
     * @param  int  $requiredBytes  需要的字节数
     * @return bool 是否有足够空间
     */
    public function hasEnoughDiskSpace(string $path, int $requiredBytes): bool
    {
        $freeSpace = disk_free_space(dirname($path));

        return $freeSpace >= $requiredBytes;
    }

    /**
     * 格式化字节大小为人类可读格式
     *
     * @param  int  $bytes  字节数
     * @param  int  $precision  精度
     * @return string 格式化后的大小
     */
    public function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }

    /**
     * 写入备份内容到文件
     *
     * @param  string  $filepath  文件路径
     * @param  string  $content  SQL 内容
     * @return bool 是否成功
     */
    public function writeBackupFile(string $filepath, string $content): bool
    {
        return File::put($filepath, $content) !== false;
    }

    /**
     * 流式写入备份内容（用于大文件）
     *
     * @param  string  $filepath  文件路径
     * @param  callable  $contentGenerator  内容生成器函数
     * @return bool 是否成功
     */
    public function streamWriteBackupFile(string $filepath, callable $contentGenerator): bool
    {
        $handle = fopen($filepath, 'w');

        if ($handle === false) {
            return false;
        }

        try {
            foreach ($contentGenerator() as $chunk) {
                fwrite($handle, $chunk);
            }

            return true;
        } finally {
            fclose($handle);
        }
    }
}

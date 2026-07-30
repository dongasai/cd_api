<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * 默认管理员账户
     */
    public function up(): void
    {
        DB::table('users')->updateOrInsert(
            ['email' => 'admin@163.com'],
            [
                'name' => 'admin',
                'password' => Hash::make('admin123456'),
                'locale' => 'zh_CN',
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('users')->where('email', 'admin@163.com')->delete();
    }
};

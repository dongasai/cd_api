<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@163.com'],
            [
                'name' => 'admin',
                'password' => Hash::make('admin123456'),
                'locale' => 'zh_CN',
                'email_verified_at' => now(),
            ]
        );
    }
}

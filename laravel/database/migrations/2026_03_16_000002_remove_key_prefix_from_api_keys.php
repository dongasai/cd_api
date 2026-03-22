<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            // SQLite 需要先删除依赖列的索引
            $table->dropIndex('api_keys_key_prefix_index');
            $table->dropColumn('key_prefix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->string('key_prefix', 20)->nullable()->comment('API密钥前缀')->after('key');
            $table->index('key_prefix', 'api_keys_key_prefix_index');
        });
    }
};

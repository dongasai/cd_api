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
            // 检查 model_mappings 列是否存在
            $columns = Schema::getColumnListing('api_keys');
            $afterColumn = in_array('model_mappings', $columns) ? 'model_mappings' : 'allowed_models';

            $table->json('allowed_channels')->nullable()->after($afterColumn)->comment('允许的渠道ID列表(白名单)');
            $table->json('not_allowed_channels')->nullable()->after('allowed_channels')->comment('禁止的渠道ID列表(黑名单)');
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn(['allowed_channels', 'not_allowed_channels']);
        });
    }
};

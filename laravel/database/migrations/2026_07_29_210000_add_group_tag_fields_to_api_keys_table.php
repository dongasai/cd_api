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
            $table->json('allowed_groups')->nullable()->after('not_allowed_channels')->comment('允许的分组slug列表(白名单)');
            $table->json('not_allowed_groups')->nullable()->after('allowed_groups')->comment('禁止的分组slug列表(黑名单)');
            $table->json('allowed_tags')->nullable()->after('not_allowed_groups')->comment('允许的标签name列表(白名单)');
            $table->json('not_allowed_tags')->nullable()->after('allowed_tags')->comment('禁止的标签name列表(黑名单)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn(['allowed_groups', 'not_allowed_groups', 'allowed_tags', 'not_allowed_tags']);
        });
    }
};

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
        Schema::table('channel_affinity_rules', function (Blueprint $table) {
            $table->string('path_patterns', 255)->nullable()->comment('请求路径匹配')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('channel_affinity_rules', function (Blueprint $table) {
            $table->json('path_patterns')->nullable()->comment('请求路径正则匹配数组')->change();
        });
    }
};

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
            $table->text('model_patterns')->nullable()->comment('模型匹配正则表达式')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('channel_affinity_rules', function (Blueprint $table) {
            $table->json('model_patterns')->nullable()->comment('模型名称正则匹配数组')->change();
        });
    }
};

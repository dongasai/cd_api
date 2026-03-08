<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50)->default('system')->comment('配置分组');
            $table->string('key', 100)->comment('配置键');
            $table->text('value')->nullable()->comment('配置值');
            $table->enum('type', ['string', 'integer', 'float', 'boolean', 'json', 'array'])->default('string')->comment('值类型');
            $table->string('label', 100)->nullable()->comment('显示标签');
            $table->text('description')->nullable()->comment('配置说明');
            $table->boolean('is_public')->default(false)->comment('是否公开');
            $table->unsignedInteger('sort_order')->default(0)->comment('排序');
            $table->timestamps();

            $table->unique(['group', 'key']);
            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};

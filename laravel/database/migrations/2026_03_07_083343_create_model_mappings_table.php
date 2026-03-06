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
        Schema::create('model_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('alias', 100)->unique()->comment('模型别名 (对外展示)');
            $table->string('actual_model', 100)->comment('实际模型名称');
            $table->unsignedBigInteger('channel_id')->nullable()->comment('默认渠道 ID');
            $table->boolean('enabled')->default(true)->comment('是否启用');
            $table->timestamps();

            $table->index('alias');
            $table->index('enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_mappings');
    }
};

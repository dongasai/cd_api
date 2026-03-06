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
        Schema::create('channel_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('分组名称');
            $table->string('slug', 100)->unique()->comment('分组标识');
            $table->text('description')->nullable()->comment('分组描述');
            $table->json('config')->nullable()->comment('分组配置');
            $table->timestamps();

            $table->index('slug');
        });

        Schema::create('channel_group_pivot', function (Blueprint $table) {
            $table->unsignedBigInteger('channel_id');
            $table->unsignedBigInteger('group_id');
            $table->unsignedInteger('priority')->default(1)->comment('组内优先级');

            $table->primary(['channel_id', 'group_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_group_pivot');
        Schema::dropIfExists('channel_groups');
    }
};

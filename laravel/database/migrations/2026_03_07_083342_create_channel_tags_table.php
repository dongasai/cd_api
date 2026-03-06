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
        Schema::create('channel_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique()->comment('标签名称');
            $table->string('color', 7)->default('#666666')->comment('标签颜色');
            $table->string('description', 255)->nullable()->comment('标签描述');
            $table->timestamp('created_at')->useCurrent();

            $table->index('name');
        });

        Schema::create('channel_tag_pivot', function (Blueprint $table) {
            $table->unsignedBigInteger('channel_id');
            $table->unsignedBigInteger('tag_id');

            $table->primary(['channel_id', 'tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_tag_pivot');
        Schema::dropIfExists('channel_tags');
    }
};

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
        Schema::table('model_mappings', function (Blueprint $table) {
            $table->json('capabilities')->nullable()->comment('模型能力: reasoning/text/image/audio/video/tool_call/web_search');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('model_mappings', function (Blueprint $table) {
            $table->dropColumn('capabilities');
        });
    }
};

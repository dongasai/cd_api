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
        if (Schema::hasTable('coding_sliding_windows')) {
            return;
        }
        Schema::create('coding_sliding_windows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->comment('Coding账户ID');
            $table->string('window_type', 20)->comment('window type: 5h/1d/7d/30d');
            $table->unsignedInteger('window_seconds')->comment('window duration in seconds');
            $table->timestamp('started_at')->comment('window start time');
            $table->timestamp('ends_at')->comment('window end time');
            $table->string('status', 20)->default('active')->comment('status: active/expired');
            $table->timestamps();

            $table->index(['account_id', 'window_type', 'status']);
            $table->index(['ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coding_sliding_windows');
    }
};

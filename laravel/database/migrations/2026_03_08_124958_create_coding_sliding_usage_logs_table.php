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
        Schema::create('coding_sliding_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('coding_accounts')->cascadeOnDelete();
            $table->foreignId('window_id')->nullable()->constrained('coding_sliding_windows')->nullOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained('channels')->nullOnDelete();
            $table->string('request_id', 64)->nullable();

            // Usage fields
            $table->unsignedInteger('requests')->default(0);
            $table->unsignedBigInteger('tokens_input')->default(0);
            $table->unsignedBigInteger('tokens_output')->default(0);
            $table->unsignedBigInteger('tokens_total')->default(0);

            // Model info
            $table->string('model', 100)->nullable();
            $table->decimal('model_multiplier', 5, 2)->default(1.00);

            // Status
            $table->string('status', 20)->default('success');
            $table->json('metadata')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Indexes - critical for sliding window queries
            $table->index(['account_id', 'created_at']);
            $table->index(['window_id', 'created_at']);
            $table->index(['request_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coding_sliding_usage_logs');
    }
};

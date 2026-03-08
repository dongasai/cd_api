<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('model_mappings');
    }

    public function down(): void
    {
        Schema::create('model_mappings', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->string('alias', 100);
            $table->string('actual_model', 100);
            $table->unsignedBigInteger('channel_id')->nullable();
            $table->boolean('enabled')->default(true);
            $table->json('capabilities')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('alias');
            $table->index('alias');
            $table->index('enabled');
        });
    }
};

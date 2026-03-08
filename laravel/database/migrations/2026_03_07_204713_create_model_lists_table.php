<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_lists', function (Blueprint $table) {
            $table->id();
            $table->string('model_name', 100)->unique();
            $table->string('display_name', 100)->nullable();
            $table->string('provider', 50)->nullable();
            $table->text('description')->nullable();
            $table->json('capabilities')->nullable();
            $table->unsignedInteger('context_length')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('provider');
            $table->index('is_enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_lists');
    }
};

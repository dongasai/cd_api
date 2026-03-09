<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coding_accounts', function (Blueprint $table) {
            $table->timestamp('disabled_at')->nullable()->after('expires_at')->comment('禁用时间');
        });
    }

    public function down(): void
    {
        Schema::table('coding_accounts', function (Blueprint $table) {
            $table->dropColumn('disabled_at');
        });
    }
};

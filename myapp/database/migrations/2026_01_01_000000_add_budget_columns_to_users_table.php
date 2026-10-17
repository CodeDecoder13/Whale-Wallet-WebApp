<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pay_cycle')->default('monthly');
            $table->json('pay_dates')->nullable();
            $table->timestamp('ai_consent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['pay_cycle', 'pay_dates', 'ai_consent_at']));
    }
};

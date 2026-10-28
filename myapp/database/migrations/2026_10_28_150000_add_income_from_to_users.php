<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // first month (YYYY-MM) the onboarding salary counts; null = from the start
        Schema::table('users', fn (Blueprint $table) => $table->string('income_from', 7)->nullable());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('income_from'));
    }
};

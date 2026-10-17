<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** month = 'YYYY-MM' for income actually received that month; null is the expected monthly baseline. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('income_sources', fn (Blueprint $t) => $t->string('month', 7)->nullable()->index());
    }

    public function down(): void
    {
        Schema::table('income_sources', fn (Blueprint $t) => $t->dropColumn('month'));
    }
};
